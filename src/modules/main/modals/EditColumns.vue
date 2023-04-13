<template>
	<NcModal v-if="showModal" size="large" @close="actionCancel">
		<div class="modal__content">
			<div v-if="loading" class="icon-loading" />

			<div class="row">
				<div class="col-4">
					<h2>{{ t('tables', 'Edit columns') }}</h2>
				</div>
				<div v-if="!columns || columns.length === 0" class="col-4">
					<p>{{ t('tables', 'There are no columns yet, click on the three-dot menu next to the table title ahead and create some.') }}</p>
				</div>
			</div>

			<div v-for="column in getColumnsSorted"
				:key="column.id"
				:class="{ editRow: editColumn && editColumn.id === column.id, deleteRow: deleteId && deleteId === column.id }"
				style="margin-bottom: 25px;">
				<!-- edit mode -->
				<div v-if="editColumn && editColumn.id === column.id" class="row space-L">
					<div class="col-2">
						<MainForm :description.sync="editColumn.description"
							:mandatory.sync="editColumn.mandatory"
							:order-weight.sync="editColumn.orderWeight"
							:title.sync="editColumn.title"
							:title-missing-error="editErrorTitle" />
					</div>
					<div class="col-2 space-LR space-T">
						<NumberForm v-if="editColumn.type === 'number' && !editColumn.subtype"
							:number-default.sync="editColumn.numberDefault"
							:number-min.sync="editColumn.numberMin"
							:number-max.sync="editColumn.numberMax"
							:number-decimals.sync="editColumn.numberDecimals"
							:number-prefix.sync="editColumn.numberPrefix"
							:number-suffix.sync="editColumn.numberSuffix" />
						<NumberStarsForm v-if="editColumn.type === 'number' && editColumn.subtype === 'stars'"
							:number-default.sync="editColumn.numberDefault" />
						<NumberProgressForm v-if="editColumn.type === 'number' && editColumn.subtype === 'progress'"
							:number-default.sync="editColumn.numberDefault" />
						<TextLineForm v-if="editColumn.type === 'text' && editColumn.subtype === 'line'"
							:text-default.sync="editColumn.textDefault"
							:text-allowed-pattern.sync="editColumn.textAllowedPattern"
							:text-max-length.sync="editColumn.textMaxLength" />
						<TextLongForm v-if="editColumn.type === 'text' && editColumn.subtype === 'long'"
							:text-default.sync="editColumn.textDefault"
							:text-max-length.sync="editColumn.textMaxLength" />
						<SelectionForm v-if="editColumn.type === 'selection' && !editColumn.subtype"
							:selection-options.sync="editColumn.selectionOptions"
							:selection-default.sync="editColumn.selectionDefault" />
						<SelectionMultiForm v-if="editColumn.type === 'selection' && editColumn.subtype === 'multi'"
							:selection-options.sync="editColumn.selectionOptions"
							:selection-default.sync="editColumn.selectionDefault" />
						<SelectionCheckForm v-if="editColumn.type === 'selection' && editColumn.subtype === 'check'"
							:selection-default.sync="editColumn.selectionDefault" />
						<DatetimeForm v-if="editColumn.type === 'datetime' && !editColumn.subtype"
							:datetime-default.sync="editColumn.datetimeDefault" />
						<DatetimeDateForm v-if="editColumn.type === 'datetime' && editColumn.subtype === 'date'"
							:datetime-default.sync="editColumn.datetimeDefault" />
						<DatetimeTimeForm v-if="editColumn.type === 'datetime' && editColumn.subtype === 'time'"
							:datetime-default.sync="editColumn.datetimeDefault" />
					</div>
					<div class="col-3 space-B space-T">
						<button class="secondary" @click="editColumn = null">
							{{ t('tables', 'Cancel') }}
						</button>
						<button class="primary" @click="safeColumn">
							{{ t('tables', 'Save') }}
						</button>
					</div>
					<div class="col-1 align-right">
						<ColumnInfoPopover :column="column" />
					</div>
				</div>

				<!-- no edit mode -->
				<div v-else class="row">
					<div class="col-1 block space-T" :class="{mandatory: column.mandatory}">
						{{ column.title }}

						<span v-if="column.type === 'number' && !column.subtype" class="block">
							{{ (column.mandatory) ? t('tables', 'Number') + ', ' + t('tables', 'Mandatory'): t('tables', 'Number') }}
						</span>

						<span v-if="column.type === 'number' && column.subtype === 'stars'" class="block">
							{{ (column.mandatory) ? t('tables', 'Star rating') + ', ' + t('tables', 'Mandatory'): t('tables', 'Star rating') }}
						</span>

						<span v-if="column.type === 'number' && column.subtype === 'progress'" class="block">
							{{ (column.mandatory) ? t('tables', 'Progress bar') + ', ' + t('tables', 'Mandatory'): t('tables', 'Progress bar') }}
						</span>

						<span v-if="column.type === 'text' && column.subtype === 'line'" class="block">
							{{ (column.mandatory) ? t('tables', 'Textline') + ', ' + t('tables', 'Mandatory'): t('tables', 'Textline') }}
						</span>

						<span v-if="column.type === 'text' && column.subtype === 'long'" class="block">
							{{ (column.mandatory) ? t('tables', 'Textline') + ', ' + t('tables', 'Mandatory'): t('tables', 'Textline') }}
						</span>

						<span v-if="column.type === 'text' && column.subtype === 'link'" class="block">
							{{ (column.mandatory) ? t('tables', 'Link') + ', ' + t('tables', 'Mandatory'): t('tables', 'Link') }}
						</span>

						<span v-if="column.type === 'selection' && !column.subtype" class="block">
							{{ (column.mandatory) ? t('tables', 'Selection') + ', ' + t('tables', 'Mandatory'): t('tables', 'Single select') }}
						</span>

						<span v-if="column.type === 'selection' && column.subtype === 'multi'" class="block">
							{{ (column.mandatory) ? t('tables', 'Multiselect') + ', ' + t('tables', 'Mandatory'): t('tables', 'Multiple select') }}
						</span>

						<span v-if="column.type === 'selection' && column.subtype === 'check'" class="block">
							{{ (column.mandatory) ? t('tables', 'Yes/No') + ', ' + t('tables', 'Mandatory'): t('tables', 'Yes/No') }}
						</span>

						<span v-if="column.type === 'datetime' && !column.subtype" class="block">
							{{ (column.mandatory) ? t('tables', 'Date and time') + ', ' + t('tables', 'Mandatory'): t('tables', 'Date and time') }}
						</span>

						<span v-if="column.type === 'datetime' && column.subtype === 'date'" class="block">
							{{ (column.mandatory) ? t('tables', 'Date') + ', ' + t('tables', 'Mandatory'): t('tables', 'Date') }}
						</span>

						<span v-if="column.type === 'datetime' && column.subtype === 'time'" class="block">
							{{ (column.mandatory) ? t('tables', 'Time') + ', ' + t('tables', 'Mandatory'): t('tables', 'Time') }}
						</span>
					</div>
					<div class="col-1 space-T">
						{{ column.description | truncate(50, '...') }}
					</div>
					<div class="col-2">
						<div class="row space-T">
							<div class="col-3 column-details-table">
								<NumberTableDisplay v-if="column.type === 'number' && !column.subtype" :column="column" />
								<NumberStarsTableDisplay v-if="column.type === 'number' && column.subtype === 'stars'" :column="column" />
								<NumberProgressTableDisplay v-if="column.type === 'number' && column.subtype === 'progress'" :column="column" />
								<TextLineTableDisplay v-if="column.type === 'text' && column.subtype === 'line'" :column="column" />
								<TextLongTableDisplay v-if="column.type === 'text' && column.subtype === 'long'" :column="column" />
								<TextLinkTableDisplay v-if="column.type === 'text' && column.subtype === 'link'" :column="column" />
								<SelectionTableDisplay v-if="column.type === 'selection' && !column.subtype" :column="column" />
								<SelectionMultiTableDisplay v-if="column.type === 'selection' && column.subtype === 'multi'" :column="column" />
								<SelectionCheckTableDisplay v-if="column.type === 'selection' && column.subtype === 'check'" :column="column" />
								<DatetimeTableDisplay v-if="column.type === 'datetime' && !column.subtype" :column="column" />
								<DatetimeDateTableDisplay v-if="column.type === 'datetime' && column.subtype === 'date'" :column="column" />
								<DatetimeTimeTableDisplay v-if="column.type === 'datetime' && column.subtype === 'time'" :column="column" />
							</div>
							<div class="col-1" style="display: inline-flex;">
								<NcActions v-if="!otherActionPerformed" :inline="2">
									<NcActionButton icon="icon-triangle-n" :close-after-click="true" @click="moveUp(column)">
										{{ t('tables', 'Move up') }}
									</NcActionButton>
									<NcActionButton icon="icon-triangle-s" :close-after-click="true" @click="moveDown(column)">
										{{ t('tables', 'Move down') }}
									</NcActionButton>
								</NcActions>
								<NcActions v-if="!otherActionPerformed" type="secondary">
									<NcActionButton icon="icon-rename" :close-after-click="true" @click="editColumn = column">
										{{ t('tables', 'Edit') }}
									</NcActionButton>
									<NcActionButton :close-after-click="true" icon="icon-delete" @click="deleteId = column.id">
										{{ t('tables', 'Delete') }}
									</NcActionButton>
								</NcActions>
							</div>
						</div>
					</div>
					<div v-if="column.id === deleteId" class="row space-L">
						<div class="col-4 space-T">
							<h4>{{ t('tables', 'Do you really want to delete the column "{column}"?', { column: column.title }) }}</h4>
						</div>
						<div class="col-4 space-T space-B">
							<button class="secondary" @click="deleteId = null">
								{{ t('tables', 'Cancel') }}
							</button>
							<button class="error" @click="deleteColumn">
								{{ t('tables', 'Delete') }}
							</button>
						</div>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-4 space-B">
					<button class="secondary" @click="actionCancel">
						{{ t('tables', 'Close') }}
					</button>
				</div>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { NcModal, NcActions, NcActionButton } from '@nextcloud/vue'
