<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div style="width: 100%">
		<div class="row space-T">
			<div class="fix-col-4 title">
				{{ t('tables', 'Relation type') }}
			</div>
			<div class="fix-col-4">
				<NcSelect v-model="customSettings.relationType"
					:options="relationTypeOptions"
					:reduce="(option) => option.id"
					:aria-label-combobox="t('tables', 'Select relation type')"
					:disabled="column.id && column.id > 0"
					required
					:clearable="false"
					@input="onRelationTypeChange" />
			</div>
		</div>

		<div class="row space-T">
			<div class="fix-col-4 title">
				{{ t('tables', 'Target') }}
			</div>
			<div class="fix-col-4">
				<NcSelect v-model="customSettings.targetId"
					:options="availableTargets"
					:reduce="(option) => option.id"
					:aria-label-combobox="t('tables', 'Select target')"
					:disabled="column.id && column.id > 0"
					required
					:clearable="false"
					@input="onTargetChange" />
			</div>
		</div>

		<div class="row space-T">
			<div class="fix-col-4 title">
				{{ t('tables', 'Value selection label') }}
			</div>
			<div class="fix-col-4">
				<NcSelect v-model="customSettings.labelColumn"
					:options="availableLabelColumns"
					:reduce="(option) => option.id"
					:loading="loadingColumns"
					:aria-label-combobox="t('tables', 'Select value selection label')"
					required
					:clearable="false"
					@input="onLabelColumnChange" />
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
import NumberColumn from '../../../mixins/columnsTypes/number.js'
import TextLineColumn from '../../../mixins/columnsTypes/textLine.js'

export default {
	name: 'RelationForm',
	components: {
		NcSelect,
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
				targetId: this.column.customSettings.targetId ?? null,
				labelColumn: this.column.customSettings.labelColumn ?? null,
				relationType: this.column.customSettings.relationType ?? 'table',
			},
			loadingColumns: false,
			relationTypeOptions: [
				{ id: 'table', label: t('tables', 'Table') },
				{ id: 'view', label: t('tables', 'View') },
			],
			availableLabelColumns: [],
		}
	},
	computed: {
		...mapState(useTablesStore, ['tables', 'views']),
		availableTargets() {
			if (this.customSettings.relationType === 'table') {
				return this.tables.map(table => ({
					id: table.id,
					label: `${table.emoji} ${table.title}`,
				}))
			}

			if (this.customSettings.relationType === 'view') {
				return this.views.map(view => ({
					id: view.id,
					label: `${view.emoji} ${view.title}`,
				}))
			}

			return []
		},
	},
	async mounted() {
		if (this.customSettings.targetId) {
			await this.loadColumns()
		}
	},
	methods: {
		t,
		async loadColumns() {
			if (!this.customSettings.targetId) {
				this.availableLabelColumns = []
				return
			}

			this.loadingColumns = true
			try {
				const dataStore = useDataStore()
				const columns = await dataStore.getColumnsFromBE({
					tableId: this.customSettings.relationType === 'table' ? this.customSettings.targetId : null,
					viewId: this.customSettings.relationType === 'view' ? this.customSettings.targetId : null,
				})
				this.availableLabelColumns = columns
					.filter(column =>
						column instanceof NumberColumn || column instanceof TextLineColumn,
					)
					.map(column => ({ id: column.id, label: column.title }))
			} finally {
				this.loadingColumns = false
			}
		},
		onRelationTypeChange() {
			this.customSettings.targetId = null
			this.customSettings.labelColumn = null
			this.loadColumns()
			this.updateCustomSettings()
		},
		onTargetChange() {
			this.customSettings.labelColumn = null
			this.loadColumns()
			this.updateCustomSettings()
		},
		onLabelColumnChange() {
			this.updateCustomSettings()
		},
		updateCustomSettings() {
			this.$emit('update:customSettings', { ...this.customSettings })
		},
	},
}
</script>

<style lang="scss" scoped>
.space-T {
	margin-top: calc(var(--default-grid-baseline) * 2);
}

.space-L-small {
	margin-inline-start: calc(var(--default-grid-baseline) * 1);
}
</style>
