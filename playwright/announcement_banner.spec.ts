/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Browser tests for Announcement Banner, against a real Nextcloud 34 or 35 with the
 * app enabled. They need an admin and a regular (non-admin) account:
 *
 *   NEXTCLOUD_ADMIN / NEXTCLOUD_ADMIN_PASSWORD   default admin / admin
 *   NEXTCLOUD_USER  / NEXTCLOUD_PASSWORD         default alice / Banner-Test-User-2026!
 *
 * Every announcement a test creates has a title starting with "PW " and is deleted
 * again when the test ends, so the suite can run against a shared instance.
 */

import { type Browser, type Page, expect, test as base } from '@playwright/test'

// OC is a global Nextcloud injects into every page; it has no TypeScript declaration.
declare const OC: { generateUrl: (path: string) => string, requestToken: string }

const ADMIN = process.env.NEXTCLOUD_ADMIN ?? 'admin'
const ADMIN_PASSWORD = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin'
const USER = process.env.NEXTCLOUD_USER ?? 'alice'
const USER_PASSWORD = process.env.NEXTCLOUD_PASSWORD ?? 'Banner-Test-User-2026!'

const API = '/apps/announcement_banner/api/announcements'
const PREFIX = 'PW '

type Announcement = {
	title: string
	message?: string
	level?: 'info' | 'success' | 'warning' | 'error'
	linkUrl?: string
	startsAt?: number | null
	endsAt?: number | null
	dismissible?: boolean
}

async function login(page: Page, user: string, password: string): Promise<void> {
	await page.goto('login')
	await page.locator('#user').fill(user)
	await page.locator('#password').fill(password)
	await page.locator('button[type="submit"]').click()
	await page.waitForURL(/apps|dashboard|files/)
	// The first-run wizard's modal swallows clicks on a fresh account; close it the way
	// its own close button does. Harmless when it is already gone.
	await page.evaluate(() => fetch(OC.generateUrl('/apps/firstrunwizard/wizard'), {
		method: 'DELETE',
		headers: { requesttoken: OC.requestToken },
	}))
}

async function loggedInPage(browser: Browser, user: string, password: string): Promise<Page> {
	const context = await browser.newContext()
	const page = await context.newPage()
	await login(page, user, password)
	return page
}

/** fetch() from inside the page: the session cookie and the page's CSRF token are used. */
async function call(page: Page, method: string, path: string, body?: unknown): Promise<{ status: number, body: any }> {
	return page.evaluate(async ({ method, path, body }) => {
		const response = await fetch(OC.generateUrl(path), {
			method,
			headers: { 'Content-Type': 'application/json', requesttoken: OC.requestToken },
			body: body === undefined ? undefined : JSON.stringify(body),
		})
		return { status: response.status, body: await response.json() }
	}, { method, path, body })
}

const unique = (what: string) => `${PREFIX}${what} ${Date.now()}-${Math.floor(Math.random() * 1e6)}`
const now = () => Math.floor(Date.now() / 1000)

const test = base.extend<{
	adminPage: Page
	userPage: Page
	publish: (a: Announcement) => Promise<{ id: number, title: string }>
}>({
	adminPage: async ({ browser }, use) => {
		const page = await loggedInPage(browser, ADMIN, ADMIN_PASSWORD)
		await use(page)
		// Clean up everything this test created, through the UI or the API.
		const list = await call(page, 'GET', API)
		for (const a of list.body as { id: number, title: string }[]) {
			if (a.title.startsWith(PREFIX)) {
				await call(page, 'DELETE', `${API}/${a.id}`)
			}
		}
		await page.context().close()
	},
	userPage: async ({ browser }, use) => {
		const page = await loggedInPage(browser, USER, USER_PASSWORD)
		await use(page)
		await page.context().close()
	},
	publish: async ({ adminPage }, use) => {
		await use(async (a) => {
			const created = await call(adminPage, 'POST', API, a)
			expect(created.status, JSON.stringify(created.body)).toBe(201)
			return { id: created.body.id, title: a.title }
		})
	},
})

test('the admin settings section renders its form', async ({ adminPage: page }) => {
	await page.goto('settings/admin/announcement_banner')

	// level 2: the server also renders a visually hidden h1 with the same page name.
	await expect(page.getByRole('heading', { level: 2, name: 'Announcement Banner' })).toBeVisible()
	const form = page.getByRole('form', { name: 'New announcement' })
	await expect(form.getByLabel('Title', { exact: true })).toBeVisible()
	await expect(form.getByLabel('Message (optional)')).toBeVisible()
	await expect(form.getByLabel('Show from (optional)')).toBeVisible()
	await expect(form.getByRole('button', { name: 'Publish announcement' })).toBeDisabled()
})

