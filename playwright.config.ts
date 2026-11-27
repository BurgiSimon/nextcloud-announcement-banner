/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineConfig, devices } from '@playwright/test'

// Point the tests at an existing Nextcloud that has this app installed:
//   PLAYWRIGHT_BASE_URL=http://nextcloud.local npx playwright test
// The default matches the dev environment from the nextcloud-dev-setup skill.
const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://nextcloud.local'

export default defineConfig({
	testDir: './playwright',
	// The tests share one instance's announcements (a banner one test publishes shows
	// up on the Dashboard another test looks at), so run them one at a time.
	fullyParallel: false,
	workers: 1,
	// The Dashboard loads every widget before it settles; on a slow instance 5 s is tight.
	expect: { timeout: 10_000 },
	forbidOnly: !!process.env.CI,
	retries: process.env.CI ? 2 : 0,
	reporter: process.env.CI ? [['dot'], ['github']] : [['list']],
	use: {
		// Nextcloud serves its routes under /index.php/, so keeping it in the base URL
		// lets specs use relative paths like 'apps/announcement_banner/'.
		baseURL: baseURL + '/index.php/',
		trace: 'on-first-retry',
		video: 'on-first-retry',
		ignoreHTTPSErrors: true,
		// Optional: use an already installed Chromium instead of `npx playwright install`.
		launchOptions: process.env.PLAYWRIGHT_CHROMIUM_PATH
			? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_PATH }
			: {},
	},
	projects: [
		{ name: 'chromium', use: { ...devices['Desktop Chrome'] } },
	],
})
