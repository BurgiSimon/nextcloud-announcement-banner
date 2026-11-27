<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection :name="t(APP_ID, 'Announcement Banner')"
		:description="t(APP_ID, 'Announcements appear as a banner at the top of every user\'s Dashboard. Users can close a banner, and it stays closed for them, unless you make it non-dismissible.')">
		<form ref="formElement"
			class="announcement-form"
			:aria-label="editingId === null ? t(APP_ID, 'New announcement') : t(APP_ID, 'Edit announcement')"
			@submit.prevent="submit">
			<h3 class="announcement-form__heading">
				{{ editingId === null ? t(APP_ID, 'New announcement') : t(APP_ID, 'Edit announcement') }}
			</h3>

			<NcTextField ref="titleField"
				v-model="form.title"
				:label="t(APP_ID, 'Title')"
				:maxlength="255"
				required />

			<NcTextArea v-model="form.message"
				:label="t(APP_ID, 'Message (optional)')"
				:maxlength="4000"
				resize="vertical"
				rows="3" />

			<fieldset class="announcement-form__levels">
				<legend>{{ t(APP_ID, 'Type') }}</legend>
				<div class="announcement-form__level-buttons">
					<NcCheckboxRadioSwitch v-for="(label, level) in levels"
						:key="level"
						v-model="form.level"
						:value="level"
						name="announcement_banner_level"
						type="radio"
						button-variant
						button-variant-grouped="horizontal">
						{{ label }}
					</NcCheckboxRadioSwitch>
				</div>
			</fieldset>

			<NcTextField v-model="form.linkUrl"
				:label="t(APP_ID, 'Link (optional)')"
				:helper-text="t(APP_ID, 'Shown as “Learn more”. An https:// address, or a path on this server such as /index.php/apps/files/')"
				:maxlength="1024"
				placeholder="https://" />

			<div class="announcement-form__schedule">
				<div class="announcement-form__date">
					<NcDateTimePickerNative id="announcement_banner_starts"
						v-model="form.startsAt"
						type="datetime-local"
						:label="t(APP_ID, 'Show from (optional)')" />
					<NcButton v-if="form.startsAt"
						variant="tertiary"
						:aria-label="t(APP_ID, 'Clear start')"
						:title="t(APP_ID, 'Clear start')"
						@click="form.startsAt = null">
						<template #icon>
							<NcIconSvgWrapper :path="mdiClose" />
						</template>
					</NcButton>
				</div>
				<div class="announcement-form__date">
					<NcDateTimePickerNative id="announcement_banner_ends"
						v-model="form.endsAt"
						type="datetime-local"
						:min="form.startsAt"
						:label="t(APP_ID, 'Show until (optional)')" />
					<NcButton v-if="form.endsAt"
						variant="tertiary"
						:aria-label="t(APP_ID, 'Clear end')"
						:title="t(APP_ID, 'Clear end')"
						@click="form.endsAt = null">
						<template #icon>
							<NcIconSvgWrapper :path="mdiClose" />
						</template>
					</NcButton>
				</div>
			</div>

			<NcCheckboxRadioSwitch v-model="form.dismissible" type="switch">
				{{ t(APP_ID, 'Users can dismiss this banner') }}
			</NcCheckboxRadioSwitch>

			<div class="announcement-form__preview">
				<p class="announcement-form__label">
					{{ t(APP_ID, 'Preview') }}
				</p>
				<BannerPreview :announcement="form" />
			</div>

			<NcNoteCard v-if="error" type="error" :text="error" />

			<div class="announcement-form__actions">
				<NcButton type="submit" variant="primary" :disabled="saving || form.title.trim() === ''">
					{{ editingId === null ? t(APP_ID, 'Publish announcement') : t(APP_ID, 'Save changes') }}
				</NcButton>
				<NcButton v-if="editingId !== null" @click="resetForm">
					{{ t(APP_ID, 'Cancel') }}
				</NcButton>
			</div>
			<p class="announcement-form__status" role="status" data-testid="admin-status">
				{{ status }}
			</p>
		</form>

		<h3>{{ t(APP_ID, 'Announcements') }}</h3>
		<NcEmptyContent v-if="announcements.length === 0"
			:name="t(APP_ID, 'No announcements yet')"
			:description="t(APP_ID, 'Publish one above and it appears on every user\'s Dashboard.')">
			<template #icon>
				<NcIconSvgWrapper :path="mdiBullhornOutline" />
			</template>
		</NcEmptyContent>
		<ul v-else class="announcement-list" :aria-label="t(APP_ID, 'Announcements')">
			<li v-for="announcement in announcements"
				:key="announcement.id"
				class="announcement-list__item"
				:class="`announcement-list__item--${announcement.level}`"
				:data-announcement-id="announcement.id">
				<div class="announcement-list__main">
					<div class="announcement-list__title-row">
						<span class="announcement-list__title">{{ announcement.title }}</span>
						<span class="announcement-list__badge" :class="`announcement-list__badge--${announcement.status}`">
							{{ statusLabel(announcement.status) }}
						</span>
						<span class="announcement-list__badge">{{ levels[announcement.level] ?? announcement.level }}</span>
					</div>
					<p v-if="announcement.message" class="announcement-list__message">
						{{ announcement.message }}
					</p>
					<p class="announcement-list__meta">
						{{ scheduleText(announcement) }}
						·
						{{ announcement.dismissible
							? n(APP_ID, 'Dismissed by %n user', 'Dismissed by %n users', announcement.dismissals)
							: t(APP_ID, 'Cannot be dismissed') }}
					</p>
				</div>
				<div class="announcement-list__actions">
					<NcButton variant="tertiary"
						:aria-label="t(APP_ID, 'Edit “{title}”', { title: announcement.title }, undefined, { escape: false })"
						:title="t(APP_ID, 'Edit')"
						@click="edit(announcement)">
						<template #icon>
							<NcIconSvgWrapper :path="mdiPencilOutline" />
						</template>
					</NcButton>
					<NcButton variant="tertiary"
						:aria-label="t(APP_ID, 'Delete “{title}”', { title: announcement.title }, undefined, { escape: false })"
						:title="t(APP_ID, 'Delete')"
						@click="toDelete = announcement">
						<template #icon>
							<NcIconSvgWrapper :path="mdiTrashCanOutline" />
						</template>
					</NcButton>
				</div>
			</li>
		</ul>

		<NcDialog v-if="toDelete"
			:open="true"
			:name="t(APP_ID, 'Delete announcement?')"
			:message="t(APP_ID, 'The banner “{title}” disappears for everyone. This cannot be undone.', { title: toDelete.title }, undefined, { escape: false })"
			:buttons="deleteButtons"
			@update:open="(open) => { if (!open) toDelete = null }" />
	</NcSettingsSection>
