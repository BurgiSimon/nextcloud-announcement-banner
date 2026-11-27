/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createAppConfig } from '@nextcloud/vite-config'

// One entry per script Nextcloud loads:
// - banner: the Dashboard banner (DashboardBannerListener), plain DOM code, kept small
// - admin:  the management form (AdminSettings::getForm()), Vue + @nextcloud/vue
// Output: js/announcement_banner-<entry>.mjs and css/announcement_banner-<entry>.css,
// exactly what Util::addScript() and Util::addStyle() load.
export default createAppConfig({
	banner: 'src/banner.js',
	admin: 'src/admin.js',
}, {
	emptyOutputDirectory: {
		additionalDirectories: ['css'],
	},
})
