<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<component :is="hasHeading ? 'section' : 'div'"
		class="grid-widget"
		:aria-labelledby="hasHeading ? headingId : undefined"
		data-cy="gridWidget">
		<header v-if="hasHeading || editable"
			class="grid-widget__header"
			:class="{ 'grid-widget__header--floating': !hasHeading }">
			<component :is="'h' + gridHeadingLevel" v-if="hasHeading" :id="headingId" class="grid-widget__title">
				<NamedIcon v-if="widget.icon" :name="widget.icon" :size="20" />
				<span class="grid-widget__title-text">{{ label }}</span>
			</component>
			<NcActions v-if="editable"
				ref="actions"
				:aria-label="t('tables', 'Actions for {label}', { label })" :force-menu="true">
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
					:ref="'step' + step.key"
					:disabled="step.disabled"
					:data-cy="'widget' + step.key"
					@click="move(step)">
					<template #icon>
						<component :is="step.icon" :size="20" />
					</template>
					{{ step.label }}
				</NcActionButton>
			</NcActions>
		</header>
		<p v-if="editable" class="hidden-visually" aria-live="polite" data-cy="gridWidgetAnnouncement">
			{{ announcement }}
		</p>
		<p v-if="widget.description" class="grid-widget__description" data-cy="gridWidgetDescription">
			{{ widget.description }}
		</p>
		<div class="grid-widget__body">
			<component :is="widgetComponent" v-if="widgetComponent" v-bind="componentProps" />
			<p v-else class="grid-widget__unknown">
				{{ t('tables', 'Unknown widget type "{type}"', { type: widget.type }) }}
			</p>
		</div>
	</component>
</template>

<script>
import { computed } from 'vue'
import { isRTL, translate as t } from '@nextcloud/l10n'
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
import NamedIcon from '../../shared/components/ncIconPicker/NamedIcon.vue'
import { GRID_COLUMNS, GRID_MIN_WIDTH, findWidgetType, targetTitle, widgetTarget } from './gridLayout.js'
import { WIDGET_TYPE_DATA, WIDGET_TYPE_TABLES, WIDGET_TYPE_TABS, getWidgetComponent } from './widgetRegistry.js'

