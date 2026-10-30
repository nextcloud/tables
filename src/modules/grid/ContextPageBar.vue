<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="context-page-bar" data-cy="contextPageBar">
		<div ref="tablist"
			role="tablist"
			class="context-page-bar__tabs"
			:aria-label="t('tables', 'Pages of this application')"
			@keydown="onKeydown">
			<button v-for="page in pages"
				:id="tabId(page)"
				:key="page.id"
				type="button"
				role="tab"
				class="context-page-bar__tab"
				:class="{ 'context-page-bar__tab--active': page.id === selectedPageId }"
				:aria-selected="page.id === selectedPageId ? 'true' : 'false'"
				:aria-controls="panelId"
				:tabindex="page.id === focusPageId ? 0 : -1"
				:data-cy="page.page_type === PAGE_TYPE_START ? 'contextStartPageLink' : 'contextPageLink'"
				@click="$emit('select', page)">
				<NamedIcon v-if="page.icon" :name="page.icon" :size="20" />
				<span>{{ pageTitle(page) }}</span>
			</button>
		</div>
		<NcButton v-if="canManage" variant="tertiary" data-cy="contextPageAdd" @click="$emit('add-page')">
			<template #icon>
				<Plus :size="20" />
			</template>
			{{ t('tables', 'Add page') }}
		</NcButton>
	</div>
</template>

<script>
import { NcButton } from '@nextcloud/vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import NamedIcon from '../../shared/components/ncIconPicker/NamedIcon.vue'
import { PAGE_TYPE_START, contextPages, pageTitle } from './gridLayout.js'

/**
 * The pages of an application as tabs: the start page first, then its grid pages. Follows the
 * tabs pattern: one tab stop, the arrow keys, Home and End move between tabs, Enter or Space opens one.
 */
export default {
	name: 'ContextPageBar',
	components: {
		NamedIcon,
		NcButton,
		Plus,
	},
	props: {
		context: {
			type: Object,
			required: true,
		},
		// The page that is shown
		selectedPageId: {
			type: Number,
			default: null,
		},
		// The id of the element that shows the selected page
		panelId: {
			type: String,
			required: true,
		},
		canManage: {
			type: Boolean,
			default: false,
		},
	},
	emits: ['select', 'add-page'],
	data() {
		return {
			PAGE_TYPE_START,
			focusedPageId: null,
		}
	},
	computed: {
		pages() {
			return contextPages(this.context)
		},
		// The tab in the tab order: the one last moved to with the keyboard, else the selected one
		focusPageId() {
			const ids = this.pages.map(page => page.id)
			if (ids.includes(this.focusedPageId)) {
				return this.focusedPageId
			}
			return ids.includes(this.selectedPageId) ? this.selectedPageId : (ids[0] ?? null)
		},
	},
	watch: {
		selectedPageId() {
			this.focusedPageId = null
		},
	},
	methods: {
		pageTitle,
		/**
		 * @param {object} page a page of the application
		 * @return {string} the id of its tab
		 */
		tabId(page) {
			return 'context-page-tab-' + page.id
		},
		/**
		 * Moves the focus between tabs with the arrow keys, Home and End.
		 *
		 * @param {KeyboardEvent} event the key that was pressed
		 */
		onKeydown(event) {
			const ids = this.pages.map(page => page.id)
			const index = ids.indexOf(this.focusPageId)
			const isRtl = getComputedStyle(this.$refs.tablist).direction === 'rtl'
			const next = { ArrowRight: isRtl ? -1 : 1, ArrowLeft: isRtl ? 1 : -1 }
			let target
			if (event.key in next) {
				target = (index + next[event.key] + ids.length) % ids.length
			} else if (event.key === 'Home') {
				target = 0
			} else if (event.key === 'End') {
				target = ids.length - 1
			} else {
				return
			}
			event.preventDefault()
			this.focusedPageId = ids[target]
			this.$nextTick(() => document.getElementById(this.tabId({ id: ids[target] }))?.focus())
		},
	},
}
</script>

<style lang="scss" scoped>
.context-page-bar {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: var(--default-grid-baseline);
	margin-inline-start: 32px;
	border-bottom: 1px solid var(--color-border);

	&__tabs {
		display: flex;
		flex-wrap: wrap;
		gap: var(--default-grid-baseline);
	}

	&__tab {
		display: inline-flex;
		align-items: center;
		gap: var(--default-grid-baseline);
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
}
</style>
