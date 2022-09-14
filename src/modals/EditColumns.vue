<template>
	<Modal v-if="showModal" @close="actionCancel">
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

			<div v-for="column in columns"
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
						<SelectionCheckForm v-if="editColumn.type === 'selection' && editColumn.subtype === 'check'"
							:selection-default.sync="editColumn.selectionDefault" />
						<DatetimeForm v-if="editColumn.type === 'datetime' && !editColumn.subtype"
							:datetime-default.sync="editColumn.datetimeDefault" />
						<DatetimeDateForm v-if="editColumn.type === 'datetime' && editColumn.subtype === 'date'"
							:datetime-default.sync="editColumn.datetimeDefault" />
						<DatetimeTimeForm v-if="editColumn.type === 'datetime' && editColumn.subtype === 'time'"
							:datetime-default.sync="editColumn.datetimeDefault" />
					</div>
					<div class="col-4 space-B space-T">
						<button class="secondary" @click="editColumn = null">
							{{ t('tables', 'Cancel') }}
						</button>
						<button class="primary" @click="safeColumn">
							{{ t('tables', 'Save') }}
						</button>
					</div>
				</div>

				<!-- no edit mode -->
				<div v-else class="row">
					<div class="col-1 block space-T" :class="{mandatory: column.mandatory}">
						{{ column.title }}

						<span v-if="column.type === 'number' && !column.subtype" class="block">{{ t('tables', 'Number') }}
							{{ (column.mandatory) ? ', ' + t('tables', 'Mandatory'): '' }}</span>
						<span v-if="column.type === 'number' && column.subtype === 'stars'" class="block">{{ t('tables', 'Star rating') }}
							{{ (column.mandatory) ? ', ' + t('tables', 'Mandatory'): '' }}</span>
						<span v-if="column.type === 'number' && column.subtype === 'progress'" class="block">{{ t('tables', 'Progress bar') }}
							{{ (column.mandatory) ? ', ' + t('tables', 'Mandatory'): '' }}</span>

						<span v-if="column.type === 'text' && column.subtype === 'line'" class="block">{{ t('tables', 'Textline') }}
							{{ (column.mandatory) ? ', ' + t('tables', 'Mandatory'): '' }}</span>
						<span v-if="column.type === 'text' && column.subtype === 'long'" class="block">{{ t('tables', 'Long text') }}
							{{ (column.mandatory) ? ', ' + t('tables', 'Mandatory'): '' }}</span>
						<span v-if="column.type === 'text' && column.subtype === 'link'" class="block">{{ t('tables', 'Link') }}
							{{ (column.mandatory) ? ', ' + t('tables', 'Mandatory'): '' }}</span>

						<span v-if="column.type === 'selection' && !column.subtype" class="block">{{ t('tables', 'Selection') }}
							{{ (column.mandatory) ? ', ' + t('tables', 'Mandatory'): '' }}</span>
						<span v-if="column.type === 'selection' && column.subtype === 'multi'" class="block">{{ t('tables', 'Multiselect') }}
							{{ (column.mandatory) ? ', ' + t('tables', 'Mandatory'): '' }}</span>
						<span v-if="column.type === 'selection' && column.subtype === 'check'" class="block">{{ t('tables', 'Yes/No') }}
							{{ (column.mandatory) ? ', ' + t('tables', 'Mandatory'): '' }}</span>

						<span v-if="column.type === 'datetime' && !column.subtype" class="block">{{ t('tables', 'Date and time') }}
							{{ (column.mandatory) ? ', ' + t('tables', 'Mandatory'): '' }}</span>
						<span v-if="column.type === 'datetime' && column.subtype === 'date'" class="block">{{ t('tables', 'Date') }}
							{{ (column.mandatory) ? ', ' + t('tables', 'Mandatory'): '' }}</span>
						<span v-if="column.type === 'datetime' && column.subtype === 'time'" class="block">{{ t('tables', 'Time') }}
							{{ (column.mandatory) ? ', ' + t('tables', 'Mandatory'): '' }}</span>
					</div>
					<div class="col-1 space-T">
						<ColumnInfoPopover :column="column" />

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
								<SelectionCheckTableDisplay v-if="column.type === 'selection' && column.subtype === 'check'" :column="column" />
								<DatetimeTableDisplay v-if="column.type === 'datetime' && !column.subtype" :column="column" />
								<DatetimeDateTableDisplay v-if="column.type === 'datetime' && column.subtype === 'date'" :column="column" />
								<DatetimeTimeTableDisplay v-if="column.type === 'datetime' && column.subtype === 'time'" :column="column" />
							</div>
							<div class="col-1">
								<Actions v-if="!otherActionPerformed" :inline="2">
									<ActionButton icon="icon-triangle-n" :close-after-click="false" @click="moveUp(column)">
										{{ t('tables', 'Move up') }}
									</ActionButton>
									<ActionButton icon="icon-triangle-s" :close-after-click="false" @click="moveDown(column)">
										{{ t('tables', 'Move down') }}
									</ActionButton>
									<ActionButton icon="icon-rename" :close-after-click="true" @click="editColumn = column">
										{{ t('tables', 'Edit') }}
									</ActionButton>
									<ActionButton :close-after-click="true" icon="icon-delete" @click="deleteId = column.id">
										{{ t('tables', 'Delete') }}
									</ActionButton>
								</Actions>
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
	</Modal>
