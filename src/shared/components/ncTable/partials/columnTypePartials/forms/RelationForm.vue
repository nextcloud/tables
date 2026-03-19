<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		<div class="row">
			<div class="col-4 mandatory space-T">
				{{ t('tables', 'Linked table') }}
			</div>
			<div class="col-4">
				<NcSelect v-model="selectedTable"
					:options="tableOptions"
					:clearable="false"
					label="title"
					track-by="id"
					:placeholder="t('tables', 'Select a table to link')"
					@input="onTableChange" />
			</div>
		</div>
		<div class="row">
			<div class="col-4 space-T">
				{{ t('tables', 'Relation type') }}
			</div>
			<div class="col-4">
				<NcSelect v-model="selectedRelationType"
					:options="relationTypeOptions"
					:clearable="false"
					label="label"
					track-by="id"
					@input="onRelationTypeChange" />
			</div>
		</div>
		<div v-if="targetColumns.length > 0" class="row">
			<div class="col-4 space-T">
				{{ t('tables', 'Display column') }}
			</div>
			<div class="col-4">
				<NcSelect v-model="selectedDisplayColumn"
					:options="targetColumns"
					:clearable="false"
					label="title"
					track-by="id"
					:placeholder="t('tables', 'Column to display from linked table')"
					@input="onDisplayColumnChange" />
			</div>
		</div>
	</div>
</template>

<script>
import { NcSelect } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'
import { mapState } from 'pinia'
import { useTablesStore } from '../../../../../../store/store.js'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'RelationForm',
	components: { NcSelect },
	props: {
		column: {
			type: Object,
			default: null,
		},
	},
	data() {
		return {
			selectedTable: null,
			selectedRelationType: null,
			selectedDisplayColumn: null,
			targetColumns: [],
			relationTypeOptions: [
				{ id: 'one-to-one', label: t('tables', 'One to One') },
				{ id: 'one-to-many', label: t('tables', 'One to Many') },
				{ id: 'many-to-many', label: t('tables', 'Many to Many') },
			],
		}
	},
	computed: {
		...mapState(useTablesStore, ['tables']),
		tableOptions() {
			return this.tables.filter(t => t.id !== this.column?.tableId)
		},
	},
	watch: {
		column: {
			immediate: true,
			async handler(col) {
				if (col?.relationTableId) {
					this.selectedTable = this.tables.find(t => t.id === col.relationTableId) || null
					await this.loadTargetColumns()
				}
				this.selectedRelationType = this.relationTypeOptions.find(o => o.id === (col?.relationType || 'many-to-many'))
				if (col?.relationDisplayColumnId && this.targetColumns.length > 0) {
					this.selectedDisplayColumn = this.targetColumns.find(c => c.id === col.relationDisplayColumnId) || this.targetColumns[0]
				}
			},
		},
	},
	methods: {
		t,
		async onTableChange(table) {
			this.column.relationTableId = table?.id || null
			this.targetColumns = []
			this.selectedDisplayColumn = null
			if (table) {
				await this.loadTargetColumns()
				if (this.targetColumns.length > 0) {
					this.selectedDisplayColumn = this.targetColumns[0]
					this.column.relationDisplayColumnId = this.targetColumns[0].id
				}
			}
		},
		onRelationTypeChange(type) {
			this.column.relationType = type?.id || 'many-to-many'
			this.column.relationMultiple = type?.id !== 'one-to-one'
		},
		onDisplayColumnChange(col) {
			this.column.relationDisplayColumnId = col?.id || null
		},
		async loadTargetColumns() {
			if (!this.column?.relationTableId) return
			try {
				const res = await axios.get(generateUrl('/apps/tables/api/1/tables/' + this.column.relationTableId + '/columns'))
				this.targetColumns = (res.data || []).filter(c => c.id >= 0)
				if (this.column.relationDisplayColumnId) {
					this.selectedDisplayColumn = this.targetColumns.find(c => c.id === this.column.relationDisplayColumnId) || this.targetColumns[0]
				} else if (this.targetColumns.length > 0) {
					this.selectedDisplayColumn = this.targetColumns[0]
					this.column.relationDisplayColumnId = this.targetColumns[0].id
				}
			} catch (e) {
				console.error('Failed to load target columns', e)
			}
		},
	},
}
</script>