/**
 * The card around one widget: its optional title, icon and description, its edit actions and the
 * type specific body.
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
		NamedIcon,
		TrashCanOutline,
	},
	inject: {
		// The level of the widget titles, one below the heading of the page around the grid
		gridHeadingLevel: { default: 2 },
	},
	provide() {
		return {
			// A widget that renders its own heading sits one level below the card's heading
			widgetHeadingLevel: computed(() => this.hasHeading ? this.gridHeadingLevel + 1 : this.gridHeadingLevel),
		}
	},
	props: {
		widget: {
			type: Object,
			required: true,
		},
		// The nodes of the application by relation id, to find the table or view of a data widget
		nodes: {
			type: Object,
			default: () => ({}),
		},
		// The content of the start page of the application, for the order of its tables and views
		startPageContent: {
			type: Object,
			default: () => ({}),
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
	data() {
		return {
			// Read out after a step from the menu, once the widget is at its new place
			announcement: '',
			movedStep: null,
		}
	},
	computed: {
		...mapState(useTablesStore, ['widgetTypes', 'tables', 'views']),
		// The same in view and edit mode, so the widgets keep their size; without a title the edit
		// menu floats over the widget
		hasHeading() {
			return !!this.widget.title || !!this.sourceTitle
		},
		// A data widget without a title of its own takes the title of its table or view
		sourceTitle() {
			if (this.widget.type !== WIDGET_TYPE_DATA) {
				return ''
			}
			const target = widgetTarget(this.widget, this.nodes)
			return target ? targetTitle(target, this.tables, this.views) : ''
		},
		headingId() {
			return 'grid-widget-title-' + this.widget.key
		},
		label() {
			return this.widget.title || this.sourceTitle || this.typeLabel
		},
		widgetComponent() {
			return getWidgetComponent(this.widget.type)
		},
		// A data widget shows a table or view of the application, the others their own settings
		componentProps() {
			if (this.widget.type === WIDGET_TYPE_DATA) {
				return { target: widgetTarget(this.widget, this.nodes), editable: this.editable, showTitle: !this.sourceTitle }
			}
			if (this.widget.type === WIDGET_TYPE_TABLES) {
				return { nodes: this.nodes, content: this.startPageContent }
			}
			if (this.widget.type === WIDGET_TYPE_TABS) {
				return { widget: this.widget, editable: this.editable }
			}
			return { settings: this.widget.settings, editable: this.editable }
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
			// In a right-to-left language the grid is mirrored, so left is towards the higher columns
			const left = isRTL() ? 1 : -1
			const atStart = item?.gridX <= 0
			const atEnd = item?.gridX + item?.gridWidth >= GRID_COLUMNS
			return [
				{ key: 'MoveLeft', icon: 'ArrowLeft', label: t('tables', 'Move left'), change: { gridX: left }, disabled: left < 0 ? atStart : atEnd },
				{ key: 'MoveRight', icon: 'ArrowRight', label: t('tables', 'Move right'), change: { gridX: -left }, disabled: left < 0 ? atEnd : atStart },
				{ key: 'MoveUp', icon: 'ArrowUp', label: t('tables', 'Move up'), change: { gridY: -1 }, disabled: item?.gridY <= 0 },
				{ key: 'MoveDown', icon: 'ArrowDown', label: t('tables', 'Move down'), change: { gridY: 1 }, disabled: false },
				{ key: 'Wider', icon: 'ArrowExpandHorizontal', label: t('tables', 'Make wider'), change: { gridWidth: 1 }, disabled: item?.gridWidth >= GRID_COLUMNS },
				{ key: 'Narrower', icon: 'ArrowCollapseHorizontal', label: t('tables', 'Make narrower'), change: { gridWidth: -1 }, disabled: item?.gridWidth <= GRID_MIN_WIDTH },
				{ key: 'Taller', icon: 'ArrowExpandVertical', label: t('tables', 'Make taller'), change: { gridHeight: 1 }, disabled: false },
				{ key: 'Shorter', icon: 'ArrowCollapseVertical', label: t('tables', 'Make shorter'), change: { gridHeight: -1 }, disabled: item?.gridHeight <= 1 },
			]
		},
	},
	watch: {
		layoutItem(item) {
			if (!this.movedStep || !item) {
				return
			}
			const step = this.movedStep
			this.movedStep = null
			this.announcement = t('tables', 'Column {column}, row {row}, {width} columns wide and {height} rows high', {
				column: item.gridX + 1,
				row: item.gridY + 1,
				width: item.gridWidth,
				height: item.gridHeight,
			})
			this.$nextTick(() => this.focusStep(step))
		},
	},
	created() {
		// The type titles come from the server, loaded the first time a grid shows a widget
		this.loadWidgetTypes()
	},
	methods: {
		...mapActions(useTablesStore, ['loadWidgetTypes']),
		/**
		 * Asks the page to move or resize the widget by one step of the menu.
		 *
		 * @param {{key: string, change: object}} step the step
		 */
		move(step) {
			this.movedStep = step.key
			this.$emit('move', this.widget, step.change)
		},
		/**
		 * Keeps focus on a step of the open menu, so pressing Enter again repeats it. The menu
		 * focuses its first action after it renders unless an action is marked active, as its own
		 * keyboard navigation does, so the step is marked and becomes the start of that navigation.
		 *
		 * @param {string} key the key of the step
		 */
		focusStep(key) {
			const button = this.$refs['step' + key]?.[0]?.$el?.querySelector('button')
			const actions = this.$refs.actions
			if (!button || !actions?.$refs.menu) {
				return
			}
			actions.$refs.menu.querySelector('li.active')?.classList.remove('active')
			button.closest('li.action')?.classList.add('active')
			actions.focusIndex = [...actions.getFocusableMenuItemElements()].indexOf(button)
			button.focus()
		},
	},
}
</script>

<style lang="scss" scoped>
.grid-widget {
	position: relative;
	display: flex;
	flex-direction: column;
	height: 100%;
	min-height: 0;

	&__header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: calc(2 * var(--default-grid-baseline));
		// Fits the edit menu button, so the header keeps its height in view and edit mode
		min-height: calc(var(--default-clickable-area) + 2 * var(--default-grid-baseline));
		padding: 0 calc(3 * var(--default-grid-baseline));
		border-bottom: 1px solid var(--color-border);

		&--floating {
			position: absolute;
			z-index: 1;
			inset-block-start: 0;
			inset-inline-end: 0;
			min-height: 0;
			padding: var(--default-grid-baseline);
			border-bottom: none;
		}
	}

	&__title {
		display: flex;
		align-items: center;
		gap: var(--default-grid-baseline);
		min-width: 0;
		margin: 0;
		font-size: var(--default-font-size);
		font-weight: bold;
	}

	&__title-text {
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	&__description {
		margin: 0;
		padding: var(--default-grid-baseline) calc(3 * var(--default-grid-baseline));
		color: var(--color-text-maxcontrast);
		white-space: pre-wrap;
	}

	&__body {
		flex: 1;
		min-height: 0;
		overflow: auto;
		// Keeps the sticky bars of a table below the floating menu of the widget
		isolation: isolate;
	}

	&__unknown {
		padding: calc(3 * var(--default-grid-baseline));
		color: var(--color-text-maxcontrast);
	}
}
</style>