test('an admin publishes an announcement through the form', async ({ adminPage: page }) => {
	const title = unique('Updated to the latest version')
	await page.goto('settings/admin/announcement_banner')
	const form = page.getByRole('form', { name: 'New announcement' })

	await form.getByLabel('Title', { exact: true }).fill(title)
	await form.getByLabel('Message (optional)').fill('Line one\nLine two')
	// NcCheckboxRadioSwitch hides the real <input>: click the visible label text.
	await form.getByText('Warning', { exact: true }).click()
	await form.getByLabel('Link (optional)').fill('https://nextcloud.com/changelog/')

	// The live preview is the same markup the Dashboard gets.
	const preview = form.getByRole('region', { name: 'Preview' })
	await expect(preview.getByRole('heading', { name: title })).toBeVisible()
	await expect(preview.locator('.announcement-banner__item--warning')).toBeVisible()
	// Styled, not just present: without banner.css the icon grows to the full width.
	const icon = await preview.locator('.announcement-banner__icon').boundingBox()
	expect(icon?.width).toBeLessThanOrEqual(32)

	await form.getByRole('button', { name: 'Publish announcement' }).click()
	await expect(page.getByTestId('admin-status')).toHaveText('Announcement published')

	// Stored, not just echoed: still listed after a reload, as active.
	await page.reload()
	const item = page.getByRole('listitem').filter({ hasText: title })
	await expect(item).toBeVisible()
	await expect(item).toContainText('Active')
	await expect(item).toContainText('Warning')
	await expect(item).toContainText('Dismissed by 0 users')
})

test('a regular user sees the banner between the Dashboard greeting and the widgets', async ({ publish, userPage: page }) => {
	const { title } = await publish({
		title: unique('Nextcloud was updated'),
		message: 'New features are live.',
		level: 'success',
		linkUrl: 'https://nextcloud.com/changelog/',
	})

	await page.goto('apps/dashboard/')
	const region = page.getByRole('region', { name: 'Announcements' })
	await expect(region.getByRole('heading', { name: title })).toBeVisible()
	await expect(region).toContainText('New features are live.')
	// Util::addStyle() worked: an unstyled icon would span the whole banner.
	const icon = await region.locator('.announcement-banner__icon').first().boundingBox()
	expect(icon?.width).toBeLessThanOrEqual(32)
	await expect(region.getByRole('link', { name: 'Learn more' }).first())
		.toHaveAttribute('href', 'https://nextcloud.com/changelog/')

	// Placement: right after the greeting, right before the panels, on 34 and 35 alike.
	const placement = await page.evaluate(() => {
		const banner = document.querySelector('.announcement-banner')
		return {
			parent: banner?.parentElement?.id,
			before: banner?.previousElementSibling?.tagName,
			after: banner?.nextElementSibling?.className,
		}
	})
	expect(placement.parent).toBe('app-dashboard')
	expect(placement.before).toBe('H2')
	expect(placement.after).toContain('panels')
})

test('a dismissed banner stays closed for that user only', async ({ publish, adminPage, userPage: page }) => {
	const { title } = await publish({ title: unique('Dismiss me') })

	await page.goto('apps/dashboard/')
	const heading = page.getByRole('region', { name: 'Announcements' }).getByRole('heading', { name: title })
	await expect(heading).toBeVisible()
	await page.getByRole('button', { name: `Dismiss announcement "${title}"` }).click()
	await expect(heading).toHaveCount(0)

	await page.reload()
	await expect(page.locator('#app-dashboard')).toBeVisible()
	await expect(page.getByRole('heading', { name: title })).toHaveCount(0)

	// The admin has not dismissed it, and the admin list counts the user.
	await adminPage.goto('apps/dashboard/')
	await expect(adminPage.getByRole('region', { name: 'Announcements' }).getByRole('heading', { name: title })).toBeVisible()
	await adminPage.goto('settings/admin/announcement_banner')
	await expect(adminPage.getByRole('listitem').filter({ hasText: title })).toContainText('Dismissed by 1 user')
})

test('a non-dismissible banner has no close button and refuses dismissal', async ({ publish, userPage: page }) => {
	const { id, title } = await publish({ title: unique('Maintenance tonight'), level: 'warning', dismissible: false })

	await page.goto('apps/dashboard/')
	await expect(page.getByRole('region', { name: 'Announcements' }).getByRole('heading', { name: title })).toBeVisible()
	await expect(page.getByRole('button', { name: `Dismiss announcement "${title}"` })).toHaveCount(0)

	const attempt = await call(page, 'POST', `/apps/announcement_banner/api/banner/${id}/dismiss`)
	expect(attempt.status).toBe(400)
})

