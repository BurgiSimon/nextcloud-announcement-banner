<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\AnnouncementBanner\Controller;

use OCA\AnnouncementBanner\AppInfo\Application;
use OCA\AnnouncementBanner\Db\Announcement;
use OCA\AnnouncementBanner\Exception\NotFoundException;
use OCA\AnnouncementBanner\Exception\ValidationException;
use OCA\AnnouncementBanner\Service\AnnouncementService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * What every logged-in user can do: read the banners meant for them, and close one.
 * The dashboard gets the same list as initial state, so the GET is for clients that
 * want to refresh it (and for checking the app from the command line).
 */
class BannerController extends Controller {
	public function __construct(
		IRequest $request,
		private AnnouncementService $service,
		private ?string $userId,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/banner')]
	public function index(): JSONResponse {
		return new JSONResponse(array_map(
			static fn (Announcement $a): array => $a->toBanner(),
			$this->service->findActiveForUser((string)$this->userId),
		));
	}

	/**
	 * State-changing, so it keeps CSRF protection: no #[NoCSRFRequired].
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/banner/{id}/dismiss')]
	public function dismiss(int $id): JSONResponse {
		try {
			$this->service->dismiss($id, (string)$this->userId);
		} catch (NotFoundException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (ValidationException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
		return new JSONResponse(['dismissed' => $id]);
	}
}
