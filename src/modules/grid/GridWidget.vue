<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<section class="grid-widget"
		:aria-labelledby="hasHeading ? headingId : undefined"
		:aria-label="hasHeading ? undefined : label"
		data-cy="gridWidget">
		<header v-if="hasHeading" class="grid-widget__header">
			<h2 :id="headingId" class="grid-widget__title">
				{{ widget.configuration.showTitle ? label : typeLabel }}
			</h2>
			<NcActions v-if="editable" :aria-label="t('tables', 'Widget actions')" :force-menu="true">
				<NcActionButton v-if="isKnownType" :close-after-click="true" @click="$emit('configure', widget)">
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
				<NcActionSeparator v-if="arrangeable" />
				<NcActionButton v-for="step in arrangeable ? layoutSteps : []"
					:key="step.key"
					:disabled="step.disabled"
					:data-cy="'widget' + step.key"
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
import { computed } from 'vue'
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
import { mapActions, mapState } from 'pinia'
import { useTablesStore } from '../../store/store.js'
import { GRID_COLUMNS, GRID_MIN_WIDTH, findWidgetType } from './gridLayout.js'
import { getWidgetComponent } from './widgetRegistry.js'

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
		ArrowCollapseHorizontal,
		ArrowCollapseVertical,
		ArrowDown,
		ArrowExpandHorizontal,
		ArrowExpandVertical,
		ArrowLeft,
		ArrowRight,
		ArrowUp,
		CogOutline,
		TrashCanOutline,
	},
	provide() {
		return {
			// A widget that renders its own heading sits one level below the card's heading
			widgetHeadingLevel: computed(() => this.hasHeading ? 3 : 2),
			// Set when the server marked the widget's stored target as gone
			widgetBroken: computed(() => this.widget.broken === true),
		}
	},
	props: {
		widget: {
			type: Object,
			required: true,
		},
		// Where the widget sits, so steps that cannot apply are disabled
		layoutItem: {
			type: Object,
			default: null,
		},
		editable: {
			type: Boolean,
			default: false,
		},
		// False on a narrowed grid, which is only shown and never arranged
		arrangeable: {
			type: Boolean,
			default: true,
		},
	},
	emits: ['configure', 'remove', 'move'],
	computed: {
		...mapState(useTablesStore, ['widgetTypes']),
		hasHeading() {
			return this.widget.configuration.showTitle || this.editable
		},
		headingId() {
			return 'grid-widget-title-' + this.widget.id
		},
		label() {
			return this.widget.configuration.title || this.typeLabel
		},
		widgetComponent() {
			return getWidgetComponent(this.widget.type)
		},
		// A widget of a type the server does not know is shown, not configured: its schema is unknown
		isKnownType() {
			return findWidgetType(this.widgetTypes, this.widget.type) !== null
		},
		typeLabel() {
			return findWidgetType(this.widgetTypes, this.widget.type)?.title ?? this.widget.type
		},
		layoutSteps() {
			const item = this.layoutItem
			return [
				{ key: 'MoveLeft', icon: 'ArrowLeft', label: t('tables', 'Move left'), change: { gridX: -1 }, disabled: item?.gridX <= 0 },
				{ key: 'MoveRight', icon: 'ArrowRight', label: t('tables', 'Move right'), change: { gridX: 1 }, disabled: item?.gridX + item?.gridWidth >= GRID_COLUMNS },
				{ key: 'MoveUp', icon: 'ArrowUp', label: t('tables', 'Move up'), change: { gridY: -1 }, disabled: item?.gridY <= 0 },
				{ key: 'MoveDown', icon: 'ArrowDown', label: t('tables', 'Move down'), change: { gridY: 1 }, disabled: false },
				{ key: 'Wider', icon: 'ArrowExpandHorizontal', label: t('tables', 'Make wider'), change: { gridWidth: 1 }, disabled: item?.gridWidth >= GRID_COLUMNS },
				{ key: 'Narrower', icon: 'ArrowCollapseHorizontal', label: t('tables', 'Make narrower'), change: { gridWidth: -1 }, disabled: item?.gridWidth <= GRID_MIN_WIDTH },
				{ key: 'Taller', icon: 'ArrowExpandVertical', label: t('tables', 'Make taller'), change: { gridHeight: 1 }, disabled: false },
				{ key: 'Shorter', icon: 'ArrowCollapseVertical', label: t('tables', 'Make shorter'), change: { gridHeight: -1 }, disabled: item?.gridHeight <= 1 },
			]
		},
	},
	created() {
		// The type titles come from the server, loaded the first time a grid shows a widget
		this.loadWidgetTypes()
	},
	methods: {
		...mapActions(useTablesStore, ['loadWidgetTypes']),
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
		gap: calc(2 * var(--default-grid-baseline));
		padding: calc(2 * var(--default-grid-baseline)) calc(3 * var(--default-grid-baseline));
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
		padding: calc(3 * var(--default-grid-baseline));
		color: var(--color-text-maxcontrast);
	}
}
</style>
