<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\AnnouncementBanner\Service;

use OCA\AnnouncementBanner\Db\Announcement;
use OCA\AnnouncementBanner\Db\AnnouncementMapper;
use OCA\AnnouncementBanner\Db\DismissalMapper;
use OCA\AnnouncementBanner\Exception\NotFoundException;
use OCA\AnnouncementBanner\Exception\ValidationException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IL10N;

/**
 * Everything the controllers, the occ commands and the dashboard listener do with
 * announcements goes through here, so the validation rules exist exactly once.
 */
class AnnouncementService {
	public const LEVELS = ['info', 'success', 'warning', 'error'];
	public const TITLE_MAX = 255;
	public const MESSAGE_MAX = 4000;
	public const LINK_MAX = 1024;

	public function __construct(
		private AnnouncementMapper $mapper,
		private DismissalMapper $dismissalMapper,
		private ITimeFactory $timeFactory,
		private IL10N $l,
	) {
	}

	/**
	 * @throws NotFoundException
	 */
	public function get(int $id): Announcement {
		try {
			return $this->mapper->find($id);
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			throw new NotFoundException($this->l->t('Announcement not found'));
		}
	}

	/**
	 * @return Announcement[]
	 */
	public function findAll(): array {
		return $this->mapper->findAll();
	}

	/**
	 * The admin view: every announcement plus its current status and how many users
	 * have dismissed it.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function listForAdmin(): array {
		$now = $this->timeFactory->getTime();
		$dismissals = $this->dismissalMapper->countPerAnnouncement();
		$list = [];
		foreach ($this->mapper->findAll() as $announcement) {
			$list[] = $announcement->jsonSerialize() + [
				'status' => $this->statusOf($announcement, $now),
				'dismissals' => $dismissals[$announcement->getId()] ?? 0,
			];
		}
		return $list;
	}

	/**
	 * @return Announcement[] what the banner shows to this user right now
	 */
	public function findActiveForUser(string $userId): array {
		return $this->mapper->findActiveForUser($userId, $this->timeFactory->getTime());
	}

	/**
	 * @throws ValidationException
	 */
	public function create(
		string $title,
		string $message = '',
		string $level = 'info',
		string $linkUrl = '',
		?int $startsAt = null,
		?int $endsAt = null,
		bool $dismissible = true,
		?string $createdBy = null,
	): Announcement {
		$announcement = new Announcement();
		$this->apply($announcement, $title, $message, $level, $linkUrl, $startsAt, $endsAt, $dismissible);
		$now = $this->timeFactory->getTime();
		$announcement->setCreatedBy($createdBy);
		$announcement->setCreatedAt($now);
		$announcement->setUpdatedAt($now);
		return $this->mapper->insert($announcement);
	}

	/**
	 * Dismissals are kept: a user who closed the banner does not see it again because
	 * of a typo fix. Publish a new announcement for news everyone should see.
	 *
	 * @throws NotFoundException
	 * @throws ValidationException
	 */
	public function update(
		int $id,
		string $title,
		string $message = '',
		string $level = 'info',
		string $linkUrl = '',
		?int $startsAt = null,
		?int $endsAt = null,
		bool $dismissible = true,
	): Announcement {
		$announcement = $this->get($id);
		$this->apply($announcement, $title, $message, $level, $linkUrl, $startsAt, $endsAt, $dismissible);
		$announcement->setUpdatedAt($this->timeFactory->getTime());
		return $this->mapper->update($announcement);
	}

	/**
	 * @throws NotFoundException
	 */
	public function delete(int $id): Announcement {
		$announcement = $this->get($id);
		$this->dismissalMapper->deleteByAnnouncement($id);
		return $this->mapper->delete($announcement);
	}

	/**
	 * @throws NotFoundException
	 * @throws ValidationException when the announcement may not be dismissed
	 */
	public function dismiss(int $id, string $userId): void {
		$announcement = $this->get($id);
		if (!$announcement->isDismissibleByUsers()) {
			throw new ValidationException($this->l->t('This announcement cannot be dismissed'));
		}
		$this->dismissalMapper->dismiss($id, $userId, $this->timeFactory->getTime());
	}

	public function deleteDismissalsOfUser(string $userId): void {
		$this->dismissalMapper->deleteByUser($userId);
	}

	/**
	 * @return 'scheduled'|'active'|'expired'
	 */
	public function statusOf(Announcement $announcement, int $now): string {
		$startsAt = $announcement->getStartsAt();
		if ($startsAt !== null && $startsAt > $now) {
			return 'scheduled';
		}
		$endsAt = $announcement->getEndsAt();
		if ($endsAt !== null && $endsAt <= $now) {
			return 'expired';
		}
		return 'active';
	}

	/**
	 * Validate and normalise the input, then write it to the entity.
	 *
	 * @throws ValidationException
	 */
	private function apply(
		Announcement $announcement,
		string $title,
		string $message,
		string $level,
		string $linkUrl,
		?int $startsAt,
		?int $endsAt,
		bool $dismissible,
	): void {
		$title = trim($title);
		if ($title === '') {
			throw new ValidationException($this->l->t('A title is required'));
		}
		if (mb_strlen($title) > self::TITLE_MAX) {
			throw new ValidationException($this->l->t('The title must not be longer than %d characters', [self::TITLE_MAX]));
		}

		$message = trim(str_replace("\r\n", "\n", $message));
		if (mb_strlen($message) > self::MESSAGE_MAX) {
			throw new ValidationException($this->l->t('The message must not be longer than %d characters', [self::MESSAGE_MAX]));
		}

		if (!in_array($level, self::LEVELS, true)) {
			throw new ValidationException($this->l->t('Unknown type "%s"', [$level]));
		}

		$linkUrl = trim($linkUrl);
		if ($linkUrl !== '' && !$this->isSafeLink($linkUrl)) {
			throw new ValidationException($this->l->t('The link must be an http:// or https:// address, or a path on this server starting with /'));
		}

		if (($startsAt !== null && $startsAt < 0) || ($endsAt !== null && $endsAt < 0)) {
			throw new ValidationException($this->l->t('Invalid date'));
		}
		if ($startsAt !== null && $endsAt !== null && $endsAt <= $startsAt) {
			throw new ValidationException($this->l->t('The end must be after the start'));
		}

		$announcement->setTitle($title);
		$announcement->setMessage($message === '' ? null : $message);
		$announcement->setLevel($level);
		$announcement->setLinkUrl($linkUrl === '' ? null : $linkUrl);
		$announcement->setStartsAt($startsAt);
		$announcement->setEndsAt($endsAt);
		$announcement->setDismissible($dismissible);
	}

	/**
	 * The link becomes an href in every user's browser, so only web links and paths on
	 * this server pass: no javascript:, data: or protocol-relative //host URLs.
	 */
	private function isSafeLink(string $url): bool {
		if (mb_strlen($url) > self::LINK_MAX || preg_match('/\s/', $url) === 1) {
			return false;
		}
		if (str_starts_with($url, '/')) {
			return !str_starts_with($url, '//') && !str_contains($url, '\\');
		}
		$scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
		return in_array($scheme, ['http', 'https'], true)
			&& filter_var($url, FILTER_VALIDATE_URL) !== false;
	}
}
