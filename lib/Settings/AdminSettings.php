<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\AnnouncementBanner\Settings;

use OCA\AnnouncementBanner\AppInfo\Application;
use OCA\AnnouncementBanner\Service\AnnouncementService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\Settings\IDelegatedSettings;
use OCP\Util;

/**
 * The management form under Administration settings > Announcement Banner.
 *
 * Implementing IDelegatedSettings (rather than plain ISettings) lets an admin hand this
 * page to a group under Administration privileges, e.g. a communications team, without
 * making its members admins. The API routes accept the same delegation through
 * #[AuthorizedAdminSetting(settings: AdminSettings::class)].
 */
class AdminSettings implements IDelegatedSettings {
	public function __construct(
		private AnnouncementService $service,
		private IInitialState $initialState,
	) {
	}

	public function getForm(): TemplateResponse {
		// The list travels as initial state; the Vue form (js/<appid>-admin.mjs) reads it
		// with loadState() and talks to AnnouncementController from then on.
		$this->initialState->provideInitialState('announcements', $this->service->listForAdmin());
		$this->initialState->provideInitialState('levels', AnnouncementService::LEVELS);
		Util::addScript(Application::APP_ID, Application::APP_ID . '-admin');
		Util::addStyle(Application::APP_ID, Application::APP_ID . '-admin');

		return new TemplateResponse(Application::APP_ID, 'admin');
	}

	public function getSection(): string {
		return Application::APP_ID;
	}

	public function getPriority(): int {
		return 50;
	}

	/**
	 * The only form in its section, so it needs no separate name in the delegation list.
	 */
	public function getName(): ?string {
		return null;
	}

	/**
	 * Delegated users change announcements through the app's own API, not app config.
	 */
	public function getAuthorizedAppConfig(): array {
		return [];
	}
}
