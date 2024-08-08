<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div :tabindex="tabbable ? 0 : null" class="tile" :class="{active: localeActive}" @click="$emit('set-template')" @keyup.enter="$emit('set-template')">
		<h3>{{ title }}</h3>
		<p>{{ body }}</p>
	</div>
</template>
<script>

export default {

	filters: {
		truncate(string, num) {
			if (string.length >= num) {
				return string.substring(0, num) + '...'
			} else {
				return string
			}
		},
	},

	props: {
		active: {
			type: Boolean,
			default: false,
		},
		title: {
			type: String,
			default: '',
		},
		body: {
			type: String,
			default: '',
		},
		tabbable: {
		      type: Boolean,
		      default: false,
		    },
	},

	computed: {
		localeActive: {
			get() {
				return this.active
			},
			set(v) {
				this.$emit('update:active', !!v)
			},
		},
	},

}
</script>
<style lang="scss" scoped>

.tile {
	background: var(--color-main-background);
	border: 2px solid var(--color-border);
	border-radius: var(--border-radius-large);
	height: 135px;
	overflow-y: auto;
	padding: calc(var(--default-grid-baseline) * 3);
}

.tile, .tile > * {
	cursor: pointer;
}

.tile:hover, .tile:focus {
	border-color: var(--color-primary-element);
}

.tile:hover h3, .tile:focus h3 {
	font-weight: bold;
}

.active {
	border-color: var(--color-primary-element);
}

</style>
