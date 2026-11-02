<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="tabs-widget" data-cy="tabsWidget">
		<p v-if="titles.length === 0" class="tabs-widget__empty">
			{{ t('tables', 'Name the tabs in the widget settings') }}
		</p>
		<template v-else>
			<div ref="tablist"
				role="tablist"
				class="tabs-widget__tabs"
				:aria-label="t('tables', 'Tabs')"
				@keydown="onKeydown">
				<button v-for="(title, index) in titles"
					:id="tabId(index)"
					:key="index"
					type="button"
					role="tab"
					class="tabs-widget__tab"
					:class="{ 'tabs-widget__tab--active': index === activeTab }"
					:aria-selected="index === activeTab ? 'true' : 'false'"
					:aria-controls="panelId"
					:tabindex="index === focusTab ? 0 : -1"
					data-cy="tabsWidgetTab"
					@click="activeTab = index">
					{{ title }}
				</button>
			</div>
			<div :id="panelId"
				role="tabpanel"
				class="tabs-widget__panel"
				:aria-labelledby="tabId(activeTab)">
				<GridCanvas :key="activeTab"
					:layout="layout"
					:editable="editable"
					@layout-change="items => actions.changeLayout(items)">
					<template #widget="{ item, narrow }">
						<GridWidget v-if="widgetsByKey[item.widgetId]"
							:widget="widgetsByKey[item.widgetId]"
							:nodes="actions.nodes()"
							:layout-item="item"
							:editable="editable"
							:arrangeable="!narrow"
							@configure="actions.configure"
							@remove="actions.remove"
							@move="actions.move" />
					</template>
				</GridCanvas>
				<NcButton v-if="editable"
					variant="secondary"
					class="tabs-widget__add"
					data-cy="tabsWidgetAdd"
					@click="actions.addToTab(widget, activeTab)">
					<template #icon>
						<Plus :size="20" />
					</template>
					{{ t('tables', 'Add widget to this tab') }}
				</NcButton>
			</div>
		</template>
	</div>
</template>

<script>
import { defineAsyncComponent } from 'vue'
import { NcButton } from '@nextcloud/vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import GridCanvas from '../GridCanvas.vue'
import { levelLayout, tabTitles } from '../gridLayout.js'

let instances = 0

/**
 * A tab strip whose tabs each hold their own grid of widgets. The widgets live in the grid of
 * the page, with the key of this widget as their parent; the page handles every change to them.
 */
export default {
	name: 'TabsWidget',
	components: {
		// Loaded later: a widget in a tab is a GridWidget, which renders this widget too
		GridWidget: defineAsyncComponent(() => import('../GridWidget.vue')),
		GridCanvas,
		NcButton,
		Plus,
	},
	inject: {
		// The grid of the page and its actions, provided by the page
		actions: { from: 'gridActions' },
		widgetHeadingLevel: { default: 2 },
	},
	provide() {
		return {
			gridHeadingLevel: this.widgetHeadingLevel,
		}
	},
	props: {
		widget: {
			type: Object,
			required: true,
		},
		settings: {
			type: Object,
			required: true,
		},
		editable: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			activeTab: 0,
			focusedTab: null,
			instance: ++instances,
		}
	},
	computed: {
		titles() {
			return tabTitles(this.settings)
		},
		panelId() {
			return 'tabs-widget-panel-' + this.instance
		},
		layout() {
			return levelLayout(this.actions.grid(), this.widget.key, this.activeTab)
		},
		widgetsByKey() {
			return Object.fromEntries(this.actions.grid().widgets.map(widget => [widget.key, widget]))
		},
		// The tab in the tab order: the one last moved to with the keyboard, else the open one
		focusTab() {
			return this.focusedTab ?? this.activeTab
		},
	},
	watch: {
		titles(titles) {
			if (this.activeTab >= titles.length) {
				this.activeTab = Math.max(0, titles.length - 1)
			}
		},
		activeTab() {
			this.focusedTab = null
		},
	},
	methods: {
		/**
		 * @param {number} index position of a tab
		 * @return {string} the id of its tab button
		 */
		tabId(index) {
			return 'tabs-widget-' + this.instance + '-tab-' + index
		},
		/**
		 * Moves the focus between tabs with the arrow keys, Home and End.
		 *
		 * @param {KeyboardEvent} event the key that was pressed
		 */
		onKeydown(event) {
			const count = this.titles.length
			const isRtl = getComputedStyle(this.$refs.tablist).direction === 'rtl'
			const step = { ArrowRight: isRtl ? -1 : 1, ArrowLeft: isRtl ? 1 : -1 }
			let target
			if (event.key in step) {
				target = (this.focusTab + step[event.key] + count) % count
			} else if (event.key === 'Home') {
				target = 0
			} else if (event.key === 'End') {
				target = count - 1
			} else {
				return
			}
			event.preventDefault()
			this.focusedTab = target
			this.$nextTick(() => document.getElementById(this.tabId(target))?.focus())
		},
	},
}
</script>

<style lang="scss" scoped>
.tabs-widget {
	display: flex;
	flex-direction: column;
	height: 100%;

	&__empty {
		padding: calc(3 * var(--default-grid-baseline));
		color: var(--color-text-maxcontrast);
	}

	&__tabs {
		display: flex;
		flex-wrap: wrap;
		gap: var(--default-grid-baseline);
		padding-inline: calc(2 * var(--default-grid-baseline));
		border-bottom: 1px solid var(--color-border);
	}

	&__tab {
		min-height: var(--default-clickable-area);
		margin: 0;
		padding: 0 calc(3 * var(--default-grid-baseline));
		border: none;
		border-bottom: 2px solid transparent;
		border-radius: 0;
		background: none;
		color: var(--color-text-maxcontrast);
		font-weight: normal;
		cursor: pointer;

		&:hover,
		&:focus-visible {
			color: var(--color-main-text);
		}

		&:focus-visible {
			outline: 2px solid var(--color-main-text);
			outline-offset: -2px;
		}

		&--active {
			border-bottom-color: var(--color-primary-element);
			color: var(--color-main-text);
			font-weight: bold;
		}
	}

	&__panel {
		flex: 1;
		min-height: 0;
		overflow: auto;
		padding: calc(2 * var(--default-grid-baseline));
	}

	&__add {
		margin-top: calc(2 * var(--default-grid-baseline));
	}
}
</style>
