<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\AnnouncementBanner\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * One announcement, one row of announcement_banner_ann.
 *
 * Nullable columns have nullable properties: the generated setter assigns the value
 * from the database straight into the property, and null into a non-nullable typed
 * property would be a TypeError on read.
 *
 * @method string getTitle()
 * @method void setTitle(string $title)
 * @method string|null getMessage()
 * @method void setMessage(?string $message)
 * @method string getLevel()
 * @method void setLevel(string $level)
 * @method string|null getLinkUrl()
 * @method void setLinkUrl(?string $linkUrl)
 * @method int|null getStartsAt()
 * @method void setStartsAt(?int $startsAt)
 * @method int|null getEndsAt()
 * @method void setEndsAt(?int $endsAt)
 * @method bool|null getDismissible()
 * @method void setDismissible(?bool $dismissible)
 * @method string|null getCreatedBy()
 * @method void setCreatedBy(?string $createdBy)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class Announcement extends Entity implements \JsonSerializable {
	protected string $title = '';
	protected ?string $message = null;
	protected string $level = 'info';
	protected ?string $linkUrl = null;
	protected ?int $startsAt = null;
	protected ?int $endsAt = null;
	protected ?bool $dismissible = true;
	protected ?string $createdBy = null;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('title', Types::STRING);
		$this->addType('message', Types::TEXT);
		$this->addType('level', Types::STRING);
		$this->addType('linkUrl', Types::STRING);
		$this->addType('startsAt', Types::BIGINT);
		$this->addType('endsAt', Types::BIGINT);
		$this->addType('dismissible', Types::BOOLEAN);
		$this->addType('createdBy', Types::STRING);
		$this->addType('createdAt', Types::BIGINT);
		$this->addType('updatedAt', Types::BIGINT);
	}

	public function isDismissibleByUsers(): bool {
		// A null from the database means the column default (true) was never written.
		return $this->getDismissible() !== false;
	}

	/**
	 * Whether the schedule includes the given moment. Mirrors the SQL condition in
	 * AnnouncementMapper::findActiveForUser().
	 */
	public function isActiveAt(int $now): bool {
		$startsAt = $this->getStartsAt();
		$endsAt = $this->getEndsAt();
		return ($startsAt === null || $startsAt <= $now) && ($endsAt === null || $endsAt > $now);
	}

	/**
	 * The fields a regular user's browser needs to draw the banner, and nothing more:
	 * who created it and when stays with the admins.
	 *
	 * @return array{id: int, title: string, message: string, level: string, linkUrl: string, dismissible: bool}
	 */
	public function toBanner(): array {
		return [
			'id' => $this->getId(),
			'title' => $this->getTitle(),
			'message' => $this->getMessage() ?? '',
			'level' => $this->getLevel(),
			'linkUrl' => $this->getLinkUrl() ?? '',
			'dismissible' => $this->isDismissibleByUsers(),
		];
	}

	/**
	 * The full record, for the admin settings and the admin API.
	 */
	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'title' => $this->getTitle(),
			'message' => $this->getMessage() ?? '',
			'level' => $this->getLevel(),
			'linkUrl' => $this->getLinkUrl() ?? '',
			'startsAt' => $this->getStartsAt(),
			'endsAt' => $this->getEndsAt(),
			'dismissible' => $this->isDismissibleByUsers(),
			'createdBy' => $this->getCreatedBy(),
			'createdAt' => $this->getCreatedAt(),
			'updatedAt' => $this->getUpdatedAt(),
		];
	}
}
