<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div ref="root" class="banner-preview" />
</template>

<script setup>
import { onMounted, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import { renderBanner } from '../bannerDom.js'
import { levelLabels } from '../levels.js'
// The Dashboard's stylesheet, so the admin page draws the preview with the same rules.
import '../banner.css'

const APP_ID = 'announcement_banner'

const props = defineProps({
	/** {title, message, level, linkUrl, dismissible} */
	announcement: {
		type: Object,
		required: true,
	},
})

const root = ref(null)

// The same DOM the Dashboard gets (bannerDom.js), so the preview cannot drift from it.
// The close button is drawn but does nothing here.
function draw() {
	if (!root.value) {
		return
	}
	const a = props.announcement
	const region = renderBanner([{
		id: 0,
		title: a.title.trim() || t(APP_ID, 'Title of the announcement'),
		message: a.message.trim(),
		level: a.level,
		linkUrl: a.linkUrl.trim(),
		dismissible: a.dismissible,
	}], {
		regionLabel: t(APP_ID, 'Preview'),
		learnMore: t(APP_ID, 'Learn more'),
		dismissLabel: (title) => t(APP_ID, 'Dismiss announcement "{title}"', { title }, undefined, { escape: false }),
		levelLabels: levelLabels(),
		onDismiss: async () => false,
	})
	root.value.replaceChildren(region)
}

onMounted(draw)
watch(() => ({ ...props.announcement }), draw, { deep: true })
</script>

<style scoped>
.banner-preview {
	padding: calc(var(--default-grid-baseline) * 4) 0;
	border-radius: var(--border-radius-container-large, var(--border-radius-large));
	/* On the Dashboard the banner floats over the background; the primary colour
	   stands in for it here. */
	background-color: var(--color-primary-element);
}

.banner-preview :deep(.announcement-banner) {
	margin-bottom: 0;
	max-width: none;
}
</style>
