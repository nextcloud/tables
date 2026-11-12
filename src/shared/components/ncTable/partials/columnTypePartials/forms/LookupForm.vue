<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div style="width: 100%">
		<p>{{ t('tables', 'Displays a column from a linked row. Lookup values are read-only.') }}</p>
		<div class="row space-T">
			<div class="fix-col-4 title">
				{{ t('tables', 'Relation column') }}
			</div>
			<div class="fix-col-4">
				<NcSelect v-model="customSettings.relationColumnId"
					:options="relationColumns"
					:reduce="(option) => option.id"
					:loading="loadingRelationColumns"
					:aria-label-combobox="t('tables', 'Select relation column')"
					required
					@update:model-value="onRelationColumnChange" />
			</div>
		</div>

		<div class="row space-T">
			<div class="fix-col-4 title">
				{{ t('tables', 'Column to display') }}
			</div>
			<div class="fix-col-4">
				<NcSelect v-model="customSettings.targetColumnId"
					:options="targetColumns"
					:reduce="(option) => option.id"
					:loading="loadingTargetColumns"
					:disabled="!selectedRelationColumn || loadingTargetColumns"
					:aria-label-combobox="t('tables', 'Select target column')"
					required
					@update:model-value="onTargetColumnChange" />
			</div>
		</div>
	</div>
</template>

<script>
import { NcSelect } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'
import { mapState } from 'pinia'
import { useTablesStore } from '../../../../../../store/store.js'
import { useDataStore } from '../../../../../../store/data.js'
import { ColumnTypes } from '../../../mixins/columnHandler.js'

export default {
	name: 'LookupForm',
	components: {
		NcSelect,
	},
	props: {
		column: {
			type: Object,
			required: true,
		},
	},
	emits: [
		'update:customSettings',
		'update:canSave',
	],
	data() {
		return {
			customSettings: {
				relationColumnId: this.column.customSettings?.relationColumnId ?? null,
				targetColumnId: this.column.customSettings?.targetColumnId ?? null,
			},
			relationColumns: [],
			targetColumns: [],
			loadingRelationColumns: false,
			loadingTargetColumns: false,
		}
	},
	computed: {
		...mapState(useTablesStore, ['activeTable', 'activeView']),
		canSave() {
			return !!this.selectedRelationColumn && !!this.selectedTargetColumn && !this.loadingRelationColumns && !this.loadingTargetColumns
		},
		selectedRelationColumn() {
			if (!this.customSettings.relationColumnId) {
				return null
			}
			return this.relationColumns.find(c => c.id === this.customSettings.relationColumnId) || null
		},
		selectedTargetColumn() {
			if (!this.customSettings.targetColumnId) {
				return null
			}
			return this.targetColumns.find(c => c.id === this.customSettings.targetColumnId) || null
		},
	},
	watch: {
		canSave: {
			immediate: true,
			handler(canSave) { this.$emit('update:canSave', canSave) },
		},
	},
	async mounted() {
		await this.loadRelationColumns()
		if (this.customSettings.relationColumnId) {
			await this.loadTargetColumns()
		}
		if (!this.column.customSettings?.relationColumnId) {
			this.updateCustomSettings()
		}
	},
	methods: {
		t,
		async loadRelationColumns() {
			this.loadingRelationColumns = true
			try {
				const dataStore = useDataStore()
				const tableId = this.column.tableId ?? this.activeTable?.id ?? this.activeView?.tableId
				if (!tableId) {
					this.relationColumns = []
					return
				}
				let columns = dataStore.getColumns(false, tableId)
				if (!columns.length) {
					columns = await dataStore.getColumnsFromBE({ tableId, viewId: this.activeView?.id })
				}

				this.relationColumns = columns
					.filter(column => column.type === ColumnTypes.Relation)
					.map(column => ({
						id: column.id,
						label: column.title,
						column,
					}))
			} finally {
				this.loadingRelationColumns = false
			}
		},
		async loadTargetColumns() {
			this.targetColumns = []
			const relationColumnId = this.customSettings.relationColumnId
			const relationSettings = this.selectedRelationColumn?.column?.customSettings
			if (!relationSettings) {
				this.loadingTargetColumns = false
				return
			}

			this.loadingTargetColumns = true
			try {
				const dataStore = useDataStore()
				let columns = await dataStore.getColumnsFromBE({
					tableId: relationSettings.relationType === 'table' ? relationSettings.targetId : null,
					viewId: relationSettings.relationType === 'view' ? relationSettings.targetId : null,
				})
				if (relationColumnId !== this.customSettings.relationColumnId) return
				const existingTarget = dataStore.getRelations(this.column.id).column
				if (!columns.length && existingTarget?.id === this.customSettings.targetColumnId) columns = [existingTarget]
				this.targetColumns = columns
					.filter(column => column.id > 0 && column.type !== ColumnTypes.Relation && !column.isVirtual())
					.map(column => ({ id: column.id, label: column.title }))
			} finally {
				if (relationColumnId === this.customSettings.relationColumnId) this.loadingTargetColumns = false
			}
		},
		async onRelationColumnChange() {
			this.customSettings.targetColumnId = null
			this.updateCustomSettings()
			await this.loadTargetColumns()
		},
		onTargetColumnChange() {
			this.updateCustomSettings()
		},
		updateCustomSettings() {
			this.$emit('update:customSettings', { ...this.customSettings })
		},
	},
}
</script>
