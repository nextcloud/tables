<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<fieldset class="tabs-editor" data-cy="tabsEditor">
		<legend class="tabs-editor__legend">
			{{ label }}<span v-if="required" aria-hidden="true"> *</span>
		</legend>
		<ol v-if="modelValue.length > 0" class="tabs-editor__list">
			<li v-for="(tab, index) in modelValue"
				:key="tab.key"
				:data-key="tab.key"
				class="tabs-editor__tab"
				data-cy="tabsEditorTab">
				<NcTextField :model-value="tab.title"
					class="tabs-editor__title"
					:label="t('tables', 'Title of tab {number}', { number: index + 1 })"
					:maxlength="titleMaxLength"
					:required="true"
					:error="showsBlank(tab)"
					:helper-text="showsBlank(tab) ? t('tables', 'Give this tab a title.') : ''"
					data-cy="tabsEditorTitle"
					@update:model-value="title => rename(index, title)"
					@blur="touch(tab.key)" />
				<NcButton variant="tertiary"
					:aria-label="t('tables', 'Move tab {title} up', { title: tabName(index) }, undefined, textOptions)"
					:disabled="index === 0"
					data-action="up"
					data-cy="tabsEditorUp"
					@click="move(index, -1)">
					<template #icon>
						<ArrowUp :size="20" />
					</template>
				</NcButton>
				<NcButton variant="tertiary"
					:aria-label="t('tables', 'Move tab {title} down', { title: tabName(index) }, undefined, textOptions)"
					:disabled="index === modelValue.length - 1"
					data-action="down"
					data-cy="tabsEditorDown"
					@click="move(index, 1)">
					<template #icon>
						<ArrowDown :size="20" />
					</template>
				</NcButton>
				<NcButton variant="tertiary"
					:aria-label="t('tables', 'Remove tab {title}', { title: tabName(index) }, undefined, textOptions)"
					data-cy="tabsEditorRemove"
					@click="remove(index)">
					<template #icon>
						<TrashCanOutline :size="20" />
					</template>
				</NcButton>
			</li>
		</ol>
		<NcButton ref="addButton"
			:disabled="modelValue.length >= maxItems"
			:aria-describedby="hintId"
			data-cy="tabsEditorAdd"
			@click="add">
			<template #icon>
				<Plus :size="20" />
			</template>
			{{ t('tables', 'Add tab') }}
		</NcButton>
		<p :id="hintId" class="tabs-editor__hint" data-cy="tabsEditorHint">
			{{ t('tables', '{count} of at most {max} tabs. Widgets in a removed tab move onto the page.', { count: modelValue.length, max: maxItems }) }}
		</p>
		<p class="hidden-visually" aria-live="polite" data-cy="tabsEditorAnnouncement">
			{{ announcement }}
		</p>
	</fieldset>
</template>

<script>
import { useId } from 'vue'
import { NcButton, NcTextField } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'
import ArrowDown from 'vue-material-design-icons/ArrowDown.vue'
import ArrowUp from 'vue-material-design-icons/ArrowUp.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import { TITLE_MAX_LENGTH, isBlank, newTabKey, trimText } from './gridLayout.js'

/**
 * Edits the tabs of a tabs widget: add, rename, move and remove tabs. Each tab keeps its key,
 * so the widgets in it stay there when it is renamed or moved; a new tab gets its key here.
 */
