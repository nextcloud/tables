<template>
	<div>
		<table class="file_import__preview">
			<caption>
				{{ t('tables', 'Preview') }}
			</caption>
			<thead>
				<tr>
					<th>{{ t('tables', 'Column') }}</th>
					<th>{{ t('tables', 'Data') }}</th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="(column, colIndex) in columnsConfig" :key="colIndex">
					<td>
						<div class="row space-L space-T space-B">
							<div class="column-title">
								{{ column.title }}
							</div>
							<div class="row no-padding-on-mobile">
								<div class="col-4 action-selections space-B space-T">
									<NcCheckboxRadioSwitch v-if="createMissingColumns" :checked.sync="columnsConfig[colIndex].action" value="new" name="columnActionSelection" type="radio" @update:checked="$forceUpdate()">
										{{ t('tables', 'New column') }}
									</NcCheckboxRadioSwitch>
									<NcCheckboxRadioSwitch :checked.sync="columnsConfig[colIndex].action" value="exist" name="columnActionSelection" type="radio" @update:checked="$forceUpdate()">
										{{ t('tables', 'Existing column') }}
									</NcCheckboxRadioSwitch>
									<NcCheckboxRadioSwitch v-if="!createMissingColumns" :checked.sync="columnsConfig[colIndex].action" value="ignore" name="columnActionSelection" type="radio" @update:checked="$forceUpdate()">
										{{ t('tables', 'Ignore') }}
									</NcCheckboxRadioSwitch>
								</div>
							</div>

							<div v-if="column.action === 'new'" class="row no-padding-on-mobile">
								<NcButton @click="editColumn(colIndex)">
									{{ columnsConfig[colIndex].typeLabel }}
									<template #icon>
										<TextLongIcon v-if="['text', 'text-line', 'text-rich'].includes(column.typeName)" />
										<LinkIcon v-if="column.typeName === 'text-link'" />
										<CounterIcon v-if="column.typeName === 'number'" />
										<StarIcon v-if="column.typeName === 'number-stars'" />
										<ProgressIcon v-if="column.typeName === 'number-progress'" />
										<SelectionIcon v-if="column.type === 'selection'" />
										<DatetimeIcon v-if="column.type === 'datetime'" />
									</template>
								</NcButton>
							</div>

							<div v-if="columnsConfig[colIndex].action === 'exist'" class="row no-padding-on-mobile">
								<NcSelect v-model="columnsConfig[colIndex].existColumn"
									:options="existingColumnOptions"
									:selectable="selectableExistingColumnOption"
									:aria-label-combobox="t('tables', 'Existing column')" />
							</div>
						</div>
					</td>
					<td>
						<ul>
							<li v-for="(row, rowIndex) in previewData.rows" :key="rowIndex">
								{{ row[colIndex] }}
							</li>
							<li>
								...
							</li>
						</ul>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcButton, NcCheckboxRadioSwitch, NcSelect } from '@nextcloud/vue'
import { mapGetters } from 'vuex'
import TextLongIcon from 'vue-material-design-icons/TextLong.vue'
import LinkIcon from 'vue-material-design-icons/Link.vue'
import CounterIcon from 'vue-material-design-icons/Counter.vue'
import StarIcon from 'vue-material-design-icons/Star.vue'
import ProgressIcon from 'vue-material-design-icons/ArrowRightThin.vue'
import SelectionIcon from 'vue-material-design-icons/FormSelect.vue'
import DatetimeIcon from 'vue-material-design-icons/ClipboardTextClockOutline.vue'
import { ColumnTypes } from '../../shared/components/ncTable/mixins/columnHandler.js'
import { emit } from '@nextcloud/event-bus'

export default {
	name: 'ImportPreview',

	components: {
		NcCheckboxRadioSwitch,
		NcButton,
		NcSelect,
		TextLongIcon,
		LinkIcon,
		CounterIcon,
		StarIcon,
		ProgressIcon,
		SelectionIcon,
		DatetimeIcon,
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
		...mapGetters(['isView']),
		existingColumnOptions() {
			if (!this.existingColumns.length) {
				return []
			}

			return this.existingColumns.map(column => ({
				id: column.id,
				label: column.title,
			}))
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
		this.existingColumns = await this.$store.dispatch('getColumnsFromBE', {
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

table {
	position: relative;
	border-collapse: collapse;
	border-spacing: 0;
	table-layout: auto;
	width: 100%;
	border: none;

	td, th {
		padding-right: 8px;
		max-width: 200px;
		overflow: hidden;
		text-overflow: ellipsis;
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
		padding-right: 8px;
		padding-left: 8px;
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
			text-align: left;
			vertical-align: middle;
			border-bottom: 1px solid var(--color-border);
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
	font-weight: bold;
}
</style>
