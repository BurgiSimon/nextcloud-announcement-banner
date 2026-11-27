<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\AnnouncementBanner\Listener;

use OCA\AnnouncementBanner\AppInfo\Application;
use OCA\AnnouncementBanner\Db\Announcement;
use OCA\AnnouncementBanner\Service\AnnouncementService;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IUserSession;
use OCP\Util;

/**
 * Puts the banner on the Dashboard, Nextcloud's home page.
 *
 * BeforeTemplateRenderedEvent fires for every page an app renders with a
 * TemplateResponse; this listener only acts on the Dashboard's own page. The banners
 * travel as initial state, so the page needs no extra request to show them, and when
 * there is nothing to show not even the script is loaded.
 *
 * @template-implements IEventListener<Event>
 */
class DashboardBannerListener implements IEventListener {
	public const STATE_KEY = 'banners';

	public function __construct(
		private AnnouncementService $service,
		private IInitialState $initialState,
		private IUserSession $userSession,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof BeforeTemplateRenderedEvent || !$event->isLoggedIn()) {
			return;
		}
		$response = $event->getResponse();
		if ($response->getApp() !== 'dashboard'
			|| $response->getTemplateName() !== 'index'
			|| $response->getRenderAs() !== TemplateResponse::RENDER_AS_USER) {
			return;
		}
		$user = $this->userSession->getUser();
		if ($user === null) {
			return;
		}

		$banners = array_map(
			static fn (Announcement $a): array => $a->toBanner(),
			$this->service->findActiveForUser($user->getUID()),
		);
		if ($banners === []) {
			return;
		}

		$this->initialState->provideInitialState(self::STATE_KEY, $banners);
		Util::addScript(Application::APP_ID, Application::APP_ID . '-banner');
		Util::addStyle(Application::APP_ID, Application::APP_ID . '-banner');
	}
}
