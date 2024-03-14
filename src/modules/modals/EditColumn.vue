<template>
	<NcModal size="large" @close="actionCancel">
		<div class="modal__content">
			<div v-if="loading" class="icon-loading" />

			<div class="row">
				<div class="col-4">
					<h2>{{ t('tables', 'Edit column') }}</h2>
				</div>
			</div>

			<div class="row space-L">
				<div class="col-2">
					<MainForm :description.sync="editColumn.description"
						:mandatory.sync="editColumn.mandatory"
						:title.sync="editColumn.title"
						:edit-column="true"
						:title-missing-error="editErrorTitle" />
				</div>
				<div class="col-2 space-LR space-T">
					<component :is="getColumnForm" :column="editColumn" :can-save.sync="canSave" />
				</div>
			</div>
			<div class="buttons">
				<div class="flex">
					<ColumnInfoPopover :column="column" />&nbsp;
					<div class="last-edit-info">
						{{ t('tables', 'Last edit') + ': ' }}
						{{ updateTime }}&nbsp;
						<NcUserBubble :user="column.lastEditBy" :display-name="column.lastEditByDisplayName ? column.lastEditByDisplayName : column.lastEditBy" />
					</div>
				</div>
				<div class="flex">
					<div class="button-padding-right">
						<NcButton type="secondary" :aria-label="t('tables', 'Cancel')" @click="actionCancel">
							{{ t('tables', 'Cancel') }}
						</NcButton>
					</div>
					<NcButton type="primary" :aria-label="t('tables', 'Save')" :disabled="!canSave" @click="saveColumn">
						{{ t('tables', 'Save') }}
					</NcButton>
				</div>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { NcModal, NcActions, NcActionButton, NcButton, NcUserBubble } from '@nextcloud/vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/style.css'
import ColumnInfoPopover from '../main/partials/ColumnInfoPopover.vue'
import NumberForm from '../../shared/components/ncTable/partials/columnTypePartials/forms/NumberForm.vue'
import NumberStarsForm from '../../shared/components/ncTable/partials/columnTypePartials/forms/NumberStarsForm.vue'
import NumberProgressForm from '../../shared/components/ncTable/partials/columnTypePartials/forms/NumberProgressForm.vue'
import TextLineForm from '../../shared/components/ncTable/partials/columnTypePartials/forms/TextLineForm.vue'
import TextLinkForm from '../../shared/components/ncTable/partials/columnTypePartials/forms/TextLinkForm.vue'
import TextLongForm from '../../shared/components/ncTable/partials/columnTypePartials/forms/TextLongForm.vue'
import TextRichForm from '../../shared/components/ncTable/partials/columnTypePartials/forms/TextRichForm.vue'
import MainForm from '../../shared/components/ncTable/partials/columnTypePartials/forms/MainForm.vue'
import SelectionCheckForm from '../../shared/components/ncTable/partials/columnTypePartials/forms/SelectionCheckForm.vue'
import SelectionForm from '../../shared/components/ncTable/partials/columnTypePartials/forms/SelectionForm.vue'
import SelectionMultiForm from '../../shared/components/ncTable/partials/columnTypePartials/forms/SelectionMultiForm.vue'
import DatetimeForm from '../../shared/components/ncTable/partials/columnTypePartials/forms/DatetimeForm.vue'
import DatetimeDateForm from '../../shared/components/ncTable/partials/columnTypePartials/forms/DatetimeDateForm.vue'
import DatetimeTimeForm from '../../shared/components/ncTable/partials/columnTypePartials/forms/DatetimeTimeForm.vue'
import { ColumnTypes } from '../../shared/components/ncTable/mixins/columnHandler.js'
import moment from '@nextcloud/moment'

export default {
	name: 'EditColumn',
	components: {
		DatetimeDateForm,
		DatetimeForm,
		DatetimeTimeForm,
		SelectionCheckForm,
		NumberForm,
		NumberStarsForm,
		NumberProgressForm,
		TextLineForm,
		TextLongForm,
		TextRichForm,
		TextLinkForm,
		MainForm,
		SelectionForm,
		SelectionMultiForm,
		NcModal,
		NcActions,
		NcActionButton,
		ColumnInfoPopover,
		NcButton,
		NcUserBubble,
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
		column: {
			type: Object,
			default: null,
		},
		isView: {
			type: Boolean,
			default: false,
		},
		elementId: {
			type: Number,
			default: null,
		},
	},
	data() {
		return {
			loading: false,
			editColumn: Object.assign({}, this.column),
			deleteId: null,
			editErrorTitle: false,
			canSave: true, // avoid to save an incorrect config
		}
	},
	computed: {
		otherActionPerformed() {
			return !!(this.editColumn !== null || this.deleteId !== null)
		},
		getColumnForm() {
			const form = this.snakeToCamel(this.column.type) + 'Form'
			if (this.$options.components && this.$options.components[form]) {
				return form
			} else {
				throw Error('Form ' + form + ' does no exist')
			}
		},
		updateTime() {
			return (this.column && this.column.lastEditAt) ? this.relativeDateTime(this.column.lastEditAt) : ''
		},
	},

	methods: {
		relativeDateTime(v) {
			return moment(v).format('L') === moment().format('L') ? t('tables', 'Today') + ' ' + moment(v).format('LT') : moment(v).format('LLLL')
		},
		snakeToCamel(str) {
			str = str.toLowerCase().replace(/([-_][a-z])/g, group =>
				group
					.toUpperCase()
					.replace('_', '')
					.replace('-', ''),
			)
			return str.charAt(0).toUpperCase() + str.slice(1)
		},
		actionCancel() {
			this.reset()
			this.$emit('close')
		},
		async saveColumn() {
			if (this.editColumn.title === '') {
				showError(t('tables', 'Cannot update column. Title is missing.'))
				this.editErrorTitle = true
				return
			}
			this.editErrorTitle = false
			await this.updateColumn()
			this.reset()
			this.$emit('close')
		},
		reset() {
			this.loading = false
			this.editColumn = null
			this.deleteId = null
			this.editErrorTitle = false
		},
		async updateColumn() {
			const data = Object.assign({}, this.editColumn)
			if ((this.column.type === ColumnTypes.SelectionMulti || this.column.type === ColumnTypes.SelectionCheck) && data.selectionDefault !== null) data.selectionDefault = JSON.stringify(data.selectionDefault)
			data.numberDefault = data.numberDefault === '' ? null : data.numberDefault
			data.numberDecimals = data.numberDecimals === '' ? null : data.numberDecimals
			data.numberMin = data.numberMin === '' ? null : data.numberMin
			data.numberMax = data.numberMax === '' ? null : data.numberMax
			delete data.type
			delete data.id
			delete data.tableId
			delete data.createdAt
			delete data.createdBy
			delete data.lastEditAt
			delete data.lastEditBy
			console.debug('this column data will be send', data)
			const res = await this.$store.dispatch('updateColumn', { id: this.editColumn.id, data })
			if (res) {
				showSuccess(t('tables', 'The column "{column}" was updated.', { column: this.editColumn.title }))
			}
		},
	},
}
</script>
<style>

.column-details-table table {
	width: 100%;
	max-width: 200px;
}

.buttons {
	display: flex;
	justify-content: space-between;
	padding:  calc(var(--default-grid-baseline) * 5);
}

.button-padding-right {
	padding-right: calc(var(--default-grid-baseline) * 2)
}

.last-edit-info {
	display: flex;
	align-items: center;
}

.buttons :deep(.user-bubble__wrapper) {
	padding-top: 5px;
}

.flex { display: flex }

</style>