import { showError, showSuccess, showWarning } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/dist/index.css'
import { mapGetters, mapState } from 'vuex'
import ColumnInfoPopover from '../partials/ColumnInfoPopover.vue'
import NumberTableDisplay from '../../../shared/components/ncTable/partials/columnTypePartials/tableDisplay/NumberTableDisplay.vue'
import NumberStarsTableDisplay from '../../../shared/components/ncTable/partials/columnTypePartials/tableDisplay/NumberStarsTableDisplay.vue'
import NumberProgressTableDisplay from '../../../shared/components/ncTable/partials/columnTypePartials/tableDisplay/NumberProgressTableDisplay.vue'
import TextLineTableDisplay from '../../../shared/components/ncTable/partials/columnTypePartials/tableDisplay/TextLineTableDisplay.vue'
import TextLongTableDisplay from '../../../shared/components/ncTable/partials/columnTypePartials/tableDisplay/TextLongTableDisplay.vue'
import TextLinkTableDisplay from '../../../shared/components/ncTable/partials/columnTypePartials/tableDisplay/TextLinkTableDisplay.vue'
import NumberForm from '../../../shared/components/ncTable/partials/columnTypePartials/forms/NumberForm.vue'
import NumberStarsForm from '../../../shared/components/ncTable/partials/columnTypePartials/forms/NumberStarsForm.vue'
import NumberProgressForm from '../../../shared/components/ncTable/partials/columnTypePartials/forms/NumberProgressForm.vue'
import TextLineForm from '../../../shared/components/ncTable/partials/columnTypePartials/forms/TextLineForm.vue'
import TextLongForm from '../../../shared/components/ncTable/partials/columnTypePartials/forms/TextLongForm.vue'
import MainForm from '../../../shared/components/ncTable/partials/columnTypePartials/forms/MainForm.vue'
import SelectionCheckTableDisplay from '../../../shared/components/ncTable/partials/columnTypePartials/tableDisplay/SelectionCheckTableDisplay.vue'
import SelectionTableDisplay from '../../../shared/components/ncTable/partials/columnTypePartials/tableDisplay/SelectionTableDisplay.vue'
import SelectionMultiTableDisplay from '../../../shared/components/ncTable/partials/columnTypePartials/tableDisplay/SelectionMultiTableDisplay.vue'
import SelectionCheckForm from '../../../shared/components/ncTable/partials/columnTypePartials/forms/SelectionCheckForm.vue'
import SelectionForm from '../../../shared/components/ncTable/partials/columnTypePartials/forms/SelectionForm.vue'
import SelectionMultiForm from '../../../shared/components/ncTable/partials/columnTypePartials/forms/SelectionMultiForm.vue'
import DatetimeForm from '../../../shared/components/ncTable/partials/columnTypePartials/forms/DatetimeForm.vue'
import DatetimeDateForm from '../../../shared/components/ncTable/partials/columnTypePartials/forms/DatetimeDateForm.vue'
import DatetimeTimeForm from '../../../shared/components/ncTable/partials/columnTypePartials/forms/DatetimeTimeForm.vue'
import DatetimeTableDisplay from '../../../shared/components/ncTable/partials/columnTypePartials/tableDisplay/DatetimeTableDisplay.vue'
import DatetimeDateTableDisplay from '../../../shared/components/ncTable/partials/columnTypePartials/tableDisplay/DatetimeDateTableDisplay.vue'
import DatetimeTimeTableDisplay from '../../../shared/components/ncTable/partials/columnTypePartials/tableDisplay/DatetimeTimeTableDisplay.vue'

