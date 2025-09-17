<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		<table class="file_import__preview">
			<tbody>
				<tr v-for="(column, colIndex) in columnsConfig" :key="colIndex" class="row">
					<td class="left space-T">
						<div class="space-L space-B">
							<div class="col-4 action-selections">
								<div class="w-100 w-mobile-85">
									<div class="column-title">
										<span>{{ column.title }} ·</span> {{ columnsConfig[colIndex].typeLabel }}
									</div>
									<div class="column-values">
										<span>{{ exampleValues(colIndex) }}</span>
									</div>
								</div>
								<div class="content-center">
									<NcButton v-if="createMissingColumns" :disabled="column.action !== 'new'" @click="editColumn(colIndex)">
										<template #icon>
											<PencilIcon />
										</template>
									</NcButton>
								</div>
							</div>

							<div class="no-padding-on-mobile">
								<div class="flex w-100">
									<NcCheckboxRadioSwitch v-if="createMissingColumns" :checked.sync="columnsConfig[colIndex].action" value="new" name="columnActionSelection" type="radio" @update:checked="$forceUpdate()">
										{{ t('tables', 'Create new column') }}
									</NcCheckboxRadioSwitch>
								</div>
								<div class="col-4 action-selections mobile-block">
									<div class="w-100">
										<NcCheckboxRadioSwitch :checked.sync="columnsConfig[colIndex].action" value="exist" name="columnActionSelection" type="radio" @update:checked="$forceUpdate()">
											{{ t('tables', 'Import to existing column') }}
										</NcCheckboxRadioSwitch>
									</div>
									<div class="inline-flex">
										<NcSelect v-model="columnsConfig[colIndex].existColumn"
											:disabled="columnsConfig[colIndex].action !== 'exist'"
											:options="existingColumnOptions"
											:selectable="selectableExistingColumnOption"
											:aria-label-combobox="t('tables', 'Existing column')" />
									</div>
								</div>
								<div v-if="!createMissingColumns" class="flex w-100">
									<NcCheckboxRadioSwitch :checked.sync="columnsConfig[colIndex].action" value="ignore" name="columnActionSelection" type="radio" @update:checked="$forceUpdate()">
										{{ t('tables', 'Ignore column') }}
									</NcCheckboxRadioSwitch>
								</div>
							</div>
						</div>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcButton, NcCheckboxRadioSwitch, NcSelect } from '@nextcloud/vue'
import { mapState, mapActions } from 'pinia'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import { ColumnTypes } from '../../shared/components/ncTable/mixins/columnHandler.js'
import { emit } from '@nextcloud/event-bus'
import { useTablesStore } from '../../store/store.js'
import { useDataStore } from '../../store/data.js'
import { TYPE_META_ID } from '../../shared/constants.js'

