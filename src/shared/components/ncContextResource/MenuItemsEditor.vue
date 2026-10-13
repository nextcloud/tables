<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="menu-items-editor" data-cy="menuItemsEditor">
		<p class="menu-items-editor__help">
			{{ t('tables', 'Drag an entry or use its move buttons to change the order. An entry can sit under the entry above it, one level deep.') }}
		</p>
		<ul v-if="items.length > 0" class="menu-items-editor__list">
			<li v-for="(item, index) in items"
				:key="item.key"
				class="menu-items-editor__item"
				:class="{ 'menu-items-editor__item--child': item.parentKey !== null, 'menu-items-editor__item--dragging': draggedIndex === index }"
				:aria-label="itemName(item)"
				:draggable="handleIndex === index"
				data-cy="menuItemRow"
				@dragstart="dragStart(index, $event)"
				@dragover.prevent="dragOver(index)"
				@dragend="dragEnd">
				<span class="menu-items-editor__handle"
					aria-hidden="true"
					:title="t('tables', 'Drag to move')"
					@pointerdown="holdHandle(index)">
					<DragHorizontalVariant :size="20" />
				</span>
				<NcIconPicker :close-on-select="true" @select="iconName => update(index, { icon: iconName })">
					<NcButton variant="tertiary"
						:aria-label="t('tables', 'Choose an icon for {label}', { label: itemName(item) }, undefined, { escape: false })"
						:title="t('tables', 'Choose an icon')"
						data-cy="menuItemIcon">
						<template #icon>
							<ApplicationIcon v-if="item.icon" :icon-name="item.icon" :size="20" />
							<DotsHorizontalCircleOutline v-else :size="20" />
						</template>
					</NcButton>
				</NcIconPicker>
				<NcTextField :model-value="item.label"
					class="menu-items-editor__field"
					:label="t('tables', 'Label')"
					:error="shownErrors[index].label"
					:helper-text="shownErrors[index].label ? t('tables', 'Give the entry a label') : ''"
					data-cy="menuItemLabel"
					@blur="touch(item)"
					@update:model-value="value => update(index, { label: value })" />
				<NcSelect :model-value="targetOption(item)"
					class="menu-items-editor__target"
					:input-label="t('tables', 'Opens')"
					:options="targetOptions"
					:clearable="false"
					:searchable="true"
					:error="shownErrors[index].target"
					:helper-text="shownErrors[index].target ? t('tables', 'Choose what the entry opens') : ''"
					label="label"
					data-cy="menuItemTarget"
					@update:model-value="option => setTarget(index, option)" />
				<NcTextField v-if="item.targetType === 'url'"
					class="menu-items-editor__field"
					:model-value="item.url ?? ''"
					:label="t('tables', 'Link')"
					:error="shownErrors[index].url"
					:helper-text="shownErrors[index].url ? t('tables', 'Enter an address that starts with http:// or https://') : ''"
					placeholder="https://"
					data-cy="menuItemLink"
					@blur="touch(item)"
					@update:model-value="value => update(index, { url: value })" />
				<div class="menu-items-editor__row-actions">
					<NcButton :disabled="!canMove(items, index, -1)"
						:aria-label="t('tables', 'Move {label} up', { label: itemName(item) }, undefined, { escape: false })"
						:title="t('tables', 'Move up')"
						variant="tertiary"
						data-cy="menuItemMoveUp"
						@click="emitItems(moveItem(items, index, -1))">
						<template #icon>
							<ArrowUp :size="20" />
						</template>
					</NcButton>
					<NcButton :disabled="!canMove(items, index, 1)"
						:aria-label="t('tables', 'Move {label} down', { label: itemName(item) }, undefined, { escape: false })"
						:title="t('tables', 'Move down')"
						variant="tertiary"
						data-cy="menuItemMoveDown"
						@click="emitItems(moveItem(items, index, 1))">
						<template #icon>
							<ArrowDown :size="20" />
						</template>
					</NcButton>
					<NcButton v-if="item.parentKey === null"
						:disabled="!canIndent(items, index)"
						:aria-label="t('tables', 'Move {label} under the entry above', { label: itemName(item) }, undefined, { escape: false })"
						:title="t('tables', 'Move under the entry above')"
						variant="tertiary"
						data-cy="menuItemIndent"
						@click="emitItems(indentItem(items, index))">
						<template #icon>
							<FormatIndentIncrease :size="20" />
						</template>
					</NcButton>
					<NcButton v-else
						:aria-label="t('tables', 'Move {label} to the top level', { label: itemName(item) }, undefined, { escape: false })"
						:title="t('tables', 'Move to the top level')"
						variant="tertiary"
						data-cy="menuItemOutdent"
						@click="emitItems(outdentItem(items, index))">
						<template #icon>
							<FormatIndentDecrease :size="20" />
						</template>
					</NcButton>
					<NcButton :aria-label="t('tables', 'Remove {label}', { label: itemName(item) }, undefined, { escape: false })"
						:title="t('tables', 'Remove menu item')"
						variant="tertiary"
						data-cy="menuItemRemove"
						@click="emitItems(removeItem(items, index))">
						<template #icon>
							<TrashCanOutline :size="20" />
						</template>
					</NcButton>
				</div>
			</li>
		</ul>
		<p v-else class="menu-items-editor__empty">
			{{ t('tables', 'No menu items yet') }}
		</p>
		<NcButton variant="secondary" data-cy="menuItemAdd" @click="emitItems([...items, newMenuItem(items)])">
			<template #icon>
				<Plus :size="20" />
			</template>
			{{ t('tables', 'Add menu item') }}
		</NcButton>
	</div>