export default {
	name: 'TabsEditor',
	components: {
		ArrowDown,
		ArrowUp,
		NcButton,
		NcTextField,
		Plus,
		TrashCanOutline,
	},
	props: {
		modelValue: {
			type: Array,
			required: true,
		},
		label: {
			type: String,
			required: true,
		},
		required: {
			type: Boolean,
			default: false,
		},
		// As GridWidgetTypes::MAX_TABS on the server
		maxItems: {
			type: Number,
			default: 20,
		},
	},
	emits: ['update:modelValue'],
	setup() {
		return { hintId: 'tabs-editor-hint-' + useId() }
	},
	data() {
		return {
			announcement: '',
			// Keys of the tabs whose title field was left, so a new tab is not marked before it is typed in
			touchedKeys: [],
		}
	},
	computed: {
		titleMaxLength() {
			return TITLE_MAX_LENGTH
		},
		// The titles go into the labels and the announcement as text
		textOptions() {
			return { escape: false, sanitize: false }
		},
	},
	methods: {
		/**
		 * @param {number} index position of a tab
		 * @return {string} its title, or its number while it has none
		 */
		tabName(index) {
			return trimText(this.modelValue[index]?.title) || String(index + 1)
		},
		/**
		 * @param {{key: string, title: string}} tab a tab
		 * @return {boolean} whether its field shows that it needs a title
		 */
		showsBlank(tab) {
			return this.touchedKeys.includes(tab.key) && isBlank(tab.title)
		},
		/**
		 * @param {string} key key of the tab whose title field was left
		 */
		touch(key) {
			if (!this.touchedKeys.includes(key)) {
				this.touchedKeys = [...this.touchedKeys, key]
			}
		},
		/**
		 * @param {Array<{key: string, title: string}>} tabs the new tabs
		 */
		emitTabs(tabs) {
			this.$emit('update:modelValue', tabs)
		},
		/**
		 * @param {number} index position of the tab
		 * @param {string} title its new title
		 */
		rename(index, title) {
			this.emitTabs(this.modelValue.map((tab, position) => position === index ? { ...tab, title } : tab))
		},
		/**
		 * Adds an empty tab at the end and moves the focus to its title.
		 */
		add() {
			if (this.modelValue.length >= this.maxItems) {
				return
			}
			const key = newTabKey(this.modelValue)
			this.emitTabs([...this.modelValue, { key, title: '' }])
			this.focusInTab(key, 'input')
		},
		/**
		 * Moves a tab one place up or down. The focus stays on the button, or goes to the other
		 * one when the tab reached the end of the list.
		 *
		 * @param {number} index position of the tab
		 * @param {number} step -1 to move it up, 1 to move it down
		 */
		move(index, step) {
			const target = index + step
			if (target < 0 || target >= this.modelValue.length) {
				return
			}
			const tabs = [...this.modelValue]
			const [tab] = tabs.splice(index, 1)
			tabs.splice(target, 0, tab)
			const title = this.tabName(index)
			this.emitTabs(tabs)
			const atEnd = step < 0 ? target === 0 : target === tabs.length - 1
			const action = (step < 0) !== atEnd ? 'up' : 'down'
			this.focusInTab(tab.key, `[data-action="${action}"]`)
			this.announcement = t('tables', 'Tab {title} moved to position {position} of {count}.', { title, position: target + 1, count: tabs.length }, undefined, this.textOptions)
		},
		/**
		 * Removes a tab and moves the focus to the title of the tab that takes its place, else
		 * to the button that adds a tab.
		 *
		 * @param {number} index position of the tab
		 */
		remove(index) {
			const title = this.tabName(index)
			const tabs = this.modelValue.filter((tab, position) => position !== index)
			this.emitTabs(tabs)
			const next = tabs[index] ?? tabs[index - 1]
			if (next) {
				this.focusInTab(next.key, 'input')
			} else {
				this.$nextTick(() => this.$refs.addButton?.$el?.focus())
			}
			this.announcement = t('tables', 'Tab {title} removed.', { title }, undefined, this.textOptions)
		},
		/**
		 * Focuses an element in the row of a tab once the list shows the new tabs.
		 *
		 * @param {string} key key of the tab
		 * @param {string} selector the element in its row
		 */
		focusInTab(key, selector) {
			this.$nextTick(() => this.$el.querySelector(`[data-key="${CSS.escape(key)}"] ${selector}`)?.focus())
		},
	},
}
</script>

<style lang="scss" scoped>
.tabs-editor {
	&__legend {
		font-weight: bold;
	}

	&__list {
		margin: var(--default-grid-baseline) 0 calc(2 * var(--default-grid-baseline));
		padding: 0;
		list-style: none;
	}

	&__tab {
		display: flex;
		align-items: flex-start;
		gap: var(--default-grid-baseline);
		margin-top: calc(2 * var(--default-grid-baseline));
	}

	&__title {
		flex: 1;
	}

	&__hint {
		color: var(--color-text-maxcontrast);
	}
}
</style>
