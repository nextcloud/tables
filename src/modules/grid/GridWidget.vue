<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<section class="grid-widget" :aria-label="widget.configuration.title || typeLabel" data-cy="grid-widget">
		<header v-if="widget.configuration.showTitle || editable" class="grid-widget__header">
			<h2 class="grid-widget__title">
				{{ widget.configuration.showTitle ? widget.configuration.title : typeLabel }}
			</h2>
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
				<NcActionSeparator />
				<NcActionButton v-for="step in layoutSteps"
					:key="step.key"
					:data-cy="'widget-' + step.key"
					@click="$emit('move', widget, step.change)">
					<template #icon>
						<component :is="step.icon" :size="20" />
					</template>
					{{ step.label }}
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
import { NcActionButton, NcActions, NcActionSeparator } from '@nextcloud/vue'
import ArrowCollapseHorizontal from 'vue-material-design-icons/ArrowCollapseHorizontal.vue'
import ArrowCollapseVertical from 'vue-material-design-icons/ArrowCollapseVertical.vue'
import ArrowDown from 'vue-material-design-icons/ArrowDown.vue'
import ArrowExpandHorizontal from 'vue-material-design-icons/ArrowExpandHorizontal.vue'
import ArrowExpandVertical from 'vue-material-design-icons/ArrowExpandVertical.vue'
import ArrowLeft from 'vue-material-design-icons/ArrowLeft.vue'
import ArrowRight from 'vue-material-design-icons/ArrowRight.vue'
import ArrowUp from 'vue-material-design-icons/ArrowUp.vue'
import CogOutline from 'vue-material-design-icons/CogOutline.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import { mapState } from 'pinia'
import { useTablesStore } from '../../store/store.js'
import { findWidgetType, getWidgetComponent } from './widgetRegistry.js'

/**
 * The card around one widget: its optional title, its edit actions and the type specific body.
 * Moving and resizing are menu actions as well, so the grid can be arranged with a keyboard.
 */
export default {
	name: 'GridWidget',
	components: {
		NcActionButton,
		NcActions,
		NcActionSeparator,
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
	emits: ['configure', 'remove', 'move'],
	computed: {
		...mapState(useTablesStore, ['widgetTypes']),
		widgetComponent() {
			return getWidgetComponent(this.widget.type)
		},
		typeLabel() {
			return findWidgetType(this.widgetTypes, this.widget.type)?.title ?? this.widget.type
		},
		layoutSteps() {
			return [
				{ key: 'move-left', icon: ArrowLeft, label: t('tables', 'Move left'), change: { gridX: -1 } },
				{ key: 'move-right', icon: ArrowRight, label: t('tables', 'Move right'), change: { gridX: 1 } },
				{ key: 'move-up', icon: ArrowUp, label: t('tables', 'Move up'), change: { gridY: -1 } },
				{ key: 'move-down', icon: ArrowDown, label: t('tables', 'Move down'), change: { gridY: 1 } },
				{ key: 'wider', icon: ArrowExpandHorizontal, label: t('tables', 'Make wider'), change: { gridWidth: 1 } },
				{ key: 'narrower', icon: ArrowCollapseHorizontal, label: t('tables', 'Make narrower'), change: { gridWidth: -1 } },
				{ key: 'taller', icon: ArrowExpandVertical, label: t('tables', 'Make taller'), change: { gridHeight: 1 } },
				{ key: 'shorter', icon: ArrowCollapseVertical, label: t('tables', 'Make shorter'), change: { gridHeight: -1 } },
			]
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
