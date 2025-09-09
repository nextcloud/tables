<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="icon-label-container" :data-cy="title">
		<img v-if="thumbnailUrl" :src="thumbnailUrl" :width="iconSize" :height="iconSize">
		<img v-else-if="iconUrl" :src="iconUrl" :width="iconSize" :height="iconSize">
		<LinkIcon v-else-if="!hideDefaultIcon" :size="iconSize" />

		<div class="labels">
			<div :class="{underline: underlineTitle}">
				<a :href="url" target="_blank" :title="title">{{ title | truncate(truncateLength) }}</a>
			</div>
			<p v-if="subline" class="multiSelectOptionLabel span">
				{{ subline }}
			</p>
		</div>
	</div>
</template>

<script>
import LinkIcon from 'vue-material-design-icons/Link.vue'

export default {

	components: {
		LinkIcon,
	},

	filters: {
		truncate(string, num) {
			if (!string) {
				return ''
			}

			if (string.length >= num) {
				return string.substring(0, num) + '...'
			} else {
				return string
			}
		},
	},

	props: {
		thumbnailUrl: {
		      type: String,
		      default: null,
		    },
		iconUrl: {
		      type: String,
		      default: null,
		    },
		subline: {
		      type: String,
		      default: null,
		    },
		title: {
		      type: String,
		      default: null,
		    },
		url: {
		      type: String,
		      default: null,
		    },
		truncateLength: {
		      type: Number,
		      default: 40,
		    },
		iconSize: {
		      type: Number,
		      default: 35,
		    },
		hideDefaultIcon: {
		      type: Boolean,
		      default: false,
		    },
		underlineTitle: {
		      type: Boolean,
		      default: false,
		    },
	},

}
</script>
<style lang="scss" scoped>

.icon-label-container {
	display: flex;
	align-items: center;

	img, :deep(svg) {
		margin-inline-end: calc(var(--default-grid-baseline) * 2);
	}
}

.labels {
	display: block;
}

.underline a {
	text-decoration: underline;
}

</style>
