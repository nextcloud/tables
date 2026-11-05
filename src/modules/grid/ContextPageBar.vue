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
				<span class="context-page-bar__tab-icon" aria-hidden="true">
					<NamedIcon v-if="page.icon" :name="page.icon" :size="20" />
					<HomeOutline v-else-if="page.page_type === PAGE_TYPE_START" :size="20" />
					<ViewDashboardOutline v-else :size="20" />
				</span>
				<span class="context-page-bar__tab-title">{{ pageTitle(page) }}</span>
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
import HomeOutline from 'vue-material-design-icons/HomeOutline.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import ViewDashboardOutline from 'vue-material-design-icons/ViewDashboardOutline.vue'
import NamedIcon from '../../shared/components/ncIconPicker/NamedIcon.vue'
import { PAGE_TYPE_START, contextPages, pageTitle } from './gridLayout.js'

/**
 * The pages of an application as tabs that look like the tabs of NcAppSidebar: the start page
 * first, then its grid pages. Follows the
 * tabs pattern: one tab stop, the arrow keys, Home and End move between tabs, Enter or Space opens one.
 */
export default {
	name: 'ContextPageBar',
	components: {
		HomeOutline,
		NamedIcon,
		NcButton,
		Plus,
		ViewDashboardOutline,
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
		align-items: stretch;
		gap: var(--default-grid-baseline);
	}

	&__tab-icon {
		display: flex;
		align-items: center;
		justify-content: center;
		height: 20px;
		overflow: hidden;
	}

	// Looks like the tabs of NcAppSidebar
	&__tab {
		position: relative;
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: var(--default-grid-baseline);
		min-width: var(--default-clickable-area);
		margin: 0 0 var(--default-grid-baseline);
		padding: var(--default-grid-baseline) calc(4 * var(--default-grid-baseline)) calc(2 * var(--default-grid-baseline));
		border: none;
		border-radius: var(--border-radius-element);
		background-color: var(--color-main-background);
		color: var(--color-main-text);
		font-size: var(--default-font-size);
		font-weight: normal;
		cursor: pointer;
		transition: background-color var(--animation-quick);

		&::after {
			content: '';
			position: absolute;
			inset-block-end: 0;
			inset-inline: 0;
			width: 0;
			margin-inline: auto;
			height: 4px;
			border-radius: 999px;
			background-color: var(--color-primary-element);
			opacity: 0;
			transition: width var(--animation-quick), opacity var(--animation-quick);
		}

		&:hover {
			background-color: var(--color-background-hover);
		}

		&:focus-visible {
			outline: 2px solid var(--color-main-text);
			outline-offset: 2px;
		}

		&--active {
			background-color: color-mix(in srgb, var(--color-primary-element) 8%, transparent);
			cursor: default;

			&:hover {
				background-color: color-mix(in srgb, var(--color-primary-element) 8%, transparent);
			}

			&::after {
				width: 80%;
				opacity: 1;
			}

			.context-page-bar__tab-title {
				font-weight: var(--font-weight-element, bold);
			}
		}
	}
}
</style>
