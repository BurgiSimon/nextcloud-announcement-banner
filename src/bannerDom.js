/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Builds the banner markup. Shared by the Dashboard (banner.js) and the live preview in
 * the admin settings (BannerPreview.vue), so the preview is exactly what users see.
 * User-provided text only ever goes through textContent.
 */

import { mdiAlert, mdiAlertDecagram, mdiCheckCircle, mdiClose, mdiInformation, mdiOpenInNew } from '@mdi/js'

const ICONS = {
	info: mdiInformation,
	success: mdiCheckCircle,
	warning: mdiAlert,
	error: mdiAlertDecagram,
}

const SVG_NS = 'http://www.w3.org/2000/svg'

function icon(path, className) {
	const svg = document.createElementNS(SVG_NS, 'svg')
	svg.setAttribute('viewBox', '0 0 24 24')
	svg.setAttribute('aria-hidden', 'true')
	svg.setAttribute('focusable', 'false')
	svg.setAttribute('class', className)
	const p = document.createElementNS(SVG_NS, 'path')
	p.setAttribute('d', path)
	svg.append(p)
	return svg
}

function element(tag, className, text) {
	const el = document.createElement(tag)
	el.className = className
	if (text !== undefined) {
		el.textContent = text
	}
	return el
}

/**
 * The server only stores http(s) links and paths on this server; check again here so a
 * value that slipped past (or an old row) can never become a javascript: link.
 *
 * @param {string} url the stored link
 */
export function isSafeLink(url) {
	return typeof url === 'string'
		&& (/^https?:\/\/[^\s]+$/i.test(url) || (/^\/[^/\\\s]/.test(url)))
}

function prefersReducedMotion() {
	return window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false
}

/**
 * @param {object} announcement {id, title, message, level, linkUrl, dismissible}
 * @param {object} options see renderBanner()
 * @param {Function} onEmpty called when the last item was dismissed
 */
function renderItem(announcement, options, onEmpty) {
	const level = ICONS[announcement.level] ? announcement.level : 'info'
	const item = element('div', `announcement-banner__item announcement-banner__item--${level}`)
	item.dataset.announcementId = String(announcement.id ?? '')

	item.append(icon(ICONS[level], 'announcement-banner__icon'))

	const body = element('div', 'announcement-banner__body')
	const title = element('h3', 'announcement-banner__title')
	// The colour and icon carry the type for sighted users; say it for everyone else.
	title.append(element('span', 'hidden-visually', `${options.levelLabels?.[level] ?? level}: `))
	title.append(document.createTextNode(announcement.title))
	body.append(title)

	if (announcement.message) {
		body.append(element('p', 'announcement-banner__message', announcement.message))
	}

	if (announcement.linkUrl && isSafeLink(announcement.linkUrl)) {
		const link = element('a', 'announcement-banner__link', options.learnMore)
		link.href = announcement.linkUrl
		if (/^https?:/i.test(announcement.linkUrl)) {
			link.target = '_blank'
			link.rel = 'noopener noreferrer'
			link.append(icon(mdiOpenInNew, 'announcement-banner__link-icon'))
		}
		body.append(link)
	}
	item.append(body)

	if (announcement.dismissible) {
		const close = element('button', 'announcement-banner__close')
		close.type = 'button'
		close.setAttribute('aria-label', options.dismissLabel(announcement.title))
		close.title = options.dismissLabel(announcement.title)
		close.append(icon(mdiClose, 'announcement-banner__close-icon'))
		close.addEventListener('click', async () => {
			close.disabled = true
			const ok = await options.onDismiss(announcement.id)
			if (!ok) {
				close.disabled = false
				return
			}
			const remove = () => {
				item.remove()
				onEmpty()
			}
			if (prefersReducedMotion()) {
				remove()
			} else {
				item.classList.add('announcement-banner__item--leaving')
				item.addEventListener('transitionend', remove, { once: true })
				// In case no transition runs (hidden tab, styles not loaded).
				setTimeout(remove, 400)
			}
		})
		item.append(close)
	}

	return item
}

/**
 * @param {object[]} announcements what to show, newest first
 * @param {object} options labels and the dismiss handler
 * @param {string} options.regionLabel accessible name of the region
 * @param {string} options.learnMore text of the link
 * @param {Function} options.dismissLabel (title) => accessible name of the close button
 * @param {Function} options.onDismiss async (id) => boolean, true once dismissed
 * @param {object} [options.levelLabels] level => spoken type, e.g. {warning: 'Warning'}
 * @return {HTMLElement} the region, not yet attached to the page
 */
export function renderBanner(announcements, options) {
	const region = element('section', 'announcement-banner')
	region.setAttribute('aria-label', options.regionLabel)
	const onEmpty = () => {
		if (!region.querySelector('.announcement-banner__item')) {
			region.remove()
		}
	}
	for (const announcement of announcements) {
		region.append(renderItem(announcement, options, onEmpty))
	}
	return region
}
