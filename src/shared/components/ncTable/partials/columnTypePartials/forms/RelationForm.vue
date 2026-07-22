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
					@update:model-value="onRelationTypeChange" />
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
					@update:model-value="onTargetChange" />
			</div>
		</div>

		<div class="row space-T">
			<div class="fix-col-4 title">
				{{ t('tables', 'Label for relation selection') }}
			</div>
			<div class="fix-col-4">
				<NcSelect v-model="customSettings.labelColumn"
					:options="availableLabelColumns"
					:reduce="(option) => option.id"
					:loading="loadingColumns"
					:aria-label-combobox="t('tables', 'Select label for relation selection')"
					required
					:clearable="false"
					@update:model-value="onLabelColumnChange" />
			</div>
		</div>
		<div class="info-text">
			<IconInformation :size="16" class="info-icon" />
			<span>{{ t('tables', 'Only text and number columns can be used as label') }}</span>
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
import IconInformation from 'vue-material-design-icons/InformationOutline.vue'

export default {
	name: 'RelationForm',
	components: {
		IconInformation,
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
	],
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

.info-text {
	display: flex;
	align-items: center;
	gap: calc(var(--default-grid-baseline) * 1);
	color: var(--color-text-maxcontrast);
	font-size: 0.9rem;

	.info-icon {
		flex-shrink: 0;
	}
}
</style>
