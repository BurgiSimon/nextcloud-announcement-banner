/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

const APP_ID = 'announcement_banner'

/**
 * Display names of the four types, in the order the admin form offers them. The keys
 * are AnnouncementService::LEVELS.
 *
 * @return {Record<string, string>}
 */
export function levelLabels() {
	return {
		info: t(APP_ID, 'Information'),
		success: t(APP_ID, 'Success'),
		warning: t(APP_ID, 'Warning'),
		error: t(APP_ID, 'Error'),
	}
}
