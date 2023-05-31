<template>
	<NcModal v-if="showModal" @close="actionCancel">
		<div class="modal__content">
			<div class="row">
				<div class="col-4">
					<h2>{{ t('tables', 'Edit row') }}</h2>
				</div>
			</div>
			<div v-for="column in columns" :key="column.id">
				<component :is="getFormComponent(column)"
					:column="column"
					:value.sync="localRow[column.id]" />
			</div>
			<div class="row">
				<div class="fix-col-4 space-T" :class="{'justify-between': showDeleteButton, 'end': !showDeleteButton}">
					<div v-if="showDeleteButton">
						<NcButton v-if="!prepareDeleteRow" type="error" @click="prepareDeleteRow = true">
							{{ t('tables', 'Delete') }}
						</NcButton>
						<NcButton v-if="prepareDeleteRow"
							:wide="true"
							type="error"
							@click="actionDeleteRow">
							{{ t('tables', 'I really want to delete this row!') }}
						</NcButton>
					</div>
					<NcButton v-if="canUpdateDataActiveTable && !localLoading" type="primary" @click="actionConfirm">
						{{ t('tables', 'Save') }}
					</NcButton>
					<div v-if="localLoading" class="icon-loading" style="margin-left: 20px;" />
				</div>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { NcModal, NcButton } from '@nextcloud/vue'
import { showError, showWarning } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/dist/index.css'
import { mapGetters } from 'vuex'
import TextLineForm from '../../../shared/components/ncTable/partials/rowTypePartials/TextLineForm.vue'
import TextLongForm from '../../../shared/components/ncTable/partials/rowTypePartials/TextLongForm.vue'
import TextRichForm from '../../../shared/components/ncTable/partials/rowTypePartials/TextRichForm.vue'
import TextLinkForm from '../../../shared/components/ncTable/partials/rowTypePartials/TextLinkForm.vue'
import NumberForm from '../../../shared/components/ncTable/partials/rowTypePartials/NumberForm.vue'
import NumberStarsForm from '../../../shared/components/ncTable/partials/rowTypePartials/NumberStarsForm.vue'
import NumberProgressForm from '../../../shared/components/ncTable/partials/rowTypePartials/NumberProgressForm.vue'
import SelectionCheckForm from '../../../shared/components/ncTable/partials/rowTypePartials/SelectionCheckForm.vue'
import SelectionForm from '../../../shared/components/ncTable/partials/rowTypePartials/SelectionForm.vue'
import SelectionMultiForm from '../../../shared/components/ncTable/partials/rowTypePartials/SelectionMultiForm.vue'
import DatetimeForm from '../../../shared/components/ncTable/partials/rowTypePartials/DatetimeForm.vue'
import DatetimeDateForm from '../../../shared/components/ncTable/partials/rowTypePartials/DatetimeDateForm.vue'
import DatetimeTimeForm from '../../../shared/components/ncTable/partials/rowTypePartials/DatetimeTimeForm.vue'
import tablePermissions from '../mixins/tablePermissions.js'
import { ColumnTypes } from '../../../shared/components/ncTable/mixins/columnHandler.js'

export default {
	name: 'EditRow',
	components: {
		SelectionCheckForm,
		SelectionForm,
		SelectionMultiForm,
		NcModal,
		TextLineForm,
		TextLongForm,
		TextRichForm,
		TextLinkForm,
		NumberForm,
		NumberStarsForm,
		NumberProgressForm,
		DatetimeForm,
		DatetimeDateForm,
		DatetimeTimeForm,
		NcButton,
	},
	mixins: [tablePermissions],
	props: {
		showModal: {
			type: Boolean,
			default: false,
		},
		columns: {
			type: Array,
			default: null,
		},
		row: {
			type: Object,
			default: null,
		},
	},
	data() {
		return {
			localRow: {},
			prepareDeleteRow: false,
			localLoading: false,
		}
	},
	computed: {
		...mapGetters(['activeTable']),
		showDeleteButton() {
			return this.canDeleteDataActiveTable && !this.localLoading
		},
	},
	watch: {
		row() {
			this.loadValues()
		},
	},
	methods: {
		getFormComponent(column) {
			switch (column.type) {
			case ColumnTypes.TextLine: return 'TextLineForm'
			case ColumnTypes.TextLong: return 'TextLongForm'
			case ColumnTypes.TextLink: return 'TextLinkForm'
			case ColumnTypes.TextRich: return 'TextRichForm'
			case ColumnTypes.Number: return 'NumberForm'
			case ColumnTypes.NumberStars: return 'NumberStarsForm'
			case ColumnTypes.NumberProgress: return 'NumberProgressForm'
			case ColumnTypes.Selection: return 'SelectionForm'
			case ColumnTypes.SelectionMulti: return 'SelectionMultiForm'
			case ColumnTypes.SelectionCheck: return 'SelectionCheckForm'
			case ColumnTypes.Datetime: return 'DatetimeForm'
			case ColumnTypes.DatetimeDate: return 'DatetimeDateForm'
			case ColumnTypes.DatetimeTime: return 'DatetimeTimeForm'
			default: throw Error('No form exists for the column type ' + column.type)
			}
		},
		loadValues() {
			if (this.row) {
				const tmp = {}
				this.row.data.forEach(item => {
					tmp[item.columnId] = item.value
				})
				this.localRow = Object.assign({}, tmp)
			}
		},
		actionCancel() {
			this.reset()
			this.$emit('close')
		},
		isValueValidForColumn(value, column) {
			if (column.type === 'selection') {
				if (
					(value instanceof Array && value.length > 0)
					|| (value === parseInt(value))
				) {
					return true
				}
				return false
			}
			return !!value || value === 0
		},
		async actionConfirm() {
			let mandatoryFieldsEmpty = false
			this.columns.forEach(col => {
				if (col.mandatory) {
					const validValue = this.isValueValidForColumn(this.localRow[col.id], col)
					mandatoryFieldsEmpty = mandatoryFieldsEmpty || !validValue
				}
			})
			if (!mandatoryFieldsEmpty) {
				this.localLoading = true
				await this.sendRowToBE()
				this.localLoading = false
				this.actionCancel()
			} else {
				showWarning(t('tables', 'Please fill in the mandatory fields.'))
			}
		},
		async sendRowToBE() {
			const data = []
			for (const [key, value] of Object.entries(this.localRow)) {
				data.push({
					columnId: key,
					value,
				})
			}
			const res = await this.$store.dispatch('updateRow', { id: this.row.id, data })
			if (!res) {
				showError(t('tables', 'Could not update row'))
			}
		},
		reset() {
			this.localRow = {}
			this.dataLoaded = false
			this.prepareDeleteRow = false
		},
		actionDeleteRow() {
			this.deleteRowAtBE(this.row.id)
		},
		async deleteRowAtBE(rowId) {
			this.localLoading = true
			const res = await this.$store.dispatch('removeRow', { rowId })
			if (!res) {
				showError(t('tables', 'Could not delete row.'))
			}
			this.localLoading = false
			this.actionCancel()
		},
	},
}
</script>