test('scheduled and expired announcements stay off the Dashboard', async ({ publish, adminPage, userPage: page }) => {
	const future = await publish({ title: unique('Future'), startsAt: now() + 3600 })
	const past = await publish({ title: unique('Past'), startsAt: now() - 7200, endsAt: now() - 3600 })
	const current = await publish({ title: unique('Current'), endsAt: now() + 3600 })

	await page.goto('apps/dashboard/')
	await expect(page.getByRole('heading', { name: current.title })).toBeVisible()
	await expect(page.getByRole('heading', { name: future.title })).toHaveCount(0)
	await expect(page.getByRole('heading', { name: past.title })).toHaveCount(0)

	await adminPage.goto('settings/admin/announcement_banner')
	await expect(adminPage.getByRole('listitem').filter({ hasText: future.title })).toContainText('Scheduled')
	await expect(adminPage.getByRole('listitem').filter({ hasText: past.title })).toContainText('Expired')
})

test('an admin edits and deletes an announcement from the list', async ({ publish, adminPage: page }) => {
	const { title } = await publish({ title: unique('Typo in titel') })
	const fixed = title.replace('titel', 'title')

	await page.goto('settings/admin/announcement_banner')
	await page.getByRole('button', { name: `Edit “${title}”` }).click()
	const form = page.getByRole('form', { name: 'Edit announcement' })
	await expect(form.getByLabel('Title', { exact: true })).toHaveValue(title)
	await form.getByLabel('Title', { exact: true }).fill(fixed)
	await form.getByRole('button', { name: 'Save changes' }).click()
	await expect(page.getByTestId('admin-status')).toHaveText('Changes saved')
	await expect(page.getByRole('listitem').filter({ hasText: fixed })).toBeVisible()

	await page.getByRole('button', { name: `Delete “${fixed}”` }).click()
	const dialog = page.getByRole('dialog', { name: 'Delete announcement?' })
	await dialog.getByRole('button', { name: 'Delete' }).click()
	await expect(page.getByTestId('admin-status')).toHaveText('Announcement deleted')
	await expect(page.getByRole('listitem').filter({ hasText: fixed })).toHaveCount(0)
})

test('the form shows the server\'s validation error for an unsafe link', async ({ adminPage: page }) => {
	await page.goto('settings/admin/announcement_banner')
	const form = page.getByRole('form', { name: 'New announcement' })
	await form.getByLabel('Title', { exact: true }).fill(unique('Bad link'))
	await form.getByLabel('Link (optional)').fill('javascript:alert(1)')
	await form.getByRole('button', { name: 'Publish announcement' }).click()

	await expect(form).toContainText('The link must be an http:// or https:// address')
})

test('announcement text is shown as text, never as HTML', async ({ publish, userPage: page }) => {
	const { title } = await publish({
		title: unique('<img src=x onerror="window.__xss=1">'),
		message: '<b>not bold</b>',
	})
	await page.goto('apps/dashboard/')
	const region = page.getByRole('region', { name: 'Announcements' })
	await expect(region.getByRole('heading', { name: title })).toBeVisible()
	await expect(region).toContainText('<b>not bold</b>')
	expect(await page.evaluate(() => (window as any).__xss)).toBeUndefined()
	await expect(region.locator('img, b')).toHaveCount(0)
})

test('a regular user cannot manage announcements', async ({ userPage: page }) => {
	expect((await call(page, 'GET', API)).status).toBe(403)
	expect((await call(page, 'POST', API, { title: unique('not allowed') })).status).toBe(403)
	expect((await call(page, 'DELETE', `${API}/1`)).status).toBe(403)
})

test('the app produces no errors of its own', async ({ publish, adminPage, userPage }) => {
	await publish({ title: unique('No errors'), linkUrl: '/index.php/apps/files/' })
	const errors: string[] = []
	for (const page of [userPage, adminPage]) {
		page.on('pageerror', (error) => errors.push(`uncaught: ${error.message}`))
		// Only this app's requests: a shared instance may have other apps failing.
		page.on('response', (response) => {
			if (response.status() >= 400 && response.url().includes('announcement_banner')) {
				errors.push(`${response.status()} ${response.url()}`)
			}
		})
	}

	await userPage.goto('apps/dashboard/')
	await expect(userPage.getByRole('region', { name: 'Announcements' })).toBeVisible()
	await adminPage.goto('settings/admin/announcement_banner')
	await expect(adminPage.getByRole('form', { name: 'New announcement' })).toBeVisible()

	expect(errors).toEqual([])
})
