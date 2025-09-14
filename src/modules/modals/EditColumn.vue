<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog size="large"
		:name="t('tables', 'Edit column')"
		@closing="actionCancel">
		<div class="modal__content">
			<div v-if="loading" class="icon-loading" />
			<div class="row space-L">
				<div class="col-2">
					<MainForm :description.sync="editColumn.description"
						:mandatory.sync="editColumn.mandatory"
						:title.sync="editColumn.title"
						:custom-settings.sync="editColumn.customSettings"
						:edit-column="true"
						:title-missing-error="editErrorTitle"
						:width-invalid-error="widthInvalidError" />
				</div>
				<div class="col-2 space-LR space-T">
					<component :is="getColumnForm" :column="editColumn" :can-save.sync="canSave" />
				</div>
			</div>
			<div class="buttons">
				<div class="edit-info">
					<ColumnInfoPopover :column="column" />&nbsp;
					<div class="last-edit-info">
						{{ t('tables', 'Last edit') + ': ' }}
						{{ updateTime }}&nbsp;
						<NcUserBubble class="last-edit-info-bubble" :user="column.lastEditBy" :display-name="column.lastEditByDisplayName ? column.lastEditByDisplayName : column.lastEditBy" />
					</div>
				</div>
				<div class="right-additional-button">
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
	</NcDialog>
</template>

<script>
import { NcDialog, NcActions, NcActionButton, NcButton, NcUserBubble } from '@nextcloud/vue'
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
import UsergroupForm from '../../shared/components/ncTable/partials/columnTypePartials/forms/UsergroupForm.vue'
import { ColumnTypes } from '../../shared/components/ncTable/mixins/columnHandler.js'
import moment from '@nextcloud/moment'
import { mapActions } from 'pinia'
import { useDataStore } from '../../store/data.js'
import { COLUMN_WIDTH_MAX, COLUMN_WIDTH_MIN } from '../../shared/constants.js'

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
		NcDialog,
		NcActions,
		NcActionButton,
		ColumnInfoPopover,
		NcButton,
		NcUserBubble,
		UsergroupForm,
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
			editColumn: JSON.parse(JSON.stringify(this.column)),
			deleteId: null,
			editErrorTitle: false,
			widthInvalidError: false,
			canSave: true, // used to avoid saving an incorrect config
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
				throw Error('Form ' + form + ' does not exist')
			}
		},
		updateTime() {
			return (this.column && this.column.lastEditAt) ? this.relativeDateTime(this.column.lastEditAt) : ''
		},
	},

	methods: {
		...mapActions(useDataStore, ['updateColumn']),
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

			if (this.editColumn.customSettings?.width
				&& (this.editColumn.customSettings?.width < COLUMN_WIDTH_MIN || this.editColumn.customSettings?.width > COLUMN_WIDTH_MAX)) {
				showError(t('tables', 'Cannot save column. Column width must be between {min} and {max}.', { min: COLUMN_WIDTH_MIN, max: COLUMN_WIDTH_MAX }))
				this.widthInvalidError = true
				return
			}

			await this.updateLocalColumn()
			this.reset()
			this.$emit('close')
		},
		reset() {
			this.loading = false
			this.editColumn = null
			this.deleteId = null
			this.editErrorTitle = false
			this.widthInvalidError = false
		},
		async updateLocalColumn() {
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
			data.customSettings = { width: data.customSettings.width }
			console.debug('this column data will be send', data)
			const res = await this.updateColumn({
				id: this.editColumn.id,
				isView: this.isView,
				elementId: this.elementId,
				data,
			})

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
	padding-inline-end: calc(var(--default-grid-baseline) * 2)
}

.last-edit-info {
	display: flex;
	align-items: center;
	flex-wrap: wrap;
}

.buttons :deep(.user-bubble__wrapper) {
	padding-top: 5px;
}

.edit-info {
	display: flex;
}

.right-additional-button {
	display: flex;
	align-items: center;
}

.last-edit-info-bubble {
	display: flex!important;
}

</style>
