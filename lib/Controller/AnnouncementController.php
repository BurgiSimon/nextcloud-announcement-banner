<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\AnnouncementBanner\Controller;

use OCA\AnnouncementBanner\AppInfo\Application;
use OCA\AnnouncementBanner\Exception\NotFoundException;
use OCA\AnnouncementBanner\Exception\ValidationException;
use OCA\AnnouncementBanner\Service\AnnouncementService;
use OCA\AnnouncementBanner\Settings\AdminSettings;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Managing announcements. None of these routes carries #[NoAdminRequired], so they
 * are admin-only; #[AuthorizedAdminSetting] additionally admits the groups an admin
 * has delegated the "Announcement Banner" settings to (Administration privileges).
 *
 * Parameters arrive from the JSON body. Text parameters are nullable so that a client
 * sending null gets a validation message rather than a TypeError.
 */
class AnnouncementController extends Controller {
	public function __construct(
		IRequest $request,
		private AnnouncementService $service,
		private ?string $userId,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[AuthorizedAdminSetting(settings: AdminSettings::class)]
	#[FrontpageRoute(verb: 'GET', url: '/api/announcements')]
	public function index(): JSONResponse {
		return new JSONResponse($this->service->listForAdmin());
	}

	#[AuthorizedAdminSetting(settings: AdminSettings::class)]
	#[FrontpageRoute(verb: 'POST', url: '/api/announcements')]
	public function create(
		?string $title = '',
		?string $message = '',
		?string $level = 'info',
		?string $linkUrl = '',
		?int $startsAt = null,
		?int $endsAt = null,
		bool $dismissible = true,
	): JSONResponse {
		try {
			$announcement = $this->service->create(
				(string)$title, (string)$message, (string)$level, (string)$linkUrl,
				$startsAt, $endsAt, $dismissible, $this->userId,
			);
		} catch (ValidationException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
		return new JSONResponse($announcement, Http::STATUS_CREATED);
	}

	#[AuthorizedAdminSetting(settings: AdminSettings::class)]
	#[FrontpageRoute(verb: 'PUT', url: '/api/announcements/{id}')]
	public function update(
		int $id,
		?string $title = '',
		?string $message = '',
		?string $level = 'info',
		?string $linkUrl = '',
		?int $startsAt = null,
		?int $endsAt = null,
		bool $dismissible = true,
	): JSONResponse {
		try {
			$announcement = $this->service->update(
				$id, (string)$title, (string)$message, (string)$level, (string)$linkUrl,
				$startsAt, $endsAt, $dismissible,
			);
		} catch (NotFoundException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (ValidationException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
		return new JSONResponse($announcement);
	}

	#[AuthorizedAdminSetting(settings: AdminSettings::class)]
	#[FrontpageRoute(verb: 'DELETE', url: '/api/announcements/{id}')]
	public function destroy(int $id): JSONResponse {
		try {
			return new JSONResponse($this->service->delete($id));
		} catch (NotFoundException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}
}