</template>

<script setup>
import { computed, nextTick, reactive, ref } from 'vue'
import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { getCanonicalLocale, n, t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { mdiBullhornOutline, mdiClose, mdiPencilOutline, mdiTrashCanOutline } from '@mdi/js'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDateTimePickerNative from '@nextcloud/vue/components/NcDateTimePickerNative'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import BannerPreview from './components/BannerPreview.vue'
import { levelLabels } from './levels.js'

const APP_ID = 'announcement_banner'
const api = (path = '') => generateUrl(`/apps/${APP_ID}/api/announcements${path}`)

const levels = levelLabels()
// Provided by AdminSettings::getForm(): the same shape GET /api/announcements returns.
const announcements = ref(loadState(APP_ID, 'announcements', []))

const emptyForm = () => ({
	title: '',
	message: '',
	level: 'info',
	linkUrl: '',
	startsAt: null,
	endsAt: null,
	dismissible: true,
})
const form = reactive(emptyForm())
const editingId = ref(null)
const saving = ref(false)
const error = ref('')
const status = ref('')
const toDelete = ref(null)
const formElement = ref(null)
const titleField = ref(null)

// Dates travel as Unix timestamps; the pickers work with Date objects in the
// browser's time zone, which is what the admin means when typing a time.
const toTimestamp = (date) => (date instanceof Date && !isNaN(date) ? Math.floor(date.getTime() / 1000) : null)
const toDate = (timestamp) => (typeof timestamp === 'number' ? new Date(timestamp * 1000) : null)
const formatTime = (timestamp) => new Date(timestamp * 1000)
	.toLocaleString(getCanonicalLocale(), { dateStyle: 'medium', timeStyle: 'short' })

function statusLabel(value) {
	return {
		active: t(APP_ID, 'Active'),
		scheduled: t(APP_ID, 'Scheduled'),
		expired: t(APP_ID, 'Expired'),
	}[value] ?? value
}

function scheduleText(a) {
	if (a.startsAt && a.endsAt) {
		return t(APP_ID, 'Shown from {start} until {end}', { start: formatTime(a.startsAt), end: formatTime(a.endsAt) })
	}
	if (a.startsAt) {
		return t(APP_ID, 'Shown from {start}', { start: formatTime(a.startsAt) })
	}
	if (a.endsAt) {
		return t(APP_ID, 'Shown until {end}', { end: formatTime(a.endsAt) })
	}
	return t(APP_ID, 'Shown until deleted')
}

async function reload() {
	const response = await axios.get(api())
	announcements.value = response.data
}

function resetForm() {
	Object.assign(form, emptyForm())
	editingId.value = null
	error.value = ''
}

async function edit(announcement) {
	Object.assign(form, {
		title: announcement.title,
		message: announcement.message,
		level: announcement.level,
		linkUrl: announcement.linkUrl,
		startsAt: toDate(announcement.startsAt),
		endsAt: toDate(announcement.endsAt),
		dismissible: announcement.dismissible,
	})
	editingId.value = announcement.id
	error.value = ''
	status.value = ''
	await nextTick()
	formElement.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
	titleField.value?.focus?.()
}

async function submit() {
	saving.value = true
	error.value = ''
	status.value = ''
	const payload = {
		title: form.title,
		message: form.message,
		level: form.level,
		linkUrl: form.linkUrl,
		startsAt: toTimestamp(form.startsAt),
		endsAt: toTimestamp(form.endsAt),
		dismissible: form.dismissible,
	}
	try {
		if (editingId.value === null) {
			await axios.post(api(), payload)
			status.value = t(APP_ID, 'Announcement published')
		} else {
			await axios.put(api(`/${editingId.value}`), payload)
			status.value = t(APP_ID, 'Changes saved')
		}
		resetForm()
		await reload()
	} catch (e) {
		error.value = e.response?.data?.error ?? t(APP_ID, 'Could not save the announcement')
	} finally {
		saving.value = false
	}
}

const deleteButtons = computed(() => [
	{ label: t(APP_ID, 'Cancel'), callback: () => { toDelete.value = null } },
	{ label: t(APP_ID, 'Delete'), variant: 'error', callback: () => { remove(toDelete.value) } },
])

async function remove(announcement) {
	toDelete.value = null
	if (!announcement) {
		return
	}
	try {
		await axios.delete(api(`/${announcement.id}`))
		status.value = t(APP_ID, 'Announcement deleted')
		if (editingId.value === announcement.id) {
			resetForm()
		}
	} catch (e) {
		error.value = e.response?.data?.error ?? t(APP_ID, 'Could not delete the announcement')
	}
	await reload()
}
</script>

<style scoped>
.announcement-form {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 3);
	max-width: 720px;
	margin-bottom: calc(var(--default-grid-baseline) * 8);
}

