<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\AnnouncementBanner\AppInfo;

use OCA\AnnouncementBanner\Listener\DashboardBannerListener;
use OCA\AnnouncementBanner\Listener\UserDeletedListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\User\Events\UserDeletedEvent;

/**
 * The app's entry point. Nextcloud instantiates this class for every request that
 * touches the app, so keep it cheap: register things here, do the work in the
 * listeners, which are only constructed when their event fires.
 */
class Application extends App implements IBootstrap {
	public const APP_ID = 'announcement_banner';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(BeforeTemplateRenderedEvent::class, DashboardBannerListener::class);
		$context->registerEventListener(UserDeletedEvent::class, UserDeletedListener::class);
	}

	public function boot(IBootContext $context): void {
	}
}
