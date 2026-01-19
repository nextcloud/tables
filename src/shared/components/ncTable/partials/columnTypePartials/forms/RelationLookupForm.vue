<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div style="width: 100%">
		<div v-if="column.id && column.id > 0" class="row space-T">
			<div class="fix-col-4">
				<NcNoteCard type="info">
					<p>{{ t('tables', 'This is a virtual column that displays data from related rows. Configuration cannot be changed after creation.') }}</p>
				</NcNoteCard>
			</div>
		</div>

		<div class="row space-T">
			<div class="fix-col-4 title">
				{{ t('tables', 'Relation Column') }}
			</div>
			<div class="fix-col-4">
				<NcSelect v-model="customSettings.relationColumnId"
					:options="relationColumns"
					:reduce="(option) => option.id"
					:loading="loadingRelationColumns"
					:aria-label-combobox="t('tables', 'Select relation column')"
					:disabled="column.id && column.id > 0"
					required
					@input="onRelationColumnChange" />
			</div>
		</div>

		<div class="row space-T">
			<div class="fix-col-4 title">
				{{ t('tables', 'Target Column') }}
			</div>
			<div class="fix-col-4">
				<NcSelect v-model="customSettings.targetColumnId"
					:options="targetColumns"
					:reduce="(option) => option.id"
					:loading="loadingTargetColumns"
					:aria-label-combobox="t('tables', 'Select target column')"
					:disabled="column.id && column.id > 0"
					required
					@input="onTargetColumnChange" />
			</div>
		</div>
	</div>
</template>

<script>
import { NcSelect, NcNoteCard } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'
import { mapState } from 'pinia'
import { useTablesStore } from '../../../../../../store/store.js'
import { useDataStore } from '../../../../../../store/data.js'
import { ColumnTypes } from '../../../mixins/columnHandler.js'

export default {
	name: 'RelationLookupForm',
	components: {
		NcSelect,
		NcNoteCard,
	},
	props: {
		column: {
			type: Object,
			required: true,
		},
	},
	data() {
		return {
			customSettings: {
				relationColumnId: this.column.customSettings.relationColumnId ?? null,
				targetColumnId: this.column.customSettings.targetColumnId ?? null,
			},
			relationColumns: [],
			targetColumns: [],
			loadingRelationColumns: false,
			loadingTargetColumns: false,
		}
	},
	computed: {
		...mapState(useTablesStore, ['activeTable', 'views']),
		selectedRelationColumn() {
			if (!this.customSettings.relationColumnId) {
				return null
			}
			return this.relationColumns.find(c => c.id === this.customSettings.relationColumnId) || null
		},
	},
	mounted() {
		this.loadRelationColumns()
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
				const columns = await dataStore.getColumns(null, this.activeTable?.id)

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
			if (!this.selectedRelationColumn?.column?.customSettings) {
				this.targetColumns = []
				return
			}

			this.loadingTargetColumns = true
			try {
				const dataStore = useDataStore()
				const relationSettings = this.selectedRelationColumn.column.customSettings
				if (relationSettings.relationType === 'view') {
					const view = this.views.find(view => view.id === relationSettings.targetId)
					await dataStore.loadColumnsFromBE({ view, tableId: null })
				} else {
					await dataStore.loadColumnsFromBE({ view: null, tableId: relationSettings.targetId })
				}

				const columns = dataStore.getColumns(relationSettings.relationType === 'view', relationSettings.targetId)

				this.targetColumns = columns
					.filter(column => column.type !== ColumnTypes.RelationLookup)
					.map(column => ({
						id: column.id,
						label: column.title,
					}))
			} finally {
				this.loadingTargetColumns = false
			}
		},
		async onRelationColumnChange() {
			this.customSettings.targetColumnId = null
			await this.loadTargetColumns()
			this.updateCustomSettings()
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
