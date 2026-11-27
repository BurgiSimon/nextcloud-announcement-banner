<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\AnnouncementBanner\Listener;

use OCA\AnnouncementBanner\Service\AnnouncementService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\UserDeletedEvent;

/**
 * A deleted account leaves no dismissal rows behind.
 *
 * @template-implements IEventListener<Event>
 */
class UserDeletedListener implements IEventListener {
	public function __construct(
		private AnnouncementService $service,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof UserDeletedEvent) {
			return;
		}
		$this->service->deleteDismissalsOfUser($event->getUser()->getUID());
	}
}
