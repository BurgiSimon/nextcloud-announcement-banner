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
 * A user closed the banner of one announcement. One row of announcement_banner_dis.
 *
 * @method int getAnnouncementId()
 * @method void setAnnouncementId(int $announcementId)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method int getDismissedAt()
 * @method void setDismissedAt(int $dismissedAt)
 */
class Dismissal extends Entity {
	protected int $announcementId = 0;
	protected string $userId = '';
	protected int $dismissedAt = 0;

	public function __construct() {
		$this->addType('announcementId', Types::BIGINT);
		$this->addType('userId', Types::STRING);
		$this->addType('dismissedAt', Types::BIGINT);
	}
}
