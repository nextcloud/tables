<template>
	<NcModal v-if="showModal" @close="actionCancel">
		<div class="modal__content">
			<div class="row">
				<div class="col-4">
					<h2 style="padding: 0">
						{{ t('tables', 'Create row') }}
					</h2>
				</div>
			</div>
			<div v-for="column in columns" :key="column.id">
				<component :is="getFormComponent(column)"
					:column="column"
					:value.sync="row[column.id]" />
			</div>
			<div class="row">
				<div class="fix-col-4 space-T end">
					<div class="padding-right">
						<NcCheckboxRadioSwitch :checked.sync="addNewAfterSave" type="switch">
							{{ t('tables', 'Add more') }}
						</NcCheckboxRadioSwitch>
					</div>
					<button v-if="!localLoading" class="primary" :aria-label="t('tables', 'Save row')" @click="actionConfirm()">
						{{ t('tables', 'Save') }}
					</button>
				</div>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { NcModal, NcCheckboxRadioSwitch } from '@nextcloud/vue'
import { showError, showSuccess, showWarning } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/dist/index.css'
import { mapGetters } from 'vuex'
import TextLineForm from '../../../shared/components/ncTable/partials/rowTypePartials/TextLineForm.vue'
import TextLongForm from '../../../shared/components/ncTable/partials/rowTypePartials/TextLongForm.vue'
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
import TextRichForm from '../../../shared/components/ncTable/partials/rowTypePartials/TextRichForm.vue'
import { ColumnTypes } from '../../../shared/components/ncTable/mixins/columnHandler.js'

export default {
	name: 'CreateRow',
	components: {
		SelectionCheckForm,
		SelectionForm,
		SelectionMultiForm,
		NcModal,
		TextLineForm,
		TextLongForm,
		TextLinkForm,
		TextRichForm,
		NumberForm,
		NumberStarsForm,
		NumberProgressForm,
		DatetimeForm,
		DatetimeDateForm,
		DatetimeTimeForm,
		NcCheckboxRadioSwitch,
	},
	props: {
		showModal: {
			type: Boolean,
			default: false,
		},
		columns: {
			type: Array,
			default: null,
		},
	},
	data() {
		return {
			row: {},
			localLoading: false,
			addNewAfterSave: false,
		}
	},
	computed: {
		...mapGetters(['activeTable']),
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
		actionCancel() {
			this.reset()
			this.addNewAfterSave = false
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
					const validValue = this.isValueValidForColumn(this.row[col.id], col)
					mandatoryFieldsEmpty = mandatoryFieldsEmpty || !validValue
				}
			})
			if (!mandatoryFieldsEmpty) {
				this.localLoading = true
				await this.sendNewRowToBE()
				this.localLoading = false
				if (!this.addNewAfterSave) {
					this.actionCancel()
				} else {
					showSuccess(t('tables', 'Row successfully created.'))
					this.reset()
				}
			} else {
				showWarning(t('tables', 'Please fill in the mandatory fields.'))
			}
		},
		async sendNewRowToBE() {
			try {
				const data = []
				for (const [key, value] of Object.entries(this.row)) {
					data.push({
						columnId: key,
						value,
					})
				}
				await this.$store.dispatch('insertNewRow', { tableId: this.activeTable.id, data })
			} catch (e) {
				console.error(e)
				showError(t('tables', 'Could not create new row'))
			}
		},
		reset() {
			this.row = {}
		},
	},
}
</script>
<style lang="scss" scoped>

.padding-right {
	padding-right: calc(var(--default-grid-baseline) * 3);
}

</style>
