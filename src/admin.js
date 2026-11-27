/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createApp } from 'vue'
import AdminSettings from './AdminSettings.vue'

// Vite entry "admin" (vite.config.js): js/announcement_banner-admin.mjs, loaded by
// AdminSettings::getForm() into the empty <div> of templates/admin.php.
createApp(AdminSettings).mount('#announcement_banner_admin')