</template>

<script>
import { NcButton, NcSelect, NcTextField } from '@nextcloud/vue'
import ArrowDown from 'vue-material-design-icons/ArrowDown.vue'
import ArrowUp from 'vue-material-design-icons/ArrowUp.vue'
import DotsHorizontalCircleOutline from 'vue-material-design-icons/DotsHorizontalCircleOutline.vue'
import DragHorizontalVariant from 'vue-material-design-icons/DragHorizontalVariant.vue'
import FormatIndentDecrease from 'vue-material-design-icons/FormatIndentDecrease.vue'
import FormatIndentIncrease from 'vue-material-design-icons/FormatIndentIncrease.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import { mapState } from 'pinia'
import ApplicationIcon from '../ApplicationIcon.vue'
import NcIconPicker from '../ncIconPicker/NcIconPicker.vue'
import permissionsMixin from '../ncTable/mixins/permissionsMixin.js'
import { useTablesStore } from '../../../store/store.js'
import {
	canIndent,
	canMove,
	dragItemOver,
	indentItem,
	menuItemErrors,
	moveItem,
	newMenuItem,
	outdentItem,
	removeItem,
} from '../../utils/menuItems.js'

const URL_OPTION_ID = 'url'

/**
 * Edits the ordered menu of an application. Every item opens a view, a table or a link, carries an
 * icon, and can sit under the top-level item above it: one level of nesting. The list operations
 * live in utils/menuItems.js.
 */
