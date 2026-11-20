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
			<button v-for="(tab, index) in tabs"
				:id="tabId(tab.key)"
				:key="tab.key"
				type="button"
				role="tab"
				class="tabs-widget__tab"
				:class="{ 'tabs-widget__tab--active': tab.key === activeKey }"
				:aria-selected="tab.key === activeKey ? 'true' : 'false'"
				:aria-controls="visitedKeys.includes(tab.key) ? panelId(tab.key) : undefined"
				:tabindex="index === focusTab ? 0 : -1"
				data-cy="tabsWidgetTab"
				@click="openTab(tab.key)">
				{{ tab.title }}
			</button>
		</div>
		<div v-for="tab in visitedTabs"
			v-show="tab.key === activeKey"
			:id="panelId(tab.key)"
			:key="tab.key"
			role="tabpanel"
			class="tabs-widget__panel"
			tabindex="0"
			:aria-labelledby="tabId(tab.key)">
			<p v-if="isEmpty(tab.key)" class="tabs-widget__empty" data-cy="tabsWidgetEmpty">
				{{ editable ? t('tables', 'Add widgets to this tab.') : t('tables', 'This tab is empty.') }}
			</p>
			<GridLevel v-if="editable || !isEmpty(tab.key)" :parent-key="widget.key" :tab="tab.key" />
			<NcButton v-if="editable"
				variant="secondary"
				class="tabs-widget__add"
				data-cy="tabsWidgetAdd"
				@click="grid.addToTab(widget, tab.key)">
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
import { widgetTabs } from '../gridLayout.js'
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
			openedKey: null,
			focusedTab: null,
			// A tab is mounted when it is first opened and kept, so its widgets keep their state
			visitedKeys: [],
		}
	},
	computed: {
		tabs() {
			return widgetTabs(this.widget.settings)
		},
		// The opened tab while it exists, else the first one
		activeKey() {
			return this.tabs.some(tab => tab.key === this.openedKey) ? this.openedKey : (this.tabs[0]?.key ?? null)
		},
		visitedTabs() {
			return this.tabs.filter(tab => this.visitedKeys.includes(tab.key))
		},
		// Position of the tab in the tab order: the one last moved to with the keyboard, else the open one
		focusTab() {
			const active = this.tabs.findIndex(tab => tab.key === this.activeKey)
			return Math.min(this.focusedTab ?? active, this.tabs.length - 1)
		},
	},
	watch: {
		tabs() {
			this.focusedTab = null
		},
		activeKey: {
			handler(key) {
				this.focusedTab = null
				if (key !== null && !this.visitedKeys.includes(key)) {
					this.visitedKeys = [...this.visitedKeys, key]
				}
			},
			immediate: true,
		},
	},
	methods: {
		/**
		 * @param {string} key key of a tab
		 * @return {string} the id of its tab button
		 */
		tabId(key) {
			return 'tabs-widget-' + this.widget.key + '-tab-' + key
		},
		/**
		 * @param {string} key key of a tab
		 * @return {boolean} whether the tab shows no widgets
		 */
		isEmpty(key) {
			return this.grid.shownLayout(this.widget.key, key).length === 0
		},
		/**
		 * @param {string} key key of a tab
		 * @return {string} the id of its panel
		 */
		panelId(key) {
			return 'tabs-widget-' + this.widget.key + '-panel-' + key
		},
		/**
		 * @param {string} key key of the tab to open
		 */
		openTab(key) {
			this.openedKey = key
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
			const target = rovingTabTarget(event, eventTabIndex(event), this.tabs.length, isRtl)
			if (target === null) {
				return
			}
			event.preventDefault()
			this.focusedTab = target
			this.$nextTick(() => document.getElementById(this.tabId(this.tabs[target].key))?.focus())
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
