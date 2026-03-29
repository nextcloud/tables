<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="view-tab-bar">
		<button class="view-tab"
			:class="{ active: isTableActive }"
			@click="$emit('select-table')">
			<span class="view-tab-icon">&#x1F4CB;</span>
			<span class="view-tab-label">{{ t('tables', 'Table') }}</span>
		</button>
		<button v-for="view in views"
			:key="view.id"
			class="view-tab"
			:class="{ active: activeViewId === view.id }"
			@click="$emit('select-view', view)">
			<span class="view-tab-icon">{{ view.emoji || '&#x1F441;' }}</span>
			<span class="view-tab-label">{{ view.title }}</span>
			<span v-if="view.type === 'chart'" class="view-tab-badge">{{ t('tables', 'Chart') }}</span>
		</button>
		<button v-if="canCreate"
			class="view-tab view-tab--add"
			:title="t('tables', 'Add view')"
			@click="$emit('create-view')">
			<Plus :size="16" />
		</button>
	</div>
</template>

<script>
import Plus from 'vue-material-design-icons/Plus.vue'

export default {
	name: 'ViewTabBar',
	components: { Plus },
	props: {
		views: { type: Array, default: () => [] },
		activeViewId: { type: Number, default: null },
		isTableActive: { type: Boolean, default: false },
		canCreate: { type: Boolean, default: false },
	},
}
</script>

<style lang="scss" scoped>
.view-tab-bar {
	display: flex;
	align-items: stretch;
	gap: 0;
	border-bottom: 2px solid var(--color-border);
	margin-bottom: 0;
	overflow-x: auto;
	scrollbar-width: thin;
}

.view-tab {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	padding: 8px 16px;
	border: none;
	background: none;
	cursor: pointer;
	font-size: 14px;
	color: var(--color-text-maxcontrast);
	border-bottom: 2px solid transparent;
	margin-bottom: -2px;
	white-space: nowrap;
	transition: color 0.15s, border-color 0.15s;

	&:hover {
		color: var(--color-main-text);
		background: var(--color-background-hover);
	}

	&.active {
		color: var(--color-main-text);
		border-bottom-color: var(--color-primary-element);
		font-weight: 600;
	}
}

.view-tab-icon {
	font-size: 16px;
}

.view-tab-badge {
	font-size: 10px;
	padding: 1px 5px;
	border-radius: 8px;
	background: var(--color-primary-element-light);
	color: var(--color-primary-element);
	font-weight: 600;
	text-transform: uppercase;
}

.view-tab--add {
	padding: 8px 12px;
	color: var(--color-text-maxcontrast);

	&:hover {
		color: var(--color-primary-element);
	}
}
</style>