export default {
	name: 'ImportPreview',

	components: {
		NcCheckboxRadioSwitch,
		NcButton,
		NcSelect,
		PencilIcon,
	},

	props: {
		previewData: {
			type: Object,
			default() {
				return {
					columns: [],
					rows: [],
				}
			},
		},
		element: {
			type: Object,
			default: null,
		},
		createMissingColumns: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			editingColumnIndex: null,
			editingColumnPreset: null,
			existingColumns: [],
			columnsConfig: [],
		}
	},

	computed: {
		...mapState(useTablesStore, ['isView']),
		existingColumnOptions() {
			if (!this.existingColumns.length) {
				return []
			}

			const columns = this.existingColumns.map(column => ({
				id: column.id,
				label: column.title,
			}))

			columns.unshift({
				id: TYPE_META_ID,
				label: t('tables', 'ID (Meta)'),
			})

			return columns
		},
	},

	watch: {
		columnsConfig: {
			handler(newVal) {
				this.$emit('update:columns', newVal)
			},
			deep: true,
		},
	},

	async mounted() {
		this.existingColumns = await this.getColumnsFromBE({
			tableId: !this.isView ? this.element.id : null,
			viewId: this.isView ? this.element.id : null,
		})

		for (let i = 0; i < this.previewData.columns.length; i++) {
			const typeName = this.previewData.columns[i].type + (this.previewData.columns[i].subtype ? `-${this.previewData.columns[i].subtype}` : '')
			this.columnsConfig.push({
				titleRaw: this.previewData.columns[i].title,
				title: this.previewData.columns[i].title,
				description: t('tables', 'This column was automatically created by the import service.'),
				action: this.previewData.columns[i].id ? 'exist' : this.createMissingColumns ? 'new' : 'ignore',
				existColumn: this.previewData.columns[i].id ? this.existingColumnOptions.find(col => col.id === this.previewData.columns[i].id) : null,
				type: this.previewData.columns[i].type,
				subtype: this.previewData.columns[i].subtype,
				typeName,
				typeLabel: this.getTypeLabel(typeName),
				numberDecimals: this.previewData.columns[i].numberDecimals,
				numberPrefix: this.previewData.columns[i].numberPrefix,
			})
		}
	},

	methods: {
		...mapActions(useDataStore, ['getColumnsFromBE', 'createColumn']),
		t,
		editColumn(index) {
			this.editingColumnPreset = this.columnsConfig[index]
			this.editingColumnIndex = index
			emit('tables:column:create', { isView: this.isView, element: this.element, onSave: this.onSaveColumn, preset: this.editingColumnPreset })

			// Fix modal overlay
			setTimeout(() => {
				const modal = document.querySelector('.modal__content.create-column').closest('.modal-mask')
				document.body.appendChild(modal)
			}, 500)
		},
		onSaveColumn(column) {
			this.columnsConfig[this.editingColumnIndex] = column
			this.columnsConfig[this.editingColumnIndex].titleRaw = this.previewData.columns[this.editingColumnIndex].title
			this.columnsConfig[this.editingColumnIndex].action = 'new'
			this.columnsConfig[this.editingColumnIndex].typeName = column.type + (column.subtype ? `-${column.subtype}` : '')
			this.columnsConfig[this.editingColumnIndex].typeLabel = this.getTypeLabel(this.columnsConfig[this.editingColumnIndex].typeName)
			this.editingColumnPreset = null
			this.editingColumnIndex = null
			this.$emit('update:columns', this.columnsConfig)
			this.$forceUpdate()
		},
		getTypeLabel(typeName) {
			switch (typeName) {
			case ColumnTypes.TextLine:
			case ColumnTypes.TextLong:
			case ColumnTypes.TextRich:
				return t('tables', 'Text')
			case ColumnTypes.TextLink:
				return t('tables', 'Link')
			case ColumnTypes.Number:
				return t('tables', 'Number')
			case ColumnTypes.NumberStars:
				return t('tables', 'Stars rating')
			case ColumnTypes.NumberProgress:
				return t('tables', 'Progress bar')
			case ColumnTypes.Selection:
			case ColumnTypes.SelectionMulti:
			case ColumnTypes.SelectionCheck:
				return t('tables', 'Selection')
			case ColumnTypes.Datetime:
			case ColumnTypes.DatetimeDate:
			case ColumnTypes.DatetimeTime:
				return t('tables', 'Date and time')
			default:
				return ''
			}
		},
		selectableExistingColumnOption(option) {
			return !this.columnsConfig.some(column => column.existColumn?.id === option.id)
		},
		exampleValues(colIndex) {
			let result = ''
			for (let i = 0; i < this.previewData.rows.length; i++) {
				// Limit value length to 30 characters
				let value = this.previewData.rows[i][colIndex]
				if (value && value.length > 20) {
					value = value.substring(0, 20) + '...'
				}
				if (i > 0) {
					result += ', '
				}
				result += value
			}
			if (result.length > 0) {
				result += '...'
			}
			return result
		},
	},
}
</script>

<style lang="scss" scoped>
.file_import__preview {
	margin: auto;

	& caption {
		font-weight: bold;
		margin: calc(var(--default-grid-baseline) * 2) auto;
	}
}

.w-100 {
  width: 100%;
}

.flex {
  display: flex;
}

table {
	position: relative;
	border-collapse: collapse;
	border-spacing: 0;
	table-layout: auto;
	width: 100%;
	border: none;

	td, th {
		padding-inline-end: 8px;
		max-width: 200px;
		overflow: hidden;
		white-space: nowrap;

		ul {
			li {
				overflow: hidden;
				text-overflow: ellipsis;
				white-space: nowrap;
			}
		}
	}

	td:not(:first-child), th:not(:first-child) {
		padding-inline: 8px;
	}

	tr {
		height: 51px;
		background-color: var(--color-main-background);
	}

	thead {
		tr {
			th {
				vertical-align: middle;
				color: var(--color-text-maxcontrast);
			}
		}
	}

	tbody {
		td {
			text-align: start;
			vertical-align: middle;

			&.left {
				max-width: 300px;
			}
			&.right {
				text-align: end;
			}
		}

		tr:hover {
			background-color: var(--color-background-dark);
		}
	}
}

.action-selections {
	display: inline-flex;
}

.column-title {
  width: 80%;
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  white-space: normal;

  span {
		font-weight: bold;
	}
}

.column-values {
  width: 80%;
  overflow: hidden;
  text-overflow: ellipsis;
	color: var(--color-text-maxcontrast);
}

@media only screen and (max-width: 641px) {
  .w-mobile-85 {
    width: 85%;
  }
  .mobile-block {
    display: block;
  }
}

:deep(.v-select.select) {
  max-width: 220px;
}

.content-center {
  align-content: center;
}
</style>
