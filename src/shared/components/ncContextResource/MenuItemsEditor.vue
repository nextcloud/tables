<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="menu-items-editor" data-cy="menuItemsEditor">
		<ul v-if="items.length > 0" class="menu-items-editor__list">
			<li v-for="(item, index) in items"
				:key="item.key"
				class="menu-items-editor__item"
				draggable="true"
				data-cy="menuItemRow"
				@dragstart="dragStart(index)"
				@dragover.prevent="dragOver(index)"
				@dragend="dragEnd">
				<NcButton :aria-label="t('tables', 'Move')" variant="tertiary-no-background" class="move-button">
					<template #icon>
						<DragHorizontalVariant :size="20" />
					</template>
				</NcButton>
				<NcTextField :model-value="item.label"
					:label="t('tables', 'Label')"
					data-cy="menuItemLabel"
					@update:model-value="value => update(index, { label: value })" />
				<NcSelect :model-value="targetOption(item)"
					:input-label="t('tables', 'Opens')"
					:options="targetOptions"
					:clearable="false"
					:searchable="true"
					label="label"
					class="menu-items-editor__target"
					data-cy="menuItemTarget"
					@update:model-value="option => setTarget(index, option)" />
				<NcTextField v-if="item.targetType === 'url'"
					:model-value="item.url ?? ''"
					:label="t('tables', 'Link')"
					placeholder="https://"
					@update:model-value="value => update(index, { url: value })" />
				<NcButton :aria-label="t('tables', 'Remove menu item')" variant="tertiary" data-cy="menuItemRemove" @click="remove(index)">
					<template #icon>
						<TrashCanOutline :size="20" />
					</template>
				</NcButton>
			</li>
		</ul>
		<p v-else class="menu-items-editor__empty">
			{{ t('tables', 'No menu items yet') }}
		</p>
		<NcButton variant="secondary" data-cy="menuItemAdd" @click="add">
			<template #icon>
				<Plus :size="20" />
			</template>
			{{ t('tables', 'Add menu item') }}
		</NcButton>
	</div>
</template>

<script>
import { NcButton, NcSelect, NcTextField } from '@nextcloud/vue'
import DragHorizontalVariant from 'vue-material-design-icons/DragHorizontalVariant.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import { mapState } from 'pinia'
import { useTablesStore } from '../../../store/store.js'

const URL_OPTION_ID = 'url'

/**
 * Edits the ordered menu of an application. Every item opens a view, a table or a link.
 */
export default {
	name: 'MenuItemsEditor',
	components: {
		NcButton,
		NcSelect,
		NcTextField,
		DragHorizontalVariant,
		Plus,
		TrashCanOutline,
	},
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
		}
	},
	computed: {
		...mapState(useTablesStore, ['tables', 'views']),
		targetOptions() {
			const views = this.views.map(view => ({
				id: 'view-' + view.id,
				targetType: 'view',
				targetId: view.id,
				label: (view.emoji ? view.emoji + ' ' : '') + view.title + (view.type === 'grid' ? ' (' + t('tables', 'grid view') + ')' : ' (' + t('tables', 'view') + ')'),
			}))
			const tables = this.tables.map(table => ({
				id: 'table-' + table.id,
				targetType: 'table',
				targetId: table.id,
				label: (table.emoji ? table.emoji + ' ' : '') + table.title + ' (' + t('tables', 'table') + ')',
			}))
			return [...views, ...tables, { id: URL_OPTION_ID, targetType: 'url', targetId: null, label: t('tables', 'External link') }]
		},
	},
	methods: {
		targetOption(item) {
			if (item.targetType === 'url') {
				return this.targetOptions.find(option => option.id === URL_OPTION_ID)
			}
			return this.targetOptions.find(option => option.targetType === item.targetType && option.targetId === item.targetId) ?? null
		},
		emitItems(items) {
			this.$emit('update:items', items)
		},
		update(index, changes) {
			const items = this.items.map((item, i) => i === index ? { ...item, ...changes } : item)
			this.emitItems(items)
		},
		setTarget(index, option) {
			if (!option) {
				return
			}
			const changes = { targetType: option.targetType, targetId: option.targetId }
			if (option.targetType !== 'url' && this.items[index].label === '') {
				changes.label = option.label.replace(/\s\([^)]*\)$/, '')
			}
			this.update(index, changes)
		},
		add() {
			this.emitItems([...this.items, {
				key: 'new-' + Date.now(),
				label: '',
				icon: null,
				targetType: 'url',
				targetId: null,
				url: '',
				slug: null,
			}])
		},
		remove(index) {
			this.emitItems(this.items.filter((item, i) => i !== index))
		},
		dragStart(index) {
			this.draggedIndex = index
		},
		dragOver(index) {
			if (this.draggedIndex === null || this.draggedIndex === index) {
				return
			}
			const items = [...this.items]
			const [moved] = items.splice(this.draggedIndex, 1)
			items.splice(index, 0, moved)
			this.draggedIndex = index
			this.emitItems(items)
		},
		dragEnd() {
			this.draggedIndex = null
		},
	},
}
</script>

<style lang="scss" scoped>
.menu-items-editor {
	&__list {
		margin-bottom: calc(2 * var(--default-grid-baseline, 4px));
	}

	&__item {
		display: flex;
		align-items: flex-end;
		gap: calc(2 * var(--default-grid-baseline, 4px));
		padding: calc(1 * var(--default-grid-baseline, 4px)) 0;
		list-style: none;

		:deep(.input-field) {
			flex: 1;
			min-width: 120px;
		}
	}

	&__target {
		flex: 1;
		min-width: 160px;
	}

	&__empty {
		color: var(--color-text-maxcontrast);
		margin-bottom: calc(2 * var(--default-grid-baseline, 4px));
	}

	.move-button {
		cursor: move !important;
	}
}
</style>