.announcement-form__heading {
	margin: 0;
}

.announcement-form__levels {
	border: none;
	margin: 0;
	padding: 0;
}

.announcement-form__levels legend {
	margin-bottom: calc(var(--default-grid-baseline) * 1);
	padding: 0;
	font-weight: bold;
}

.announcement-form__level-buttons {
	display: flex;
	flex-wrap: wrap;
}

.announcement-form__schedule {
	display: flex;
	flex-wrap: wrap;
	gap: calc(var(--default-grid-baseline) * 4);
}

.announcement-form__date {
	display: flex;
	align-items: flex-end;
	gap: calc(var(--default-grid-baseline) * 1);
}

.announcement-form__label {
	margin: 0 0 calc(var(--default-grid-baseline) * 1);
	font-weight: bold;
}

.announcement-form__actions {
	display: flex;
	flex-wrap: wrap;
	gap: calc(var(--default-grid-baseline) * 2);
}

.announcement-form__status {
	min-height: 1.5em;
	margin: 0;
	color: var(--color-text-maxcontrast);
}

.announcement-list {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 2);
	max-width: 720px;
	margin: calc(var(--default-grid-baseline) * 2) 0 0;
	padding: 0;
	list-style: none;
}

.announcement-list__item {
	--accent: var(--color-element-info, var(--color-info-text));
	display: flex;
	align-items: flex-start;
	gap: calc(var(--default-grid-baseline) * 2);
	padding: calc(var(--default-grid-baseline) * 3);
	border: 1px solid var(--color-border);
	border-inline-start: 6px solid var(--accent);
	border-radius: var(--border-radius-large);
}

.announcement-list__item--success { --accent: var(--color-element-success, var(--color-success-text)); }
.announcement-list__item--warning { --accent: var(--color-element-warning, var(--color-warning-text)); }
.announcement-list__item--error { --accent: var(--color-element-error, var(--color-error-text)); }

.announcement-list__main {
	flex: 1 1 auto;
	min-width: 0;
}

.announcement-list__title-row {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: calc(var(--default-grid-baseline) * 2);
}

.announcement-list__title {
	font-weight: bold;
	overflow-wrap: anywhere;
}

.announcement-list__badge {
	padding: 0 calc(var(--default-grid-baseline) * 2);
	border: 1px solid var(--color-border-dark, var(--color-border));
	border-radius: var(--border-radius-pill, 999px);
	color: var(--color-text-maxcontrast);
	font-size: 0.85em;
	line-height: 1.6;
}

/* Outlined in the *-text colours, which the theme guarantees are readable on the main
   background in light and dark mode. */
.announcement-list__badge--active {
	border-color: var(--color-success-text);
	color: var(--color-success-text);
	font-weight: bold;
}

.announcement-list__badge--scheduled {
	border-color: var(--color-info-text);
	color: var(--color-info-text);
}

.announcement-list__message {
	margin: calc(var(--default-grid-baseline) * 1) 0 0;
	white-space: pre-line;
	overflow-wrap: anywhere;
}

.announcement-list__meta {
	margin: calc(var(--default-grid-baseline) * 1) 0 0;
	color: var(--color-text-maxcontrast);
}

.announcement-list__actions {
	display: flex;
	flex: 0 0 auto;
}
</style>