</template>

<script>
import Modal from '@nextcloud/vue/dist/Components/Modal'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError, showSuccess, showWarning } from '@nextcloud/dialogs'
import { mapGetters } from 'vuex'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import ColumnInfoPopover from '../partials/ColumnInfoPopover'
import NumberTableDisplay from '../columnTypePartials/tableDisplay/NumberTableDisplay'
import NumberStarsTableDisplay from '../columnTypePartials/tableDisplay/NumberStarsTableDisplay'
import NumberProgressTableDisplay from '../columnTypePartials/tableDisplay/NumberProgressTableDisplay'
import TextLineTableDisplay from '../columnTypePartials/tableDisplay/TextLineTableDisplay'
import TextLongTableDisplay from '../columnTypePartials/tableDisplay/TextLongTableDisplay'
import TextLinkTableDisplay from '../columnTypePartials/tableDisplay/TextLinkTableDisplay'
import NumberForm from '../columnTypePartials/forms/NumberForm'
import NumberStarsForm from '../columnTypePartials/forms/NumberStarsForm'
import NumberProgressForm from '../columnTypePartials/forms/NumberProgressForm'
import TextLineForm from '../columnTypePartials/forms/TextLineForm'
import TextLongForm from '../columnTypePartials/forms/TextLongForm'
import MainForm from '../columnTypePartials/forms/MainForm'
import SelectionCheckTableDisplay from '../columnTypePartials/tableDisplay/SelectionCheckTableDisplay'
import SelectionCheckForm from '../columnTypePartials/forms/SelectionCheckForm'
import DatetimeForm from '../columnTypePartials/forms/DatetimeForm'
import DatetimeDateForm from '../columnTypePartials/forms/DatetimeDateForm'
import DatetimeTimeForm from '../columnTypePartials/forms/DatetimeTimeForm'
import DatetimeTableDisplay from '../columnTypePartials/tableDisplay/DatetimeTableDisplay'
import DatetimeDateTableDisplay from '../columnTypePartials/tableDisplay/DatetimeDateTableDisplay'
import DatetimeTimeTableDisplay from '../columnTypePartials/tableDisplay/DatetimeTimeTableDisplay'

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
		Modal,
		Actions,
		ActionButton,
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
	},
	filters: {
		truncate(text, length, suffix) {
			if (text.length > length) {
				return text.substring(0, length) + suffix
			} else {
				return text
			}
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
			columns: null,
			editColumn: null,
			deleteId: null,
			editErrorTitle: false,
		}
	},
	computed: {
		...mapGetters(['activeTable']),
		otherActionPerformed() {
			return !!(this.editColumn !== null || this.deleteId !== null)
		},
	},
	mounted() {
		this.getColumnsForTableFromBE()
	},
	methods: {
		async getColumnsForTableFromBE() {
			this.loading = true
			if (!this.activeTable.id) {
				this.columns = null
			} else {
				try {
					const res = await axios.get(generateUrl('/apps/tables/column/' + this.activeTable.id))
					if (res.status === 200) {
						this.columns = res.data.sort(this.compareColumns)
					} else {
						showWarning(t('tables', 'Sorry, something went wrong.'))
						console.debug('axios error', res)
					}
				} catch (e) {
					console.error(e)
					showError(t('tables', 'Could not fetch columns for table'))
				}
			}
			this.loading = false
		},
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
			await this.sendEditColumnToBE()
		},
		reset() {
			this.loading = false
			this.columns = null
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
			this.editColumn = column
			await this.sendEditColumnToBE()
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
			this.editColumn = column
			await this.sendEditColumnToBE()
		},
		async sendEditColumnToBE() {
			const editColumn = this.editColumn
			// hide edit menu immediately
			this.editColumn = null
			if (!editColumn) {
				showError(t('tables', 'An error occurred. See the logs.'))
				console.debug('tried to send editColumn to BE, but it is null', editColumn)
				return
			}
			try {
				// console.debug('try so send column', editColumn)
				const res = await axios.put(generateUrl('/apps/tables/column/' + editColumn.id), editColumn)
				if (res.status === 200) {
					showSuccess(t('tables', 'The column "{column}" was updated.', { column: editColumn.title }))
					await this.getColumnsForTableFromBE()
				} else {
					showWarning(t('tables', 'Sorry, something went wrong.'))
					console.debug('axios error', res)
				}
			} catch (e) {
				console.error(e)
				showError(t('tables', 'Could not update column'))
			}
		},
		async deleteColumn() {
			console.debug('try to delete column with id:', this.deleteId)
			await this.deleteColumnAtBE(this.deleteId)
			this.deleteId = null
			await this.getColumnsForTableFromBE()
		},
		async deleteColumnAtBE(columnId) {
			if (!columnId) {
				showError(t('tables', 'An error occurred. See the logs.'))
				console.debug('tried to delete column at BE, but it is null', columnId)
				return
			}
			try {
				console.debug('try so delete column', columnId)
				const res = await axios.delete(generateUrl('/apps/tables/column/' + columnId))
				if (res.status === 200) {
					showSuccess(t('tables', 'The column is removed.'))
					await this.getColumnsForTableFromBE()
				} else {
					showWarning(t('tables', 'Sorry, something went wrong.'))
					console.debug('axios error', res)
				}
			} catch (e) {
				console.error(e)
				showError(t('tables', 'Could not delete column'))
			}
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
