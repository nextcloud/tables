<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<section class="grid-widget" :aria-label="widget.title || typeLabel" data-cy="grid-widget">
		<header v-if="widget.showTitle || editable" class="grid-widget__header">
			<h3 class="grid-widget__title">
				{{ widget.showTitle ? widget.title : typeLabel }}
			</h3>
			<NcActions v-if="editable" :aria-label="t('tables', 'Widget actions')" :force-menu="true">
				<NcActionButton :close-after-click="true" @click="$emit('configure', widget)">
					<template #icon>
						<CogOutline :size="20" />
					</template>
					{{ t('tables', 'Configure widget') }}
				</NcActionButton>
				<NcActionButton :close-after-click="true" @click="$emit('remove', widget)">
					<template #icon>
						<TrashCanOutline :size="20" />
					</template>
					{{ t('tables', 'Remove widget') }}
				</NcActionButton>
			</NcActions>
		</header>
		<div class="grid-widget__body">
			<component :is="widgetComponent" v-if="widgetComponent" :content="widget.content" :editable="editable" />
			<p v-else class="grid-widget__unknown">
				{{ t('tables', 'Unknown widget type "{type}"', { type: widget.type }) }}
			</p>
		</div>
	</section>
</template>

<script>
import { NcActionButton, NcActions } from '@nextcloud/vue'
import CogOutline from 'vue-material-design-icons/CogOutline.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import { mapState } from 'pinia'
import { useTablesStore } from '../../store/store.js'
import { findWidgetType, getWidgetComponent } from './widgetRegistry.js'

/**
 * The card around one widget: its optional title, its edit actions and the type specific body.
 */
export default {
	name: 'GridWidget',
	components: {
		NcActionButton,
		NcActions,
		CogOutline,
		TrashCanOutline,
	},
	props: {
		widget: {
			type: Object,
			required: true,
		},
		editable: {
			type: Boolean,
			default: false,
		},
	},
	emits: ['configure', 'remove'],
	computed: {
		...mapState(useTablesStore, ['widgetTypes']),
		widgetComponent() {
			return getWidgetComponent(this.widget.type)
		},
		typeLabel() {
			const widgetType = findWidgetType(this.widgetTypes, this.widget.type)
			return widgetType ? t('tables', widgetType.title) : this.widget.type
		},
	},
}
</script>

<style lang="scss" scoped>
.grid-widget {
	display: flex;
	flex-direction: column;
	height: 100%;
	min-height: 0;

	&__header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: calc(2 * var(--default-grid-baseline, 4px));
		padding: calc(2 * var(--default-grid-baseline, 4px)) calc(3 * var(--default-grid-baseline, 4px));
		border-bottom: 1px solid var(--color-border);
	}

	&__title {
		margin: 0;
		font-size: var(--default-font-size);
		font-weight: bold;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	&__body {
		flex: 1;
		min-height: 0;
		overflow: auto;
	}

	&__unknown {
		padding: calc(3 * var(--default-grid-baseline, 4px));
		color: var(--color-text-maxcontrast);
	}
}
</style>