export default {
	name: 'EditColumns',
	components: {
		DatetimeDateForm,
		DatetimeForm,
		DatetimeTimeForm,
		DatetimeTableDisplay,
		DatetimeTimeTableDisplay,
		DatetimeDateTableDisplay,
		SelectionCheckTableDisplay,
		SelectionCheckForm,
		NcModal,
		NcActions,
		NcActionButton,
		ColumnInfoPopover,
		NumberTableDisplay,
		NumberStarsTableDisplay,
		NumberProgressTableDisplay,
		TextLineTableDisplay,
		TextLongTableDisplay,
		TextLinkTableDisplay,
		NumberForm,
		NumberStarsForm,
		NumberProgressForm,
		TextLineForm,
		TextLongForm,
		MainForm,
		SelectionForm,
		SelectionMultiForm,
		SelectionTableDisplay,
		SelectionMultiTableDisplay,
	},
	filters: {
		truncate(text, length, suffix) {
			if (text?.length > length) {
				return text.substring(0, length) + suffix
			}
			return text
		},
	},
	props: {
		showModal: {
			type: Boolean,
		},
	},
	data() {
		return {
			loading: false,
			editColumn: null,
			deleteId: null,
			editErrorTitle: false,
		}
	},
	computed: {
		...mapState({
			columns: state => state.data.columns,
		}),
		...mapGetters(['activeTable']),
		otherActionPerformed() {
			return !!(this.editColumn !== null || this.deleteId !== null)
		},
		getColumnsSorted() {
			if (this.columns) {
				const columns = this.columns
				return columns.sort(this.compareColumns)
			} else {
				return []
			}
		},
	},

	methods: {
		actionCancel() {
			this.reset()
			this.$emit('close')
		},
		compareColumns(a, b) {
			if (a.orderWeight < b.orderWeight) { return 1 }
			if (a.orderWeight > b.orderWeight) { return -1 }
			return 0
		},
		async safeColumn() {
			if (this.editColumn.title === '') {
				showError(t('tables', 'Cannot update column. Title is missing.'))
				this.editErrorTitle = true
				return
			}
			this.editErrorTitle = false
			await this.updateColumn()
			this.editColumn = null
		},
		reset() {
			this.loading = false
			this.editColumn = null
			this.deleteId = null
			this.editErrorTitle = false
		},
		async moveUp(column) {
			let nextColumn = null
			this.columns.forEach(c => {
				if (c.orderWeight > column.orderWeight && (!nextColumn || c.orderWeight < nextColumn.orderWeight)) {
					nextColumn = c
				}
			})
			if (nextColumn) {
				column.orderWeight = nextColumn.orderWeight + 1
			} else {
				column.orderWeight++
			}
			const res = await this.$store.dispatch('updateColumn', { id: column.id, data: column })
			if (!res) {
				showWarning(t('tables', 'Could not reorder columns.'))
			}
		},
		async moveDown(column) {
			let nextColumn = null
			this.columns.forEach(c => {
				if (c.orderWeight < column.orderWeight && (!nextColumn || c.orderWeight > nextColumn.orderWeight)) {
					nextColumn = c
				}
			})
			if (nextColumn) {
				column.orderWeight = nextColumn.orderWeight - 1
			} else {
				column.orderWeight--
			}

			const res = await this.$store.dispatch('updateColumn', { id: column.id, data: column })
			if (!res) {
				showWarning(t('tables', 'Could not reorder columns.'))
			}
		},
		async updateColumn() {
			const res = await this.$store.dispatch('updateColumn', { id: this.editColumn.id, data: { ...this.editColumn } })
			if (res) {
				showSuccess(t('tables', 'The column "{column}" was updated.', { column: this.editColumn.title }))
			}
		},
		async deleteColumn() {
			const res = this.$store.dispatch('removeColumn', { id: this.deleteId })
			if (res) {
				showSuccess(t('tables', 'Column removed successfully.'))
			} else {
				showWarning(t('tables', 'Sorry, something went wrong.'))
			}
			this.deleteId = null
		},
	},
}
</script>
<style scoped>

	.editRow {
		background-color: var(--color-primary-light-hover);
	}

	.deleteRow {
		background-color: var(--color-primary-light-hover);
	}

</style>
<style>

	.column-details-table table {
		width: 100%;
		max-width: 200px;
	}

</style>
