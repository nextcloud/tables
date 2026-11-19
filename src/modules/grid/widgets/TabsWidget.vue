<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="tabs-widget" data-cy="tabsWidget">
		<div ref="tablist"
			role="tablist"
			class="tabs-widget__tabs"
			:aria-label="widget.title || t('tables', 'Tabs')"
			@keydown="onKeydown"
			@focusout="onFocusout">
			<button v-for="(title, index) in titles"
				:id="tabId(index)"
				:key="index"
				type="button"
				role="tab"
				class="tabs-widget__tab"
				:class="{ 'tabs-widget__tab--active': index === activeTab }"
				:aria-selected="index === activeTab ? 'true' : 'false'"
				:aria-controls="visitedTabs.includes(index) ? panelId(index) : undefined"
				:tabindex="index === focusTab ? 0 : -1"
				data-cy="tabsWidgetTab"
				@click="openTab(index)">
				{{ title }}
			</button>
		</div>
		<div v-for="index in visitedTabs"
			v-show="index === activeTab"
			:id="panelId(index)"
			:key="index"
			role="tabpanel"
			class="tabs-widget__panel"
			tabindex="0"
			:aria-labelledby="tabId(index)">
			<p v-if="isEmpty(index)" class="tabs-widget__empty" data-cy="tabsWidgetEmpty">
				{{ editable ? t('tables', 'Add widgets to this tab.') : t('tables', 'This tab is empty.') }}
			</p>
			<GridLevel v-if="editable || !isEmpty(index)" :parent-key="widget.key" :tab="index" />
			<NcButton v-if="editable"
				variant="secondary"
				class="tabs-widget__add"
				data-cy="tabsWidgetAdd"
				@click="grid.addToTab(widget, index)">
				<template #icon>
					<Plus :size="20" />
				</template>
				{{ t('tables', 'Add widget to this tab') }}
			</NcButton>
		</div>
	</div>
</template>

<script>
import { computed, defineAsyncComponent } from 'vue'
import { NcButton } from '@nextcloud/vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import { tabTitles } from '../gridLayout.js'
import { eventTabIndex, rovingTabTarget } from '../../../shared/utils/rovingTabs.js'

/**
 * A tab strip whose tabs each hold their own grid of widgets. The widgets live in the grid of
 * the page, with the key of this widget as their parent; the page handles every change to them.
 */
export default {
	name: 'TabsWidget',
	components: {
		// Loaded later: the widgets of a tab are GridWidgets, which render this widget too
		GridLevel: defineAsyncComponent(() => import('../GridLevel.vue')),
		NcButton,
		Plus,
	},
	inject: {
		// The grid of the page and its actions, provided by the page
		grid: { from: 'gridLevel' },
		widgetHeadingLevel: { default: 2 },
	},
	provide() {
		return {
			// Follows the level of the tabs widget, which changes when it gets or loses a title
			gridHeadingLevel: computed(() => this.widgetHeadingLevel),
		}
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
	data() {
		return {
			activeTab: 0,
			focusedTab: null,
			// A tab is mounted when it is first opened and kept, so its widgets keep their state
			visitedTabs: [0],
		}
	},
	computed: {
		titles() {
			return tabTitles(this.widget.settings)
		},
		// The tab in the tab order: the one last moved to with the keyboard, else the open one
		focusTab() {
			return Math.min(this.focusedTab ?? this.activeTab, this.titles.length - 1)
		},
	},
	watch: {
		titles(titles) {
			if (this.activeTab >= titles.length) {
				this.activeTab = Math.max(0, titles.length - 1)
			}
			this.visitedTabs = this.visitedTabs.filter(index => index < Math.max(1, titles.length))
			this.focusedTab = null
		},
		activeTab(index) {
			this.focusedTab = null
			if (!this.visitedTabs.includes(index)) {
				this.visitedTabs = [...this.visitedTabs, index]
			}
		},
	},
	methods: {
		/**
		 * @param {number} index position of a tab
		 * @return {string} the id of its tab button
		 */
		tabId(index) {
			return 'tabs-widget-' + this.widget.key + '-tab-' + index
		},
		/**
		 * @param {number} index position of a tab
		 * @return {boolean} whether the tab shows no widgets
		 */
		isEmpty(index) {
			return this.grid.shownLayout(this.widget.key, index).length === 0
		},
		/**
		 * @param {number} index position of a tab
		 * @return {string} the id of its panel
		 */
		panelId(index) {
			return 'tabs-widget-' + this.widget.key + '-panel-' + index
		},
		/**
		 * @param {number} index position of the tab to open
		 */
		openTab(index) {
			this.activeTab = index
			this.focusedTab = null
		},
		/**
		 * Puts the open tab back in the tab order once the focus leaves the tab list.
		 *
		 * @param {FocusEvent} event the focus that left a tab
		 */
		onFocusout(event) {
			if (!this.$refs.tablist.contains(event.relatedTarget)) {
				this.focusedTab = null
			}
		},
		/**
		 * Moves the focus between tabs with the arrow keys, Home and End.
		 *
		 * @param {KeyboardEvent} event the key that was pressed
		 */
		onKeydown(event) {
			const isRtl = getComputedStyle(this.$refs.tablist).direction === 'rtl'
			const target = rovingTabTarget(event, eventTabIndex(event), this.titles.length, isRtl)
			if (target === null) {
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
