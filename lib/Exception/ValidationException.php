<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\AnnouncementBanner\Exception;

/**
 * Thrown by AnnouncementService; controllers turn it into a 400 with the (translated) message.
 */
class ValidationException extends \InvalidArgumentException {
}