export default {
	name: 'MenuItemsEditor',
	components: {
		ApplicationIcon,
		ArrowDown,
		ArrowUp,
		NcButton,
		NcIconPicker,
		NcSelect,
		NcTextField,
		DotsHorizontalCircleOutline,
		DragHorizontalVariant,
		FormatIndentDecrease,
		FormatIndentIncrease,
		Plus,
		TrashCanOutline,
	},
	mixins: [permissionsMixin],
	props: {
		items: {
			type: Array,
			default: () => ([]),
		},
	},
	emits: ['update:items'],
	data() {
		return {
			draggedIndex: null,
			// Only a drag that starts on the handle moves a row, so text in the fields stays selectable
			handleIndex: null,
			// Rows the user has left a field of; only their errors show, so a new row does not start red
			touchedKeys: {},
		}
	},
	computed: {
		...mapState(useTablesStore, ['tables', 'views']),
		/**
		 * The tables and views the menu can open: only those the user can manage, because the server
		 * adds them to the application as resources, like the resource picker in the same dialog.
		 *
		 * @return {Array<object>} the options of the target select
		 */
		targetOptions() {
			const views = this.views.filter(view => this.canManageElement(view)).map(view => {
				const title = (view.emoji ? view.emoji + ' ' : '') + view.title
				return {
					id: 'view-' + view.id,
					targetType: 'view',
					targetId: view.id,
					title,
					label: view.type === 'grid'
						? t('tables', '{title} (page with widgets)', { title }, undefined, { escape: false })
						: t('tables', '{title} (view)', { title }, undefined, { escape: false }),
				}
			})
			const tables = this.tables.filter(table => this.canManageTable(table)).map(table => {
				const title = (table.emoji ? table.emoji + ' ' : '') + table.title
				return {
					id: 'table-' + table.id,
					targetType: 'table',
					targetId: table.id,
					title,
					label: t('tables', '{title} (table)', { title }, undefined, { escape: false }),
				}
			})
			return [...views, ...tables, { id: URL_OPTION_ID, targetType: 'url', targetId: null, title: '', label: t('tables', 'External link') }]
		},
		/**
		 * What is missing or wrong in every item, by position.
		 *
		 * @return {Array<{label: boolean, target: boolean, url: boolean}>} the errors of each item
		 */
		errors() {
			return this.items.map(item => menuItemErrors(item))
		},
		/**
		 * The errors shown in the fields: those of rows the user has touched.
		 *
		 * @return {Array<{label: boolean, target: boolean, url: boolean}>} the visible errors of each item
		 */
		shownErrors() {
			const none = { label: false, target: false, url: false }
			return this.items.map((item, index) => this.touchedKeys[item.key] ? this.errors[index] : none)
		},
	},
	methods: {
		canIndent,
		canMove,
		indentItem,
		moveItem,
		newMenuItem,
		outdentItem,
		removeItem,
		/**
		 * The name of an item for screen readers, also while it has no label yet.
		 *
		 * @param {object} item an item of the menu
		 * @return {string} the label, or a placeholder
		 */
		itemName(item) {
			return item.label.trim() || t('tables', 'new menu item')
		},
		/**
		 * The option of the target select that matches an item.
		 *
		 * @param {object} item an item of the menu
		 * @return {object|null} the selected option, or null when its target is not offered
		 */
		targetOption(item) {
			if (item.targetType === 'url') {
				return this.targetOptions.find(option => option.id === URL_OPTION_ID)
			}
			return this.targetOptions.find(option => option.targetType === item.targetType && option.targetId === item.targetId) ?? null
		},
		/**
		 * Hands the changed list to the parent.
		 *
		 * @param {Array<object>} items the new list
		 */
		emitItems(items) {
			this.$emit('update:items', items)
		},
		/**
		 * Changes some fields of one item.
		 *
		 * @param {number} index position of the item
		 * @param {object} changes the fields to change
		 */
		update(index, changes) {
			this.emitItems(this.items.map((item, i) => i === index ? { ...item, ...changes } : item))
		},
		/**
		 * Points an item at the chosen target; an item without a label takes the target's title.
		 *
		 * @param {number} index position of the item
		 * @param {object|null} option the chosen option
		 */
		setTarget(index, option) {
			if (!option) {
				return
			}
			const changes = { targetType: option.targetType, targetId: option.targetId }
			if (option.targetType !== 'url' && this.items[index].label === '') {
				changes.label = option.title
			}
			this.touch(this.items[index])
			this.update(index, changes)
		},
		/**
		 * Marks a row as touched, so its errors show from now on.
		 *
		 * @param {object} item an item of the menu
		 */
		touch(item) {
			if (!this.touchedKeys[item.key]) {
				this.touchedKeys = { ...this.touchedKeys, [item.key]: true }
			}
		},
		/**
		 * Marks every row as touched, so all errors show, for example when the dialog is submitted.
		 */
		touchAll() {
			this.touchedKeys = Object.fromEntries(this.items.map(item => [item.key, true]))
		},
		/**
		 * Makes a row draggable while its handle is held. Releasing the pointer anywhere, or the
		 * browser cancelling it, makes the row plain again, so its fields keep selecting text.
		 *
		 * @param {number} index position of the item
		 */
		holdHandle(index) {
			this.handleIndex = index
			const release = () => {
				window.removeEventListener('pointerup', release)
				window.removeEventListener('pointercancel', release)
				// A drag that already started keeps going; dragEnd resets its own state
				if (this.draggedIndex === null) {
					this.handleIndex = null
				}
			}
			window.addEventListener('pointerup', release)
			window.addEventListener('pointercancel', release)
		},
		/**
		 * Starts dragging a row.
		 *
		 * @param {number} index position of the item
		 * @param {DragEvent} event the drag event
		 */
		dragStart(index, event) {
			this.draggedIndex = index
			if (event?.dataTransfer) {
				event.dataTransfer.effectAllowed = 'move'
			}
		},
		/**
		 * Moves the dragged row to where it is dragged over.
		 *
		 * @param {number} index position of the item it is dragged over
		 */
		dragOver(index) {
			if (this.draggedIndex === null) {
				return
			}
			const result = dragItemOver(this.items, this.draggedIndex, index)
			if (result === null) {
				return
			}
			this.draggedIndex = result.draggedIndex
			this.emitItems(result.items)
		},
		/**
		 * Ends a drag.
		 */
		dragEnd() {
			this.draggedIndex = null
			this.handleIndex = null
		},
	},
}
</script>

<style lang="scss" scoped>
.menu-items-editor {
	// The dialog's labels float left, so the editor must start on its own line
	clear: both;
	width: 100%;
	min-width: 0;

	&__help {
		color: var(--color-text-maxcontrast);
		margin: 0 0 calc(2 * var(--default-grid-baseline));
	}

	&__list {
		margin-bottom: calc(2 * var(--default-grid-baseline));
	}

	&__item {
		display: flex;
		flex-wrap: wrap;
		align-items: flex-end;
		gap: calc(2 * var(--default-grid-baseline));
		padding: calc(2 * var(--default-grid-baseline)) 0;
		border-bottom: 1px solid var(--color-border);
		list-style: none;

		&--child {
			padding-inline-start: calc(9 * var(--default-grid-baseline));
			border-inline-start: 2px solid var(--color-primary-element-light);
		}

		&--dragging {
			opacity: 0.5;
		}
	}

	&__handle {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: var(--default-clickable-area);
		height: var(--default-clickable-area);
		color: var(--color-text-maxcontrast);
		cursor: grab;
	}

	&__field {
		flex: 1 1 160px;
		min-width: 0;
	}

	&__target {
		flex: 1 1 200px;
		min-width: 0;
	}

	&__row-actions {
		display: flex;
		gap: var(--default-grid-baseline);
	}

	&__empty {
		color: var(--color-text-maxcontrast);
		margin-bottom: calc(2 * var(--default-grid-baseline));
	}
}
</style>
