<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog v-if="showModal" size="large" close-on-click-outside :name="t('tables', 'Import structure preview')"
		@closing="actionCancel">
		<div class="import-structure-preview">
			<!-- Version warning banner -->
			<NcNoteCard v-if="diff.versionWarning" type="warning" class="import-structure-preview__version-warning">
				{{ diff.versionWarning }}
			</NcNoteCard>

			<!-- Table metadata section -->
			<template v-if="tableMetaChangedFields.length > 0">
				<h3 class="import-structure-preview__section-title">
					{{ t('tables', 'Table metadata') }}
				</h3>
				<ul class="import-structure-preview__list">
					<li v-for="field in tableMetaChangedFields" :key="field">
						<NcCheckboxRadioSwitch :checked="selection.tableMeta.includes(field)"
							@update:checked="toggleTableMeta(field, $event)">
							<span class="import-structure-preview__field-label">{{ formatFieldName(field) }}</span>
							<span class="import-structure-preview__change">
								<span class="import-structure-preview__current">{{ diff.tableMeta[field].current || t('tables', '(none)') }}</span>
								<span class="import-structure-preview__arrow"> → </span>
								<span class="import-structure-preview__incoming">{{ diff.tableMeta[field].incoming || t('tables', '(none)') }}</span>
							</span>
						</NcCheckboxRadioSwitch>
					</li>
				</ul>
			</template>

			<!-- Columns — Add section -->
			<template v-if="columnsToAdd.length > 0">
				<h3 class="import-structure-preview__section-title">
					{{ n('tables', 'Add column (%n)', 'Add columns (%n)', columnsToAdd.length) }}
				</h3>
				<ul class="import-structure-preview__list">
					<li v-for="item in columnsToAdd" :key="item.column.id">
						<NcCheckboxRadioSwitch :checked="selection.columnsAdd.includes(item.column.id)"
							@update:checked="toggleColumnAdd(item.column.id, $event)">
							<span class="import-structure-preview__field-label">{{ item.column.title }}</span>
							<span class="import-structure-preview__badge">{{ item.column.type }}</span>
						</NcCheckboxRadioSwitch>
					</li>
				</ul>
			</template>

			<!-- Columns — Update section -->
			<template v-if="columnsToUpdate.length > 0">
				<h3 class="import-structure-preview__section-title">
					{{ n('tables', 'Update column (%n)', 'Update columns (%n)', columnsToUpdate.length) }}
				</h3>
				<ul class="import-structure-preview__list">
					<li v-for="item in columnsToUpdate" :key="item.targetId" class="import-structure-preview__expandable">
						<NcCheckboxRadioSwitch :checked="isColumnUpdateChecked(item)"
							:indeterminate="isColumnUpdateIndeterminate(item)"
							@update:checked="toggleColumnUpdateAll(item, $event)">
							<span class="import-structure-preview__field-label">{{ item.column.title }}</span>
						</NcCheckboxRadioSwitch>
						<ul class="import-structure-preview__sub-list">
							<li v-for="(change, field) in item.changes" :key="field">
								<NcCheckboxRadioSwitch :checked="isColumnFieldChecked(item.targetId, field)"
									@update:checked="toggleColumnField(item.targetId, field, $event)">
									<span class="import-structure-preview__field-label">{{ formatFieldName(field) }}</span>
									<span class="import-structure-preview__change">
										<span class="import-structure-preview__current">{{ formatValue(change.current) }}</span>
										<span class="import-structure-preview__arrow"> → </span>
										<span class="import-structure-preview__incoming">{{ formatValue(change.incoming) }}</span>
									</span>
								</NcCheckboxRadioSwitch>
							</li>
						</ul>
					</li>
				</ul>
			</template>

			<!-- Columns — Delete section (collapsed by default) -->
			<template v-if="columnsToDelete.length > 0">
				<button class="import-structure-preview__section-title import-structure-preview__section-title--collapsible"
					:aria-expanded="deleteColumnExpanded ? 'true' : 'false'"
					@click="deleteColumnExpanded = !deleteColumnExpanded">
					<span>{{ n('tables', 'Delete column (%n)', 'Delete columns (%n)', columnsToDelete.length) }}</span>
					<ChevronDown v-if="!deleteColumnExpanded" :size="20" />
					<ChevronUp v-if="deleteColumnExpanded" :size="20" />
				</button>
				<ul v-if="deleteColumnExpanded" class="import-structure-preview__list">
					<li v-for="item in columnsToDelete" :key="item.targetId">
						<NcCheckboxRadioSwitch :checked="selection.columnsDelete.includes(item.targetId)"
							class="import-structure-preview__delete-item"
							@update:checked="toggleColumnDelete(item.targetId, $event)">
							<span class="import-structure-preview__field-label import-structure-preview__field-label--danger">{{ item.column.title }}</span>
							<span class="import-structure-preview__badge import-structure-preview__badge--danger">{{ item.column.type }}</span>
						</NcCheckboxRadioSwitch>
					</li>
				</ul>
			</template>

			<!-- Views — Add section -->
			<template v-if="viewsToAdd.length > 0">
				<h3 class="import-structure-preview__section-title">
					{{ n('tables', 'Create view (%n)', 'Create views (%n)', viewsToAdd.length) }}
				</h3>
				<ul class="import-structure-preview__list">
					<li v-for="item in viewsToAdd" :key="item.view.title">
						<NcCheckboxRadioSwitch :checked="selection.viewsAdd.includes(item.view.title)"
							@update:checked="toggleViewAdd(item.view.title, $event)">
							<span class="import-structure-preview__field-label">{{ item.view.title }}</span>
							<span v-if="item.view.emoji" class="import-structure-preview__badge">{{ item.view.emoji }}</span>
						</NcCheckboxRadioSwitch>
					</li>
				</ul>
			</template>

			<!-- Views — Update section -->
			<template v-if="viewsToUpdate.length > 0">
				<h3 class="import-structure-preview__section-title">
					{{ n('tables', 'Update view (%n)', 'Update views (%n)', viewsToUpdate.length) }}
				</h3>
				<ul class="import-structure-preview__list">
					<li v-for="item in viewsToUpdate" :key="item.title" class="import-structure-preview__expandable">
						<NcCheckboxRadioSwitch :checked="isViewUpdateChecked(item)"
							:indeterminate="isViewUpdateIndeterminate(item)"
							@update:checked="toggleViewUpdateAll(item, $event)">
							<span class="import-structure-preview__field-label">{{ item.title }}</span>
						</NcCheckboxRadioSwitch>
						<NcNoteCard v-if="item.selectionFilterWarning" type="warning" class="import-structure-preview__filter-warning">
							{{ t('tables', 'Replacing selection options may break existing row filters — verify after import.') }}
						</NcNoteCard>
						<ul class="import-structure-preview__sub-list">
							<li v-for="(change, field) in item.changes" :key="field">
								<NcCheckboxRadioSwitch :checked="isViewFieldChecked(item.title, field)"
									@update:checked="toggleViewField(item.title, field, $event)">
									<span class="import-structure-preview__field-label">{{ formatFieldName(field) }}</span>
								</NcCheckboxRadioSwitch>
							</li>
						</ul>
					</li>
				</ul>
			</template>

			<!-- Empty state -->
			<div v-if="!hasDiffContent" class="import-structure-preview__empty">
				{{ t('tables', 'No structural differences found.') }}
			</div>
		</div>

		<template #actions>
			<NcButton @click="actionCancel">
				{{ t('tables', 'Cancel') }}
			</NcButton>
			<NcButton type="primary" :disabled="!hasSelection || applying" @click="actionApply">
				<template #icon>
					<NcLoadingIcon v-if="applying" :size="20" />
				</template>
				{{ t('tables', 'Apply selected changes') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { NcButton, NcCheckboxRadioSwitch, NcDialog, NcLoadingIcon, NcNoteCard } from '@nextcloud/vue'
import { showError } from '@nextcloud/dialogs'
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { mapActions } from 'pinia'
import ChevronDown from 'vue-material-design-icons/ChevronDown.vue'
import ChevronUp from 'vue-material-design-icons/ChevronUp.vue'
import { useTablesStore } from '../../store/store.js'

export default {
	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		NcDialog,
		NcLoadingIcon,
		NcNoteCard,
		ChevronDown,
		ChevronUp,
	},

	props: {
		showModal: {
			type: Boolean,
			default: false,
		},
		tableId: {
			type: Number,
			default: null,
		},
		scheme: {
			type: Object,
			default: null,
		},
		diff: {
			type: Object,
			default: () => ({}),
		},
	},

	data() {
		return {
			applying: false,
			deleteColumnExpanded: false,
			selection: {
				tableMeta: [],
				columnsAdd: [],
				columnsUpdate: {},
				columnsDelete: [],
				viewsAdd: [],
				viewsUpdate: {},
			},
		}
	},

	computed: {
		tableMetaChangedFields() {
			if (!this.diff?.tableMeta) return []
			return Object.keys(this.diff.tableMeta)
		},
		columnsToAdd() {
			return (this.diff?.columns ?? []).filter(c => c.action === 'add')
		},
		columnsToUpdate() {
			return (this.diff?.columns ?? []).filter(c => c.action === 'update')
		},
		columnsToDelete() {
			return (this.diff?.columns ?? []).filter(c => c.action === 'delete')
		},
		viewsToAdd() {
			return (this.diff?.views ?? []).filter(v => v.action === 'add')
		},
		viewsToUpdate() {
			return (this.diff?.views ?? []).filter(v => v.action === 'update')
		},
		hasDiffContent() {
			return this.tableMetaChangedFields.length > 0
				|| this.columnsToAdd.length > 0
				|| this.columnsToUpdate.length > 0
				|| this.columnsToDelete.length > 0
				|| this.viewsToAdd.length > 0
				|| this.viewsToUpdate.length > 0
		},
		hasSelection() {
			return this.selection.tableMeta.length > 0
				|| this.selection.columnsAdd.length > 0
				|| Object.values(this.selection.columnsUpdate).some(fields => fields.length > 0)
				|| this.selection.columnsDelete.length > 0
				|| this.selection.viewsAdd.length > 0
				|| Object.values(this.selection.viewsUpdate).some(fields => fields.length > 0)
		},
	},

	watch: {
		diff: {
			immediate: true,
			handler(newDiff) {
				this.resetSelection(newDiff)
			},
		},
	},

	methods: {
		...mapActions(useTablesStore, ['loadTablesFromBE']),

		resetSelection(diff) {
			if (!diff) return
			const sel = {
				tableMeta: [],
				columnsAdd: [],
				columnsUpdate: {},
				columnsDelete: [],
				viewsAdd: [],
				viewsUpdate: {},
			}
			// Views-add: checked by default
			for (const item of (diff.views ?? []).filter(v => v.action === 'add')) {
				sel.viewsAdd.push(item.view.title)
			}
			this.selection = sel
			this.deleteColumnExpanded = false
		},

		toggleTableMeta(field, checked) {
			if (checked && !this.selection.tableMeta.includes(field)) {
				this.selection.tableMeta = [...this.selection.tableMeta, field]
			} else if (!checked) {
				this.selection.tableMeta = this.selection.tableMeta.filter(f => f !== field)
			}
		},

		toggleColumnAdd(sourceId, checked) {
			if (checked && !this.selection.columnsAdd.includes(sourceId)) {
				this.selection.columnsAdd = [...this.selection.columnsAdd, sourceId]
			} else if (!checked) {
				this.selection.columnsAdd = this.selection.columnsAdd.filter(id => id !== sourceId)
			}
		},

		isColumnUpdateChecked(item) {
			const fields = this.selection.columnsUpdate[item.targetId] ?? []
			return fields.length === Object.keys(item.changes).length && fields.length > 0
		},

		isColumnUpdateIndeterminate(item) {
			const fields = this.selection.columnsUpdate[item.targetId] ?? []
			return fields.length > 0 && fields.length < Object.keys(item.changes).length
		},

		isColumnFieldChecked(targetId, field) {
			return (this.selection.columnsUpdate[targetId] ?? []).includes(field)
		},

		toggleColumnUpdateAll(item, checked) {
			const allFields = Object.keys(item.changes)
			this.$set(this.selection.columnsUpdate, item.targetId, checked ? [...allFields] : [])
		},

		toggleColumnField(targetId, field, checked) {
			const existing = this.selection.columnsUpdate[targetId] ?? []
			if (checked && !existing.includes(field)) {
				this.$set(this.selection.columnsUpdate, targetId, [...existing, field])
			} else if (!checked) {
				this.$set(this.selection.columnsUpdate, targetId, existing.filter(f => f !== field))
			}
		},

		toggleColumnDelete(targetId, checked) {
			if (checked && !this.selection.columnsDelete.includes(targetId)) {
				this.selection.columnsDelete = [...this.selection.columnsDelete, targetId]
			} else if (!checked) {
				this.selection.columnsDelete = this.selection.columnsDelete.filter(id => id !== targetId)
			}
		},

		isViewUpdateChecked(item) {
			const fields = this.selection.viewsUpdate[item.title] ?? []
			return fields.length === Object.keys(item.changes).length && fields.length > 0
		},

		isViewUpdateIndeterminate(item) {
			const fields = this.selection.viewsUpdate[item.title] ?? []
			return fields.length > 0 && fields.length < Object.keys(item.changes).length
		},

		isViewFieldChecked(title, field) {
			return (this.selection.viewsUpdate[title] ?? []).includes(field)
		},

		toggleViewAdd(title, checked) {
			if (checked && !this.selection.viewsAdd.includes(title)) {
				this.selection.viewsAdd = [...this.selection.viewsAdd, title]
			} else if (!checked) {
				this.selection.viewsAdd = this.selection.viewsAdd.filter(t => t !== title)
			}
		},

		toggleViewUpdateAll(item, checked) {
			const allFields = Object.keys(item.changes)
			this.$set(this.selection.viewsUpdate, item.title, checked ? [...allFields] : [])
		},

		toggleViewField(title, field, checked) {
			const existing = this.selection.viewsUpdate[title] ?? []
			if (checked && !existing.includes(field)) {
				this.$set(this.selection.viewsUpdate, title, [...existing, field])
			} else if (!checked) {
				this.$set(this.selection.viewsUpdate, title, existing.filter(f => f !== field))
			}
		},

		formatFieldName(field) {
			const labels = {
				title: t('tables', 'Title'),
				emoji: t('tables', 'Emoji'),
				description: t('tables', 'Description'),
				mandatory: t('tables', 'Mandatory'),
				textDefault: t('tables', 'Default value'),
				textAllowedPattern: t('tables', 'Allowed pattern'),
				textMaxLength: t('tables', 'Max length'),
				textUnique: t('tables', 'Unique'),
				numberDefault: t('tables', 'Default value'),
				numberMin: t('tables', 'Minimum'),
				numberMax: t('tables', 'Maximum'),
				numberDecimals: t('tables', 'Decimals'),
				numberPrefix: t('tables', 'Prefix'),
				numberSuffix: t('tables', 'Suffix'),
				selectionOptions: t('tables', 'Options'),
				selectionDefault: t('tables', 'Default'),
				datetimeDefault: t('tables', 'Default'),
				filter: t('tables', 'Filter'),
				sort: t('tables', 'Sort'),
				columns: t('tables', 'Columns'),
				columnSettings: t('tables', 'Column settings'),
			}
			return labels[field] ?? field
		},

		formatValue(value) {
			if (value === null || value === undefined) return t('tables', '(none)')
			if (typeof value === 'object') return JSON.stringify(value)
			return String(value)
		},

		actionCancel() {
			this.$emit('close')
		},

		async actionApply() {
			this.applying = true
			try {
				const url = generateOcsUrl('/apps/tables/api/2/tables/' + this.tableId + '/scheme')
				await axios.put(url, { scheme: this.scheme, selection: this.selection })
				await this.loadTablesFromBE()
				this.$emit('close')
			} catch (err) {
				const message = err.response?.data?.ocs?.data?.message
					?? err.response?.data?.message
					?? t('tables', 'Failed to apply structure changes. Please try again.')
				const failedStep = err.response?.data?.ocs?.data?.failedStep
				const detail = failedStep ? ` (${t('tables', 'failed step')}: ${failedStep})` : ''
				showError(message + detail)
			} finally {
				this.applying = false
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.import-structure-preview {
	display: flex;
	flex-direction: column;
	gap: 8px;
	padding: 4px 0;

	&__version-warning,
	&__filter-warning {
		margin: 0;
	}

	&__section-title {
		font-size: 1rem;
		font-weight: 600;
		margin: 16px 0 4px;

		&--collapsible {
			display: flex;
			align-items: center;
			gap: 8px;
			cursor: pointer;
			background: none;
			border: none;
			padding: 0;
			color: inherit;
			width: 100%;
			text-align: start;

			&:focus-visible {
				outline: 2px solid var(--color-primary-element);
				outline-offset: 2px;
				border-radius: 4px;
			}
		}
	}

	&__list {
		list-style: none;
		padding: 0;
		margin: 0;
		display: flex;
		flex-direction: column;
		gap: 4px;
	}

	&__sub-list {
		list-style: none;
		padding: 0 0 0 32px;
		margin: 4px 0 0;
		display: flex;
		flex-direction: column;
		gap: 4px;
	}

	&__change {
		font-size: 0.85rem;
		color: var(--color-text-lighter);
		margin-inline-start: 8px;
	}

	&__current {
		text-decoration: line-through;
		opacity: 0.8;
	}

	&__incoming {
		color: var(--color-success);
	}

	&__field-label {
		font-weight: 500;

		&--danger {
			color: var(--color-error);
		}
	}

	&__badge {
		display: inline-block;
		font-size: 0.75rem;
		background: var(--color-background-dark);
		border-radius: 4px;
		padding: 1px 6px;
		margin-inline-start: 6px;

		&--danger {
			background: var(--color-error-hover);
			color: var(--color-error-text);
		}
	}

	&__expandable {
		display: flex;
		flex-direction: column;
	}

	&__delete-item {
		color: var(--color-error);
	}

	&__empty {
		padding: 24px 0;
		text-align: center;
		color: var(--color-text-lighter);
	}
}
</style>
