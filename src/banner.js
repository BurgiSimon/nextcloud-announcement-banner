/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * The Dashboard banner. Loaded by DashboardBannerListener only when the current user
 * has something to see; the announcements arrive as initial state, so nothing is
 * fetched on page load.
 *
 * Deliberately plain DOM code, no Vue: this runs on every Dashboard visit, next to the
 * Dashboard's own app, and should stay small. Everything user-provided is written with
 * textContent, never innerHTML.
 */

import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { levelLabels } from './levels.js'
import { renderBanner } from './bannerDom.js'
import './banner.css'

const APP_ID = 'announcement_banner'
const WAIT_FOR_DASHBOARD_MS = 15000

/**
 * The Dashboard is a Vue app, so its markup appears after our script may already have
 * run. Resolve with <main id="app-dashboard"> once it exists. On Nextcloud 34 it
 * replaces the mount point, on 35 it is rendered inside it; this works for both.
 *
 * @return {Promise<HTMLElement|null>}
 */
function waitForDashboard() {
	const find = () => document.getElementById('app-dashboard')
	return new Promise((resolve) => {
		const found = find()
		if (found) {
			resolve(found)
			return
		}
		const observer = new MutationObserver(() => {
			const element = find()
			if (element) {
				observer.disconnect()
				resolve(element)
			}
		})
		observer.observe(document.body, { childList: true, subtree: true })
		setTimeout(() => {
			observer.disconnect()
			resolve(find())
		}, WAIT_FOR_DASHBOARD_MS)
	})
}

/**
 * Between the greeting and the widgets: before the panels if they are there, else
 * right after the greeting heading, else at the top.
 *
 * @param {HTMLElement} dashboard <main id="app-dashboard">
 * @param {HTMLElement} region the banner
 */
function place(dashboard, region) {
	const panels = dashboard.querySelector(':scope > .panels')
	const greeting = dashboard.querySelector(':scope > h2')
	if (panels) {
		dashboard.insertBefore(region, panels)
	} else if (greeting) {
		greeting.after(region)
	} else {
		dashboard.prepend(region)
	}
}

async function dismiss(id) {
	try {
		await axios.post(generateUrl(`/apps/${APP_ID}/api/banner/{id}/dismiss`, { id }))
		return true
	} catch (error) {
		// Deleted by an admin in the meantime: nothing left to dismiss, hide it anyway.
		if (error?.response?.status === 404) {
			return true
		}
		console.error('[announcement_banner] could not dismiss announcement', id, error)
		return false
	}
}

async function main() {
	let banners = []
	try {
		banners = loadState(APP_ID, 'banners', [])
	} catch (error) {
		return
	}
	if (!Array.isArray(banners) || banners.length === 0) {
		return
	}

	const region = renderBanner(banners, {
		regionLabel: t(APP_ID, 'Announcements'),
		learnMore: t(APP_ID, 'Learn more'),
		dismissLabel: (title) => t(APP_ID, 'Dismiss announcement "{title}"', { title }, undefined, { escape: false }),
		levelLabels: levelLabels(),
		onDismiss: dismiss,
	})

	const dashboard = await waitForDashboard()
	if (dashboard) {
		place(dashboard, region)
	}
}

main()
