<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog v-if="showModal"
		:name="t('tables', 'Create column')"
		size="large"
		@closing="actionCancel">
		<div class="modal__content create-column">
			<div class="row">
				<div class="fix-col-2">
					<MainForm :description.sync="column.description"
						:mandatory.sync="column.mandatory"
						:title.sync="column.title"
						:custom-settings.sync="column.customSettings"
						:selected-views.sync="column.selectedViews"
						:title-missing-error="titleMissingError"
						:width-invalid-error="widthInvalidError" />
				</div>
				<div class="fix-col-2" style="display: block">
					<div class="row no-padding-on-mobile space-L">
						<div class="col-4 mandatory space-T" :class="{error: typeMissingError}">
							{{ t('tables', 'Type') }}
						</div>
						<div class="col-4">
							<ColumnTypeSelection :column-id.sync="combinedType" />
						</div>
					</div>

					<!-- type specific parameter -------------------------------- -->

					<div v-if="column.type === 'text' && column.subtype !== 'link'" class="row no-padding-on-mobile space-L">
						<div class="col-4 typeSelections space-B space-T space-L">
							<NcCheckboxRadioSwitch :checked.sync="column.subtype" value="line" name="textTypeSelection" type="radio">
								{{ t('tables', 'Text line') }}
							</NcCheckboxRadioSwitch>
							<NcCheckboxRadioSwitch v-if="!textAppAvailable" :checked.sync="column.subtype" value="long" name="textTypeSelection" type="radio">
								{{ t('tables', 'Simple text') }}
							</NcCheckboxRadioSwitch>
							<NcCheckboxRadioSwitch v-if="textAppAvailable" :checked.sync="column.subtype" value="rich" name="textTypeSelection" type="radio">
								{{ t('tables', 'Rich text') }}
							</NcCheckboxRadioSwitch>
						</div>
					</div>

					<div v-if="column.type === 'selection'" class="row no-padding-on-mobile space-L">
						<div class="col-4 typeSelections space-B space-T space-L">
							<NcCheckboxRadioSwitch :checked.sync="combinedType" value="selection" name="selectionTypeSelection" type="radio">
								{{ t('tables', 'Single selection') }}
							</NcCheckboxRadioSwitch>
							<NcCheckboxRadioSwitch :checked.sync="combinedType" value="selection-multi" name="selectionTypeSelection" type="radio" data-cy="createColumnMultipleSelectionSwitch">
								{{ t('tables', 'Multiple selection') }}
							</NcCheckboxRadioSwitch>
							<NcCheckboxRadioSwitch :checked.sync="combinedType" value="selection-check" name="selectionTypeSelection" type="radio" data-cy="createColumnYesNoSwitch">
								{{ t('tables', 'Yes/No') }}
							</NcCheckboxRadioSwitch>
						</div>
					</div>

					<div v-if="column.type === 'datetime'" class="row no-padding-on-mobile space-L">
						<div class="col-4 typeSelections space-B space-T space-L">
							<NcCheckboxRadioSwitch :checked.sync="combinedType" value="datetime-date" name="datetimeTypeSelection" type="radio" data-cy="createColumnDateSwitch">
								{{ t('tables', 'Date') }}
							</NcCheckboxRadioSwitch>
							<NcCheckboxRadioSwitch :checked.sync="combinedType" value="datetime-time" name="datetimeTypeSelection" type="radio" data-cy="createColumnTimeSwitch">
								{{ t('tables', 'Time') }}
							</NcCheckboxRadioSwitch>
							<NcCheckboxRadioSwitch :checked.sync="combinedType" value="datetime" name="datetimeTypeSelection" type="radio" data-cy="createColumnDateAndTimeSwitch">
								{{ t('tables', 'Date and time') }}
							</NcCheckboxRadioSwitch>
						</div>
					</div>
					<div class="row no-padding-on-mobile space-L" :data-cy="getColumnForm">
						<component :is="getColumnForm" :column="column" />
					</div>
				</div>
			</div>
			<div class="row space-T">
				<div class="fix-col-4 end">
					<div class="padding-right">
						<NcCheckboxRadioSwitch :checked.sync="addNewAfterSave" type="switch">
							{{ t('tables', 'Add more') }}
						</NcCheckboxRadioSwitch>
					</div>
					<button class="primary" data-cy="createColumnSaveBtn" @click="actionConfirm()">
						{{ t('tables', 'Save') }}
					</button>
				</div>
			</div>
		</div>
	</NcDialog>
</template>

<script>
import NumberForm from '../../shared/components/ncTable/partials/columnTypePartials/forms/NumberForm.vue'
import NumberStarsForm from '../../shared/components/ncTable/partials/columnTypePartials/forms/NumberStarsForm.vue'
import NumberProgressForm from '../../shared/components/ncTable/partials/columnTypePartials/forms/NumberProgressForm.vue'
import TextLineForm from '../../shared/components/ncTable/partials/columnTypePartials/forms/TextLineForm.vue'
import TextLinkForm from '../../shared/components/ncTable/partials/columnTypePartials/forms/TextLinkForm.vue'
import TextLongForm from '../../shared/components/ncTable/partials/columnTypePartials/forms/TextLongForm.vue'
import SelectionCheckForm from '../../shared/components/ncTable/partials/columnTypePartials/forms/SelectionCheckForm.vue'
import MainForm from '../../shared/components/ncTable/partials/columnTypePartials/forms/MainForm.vue'
import DatetimeForm from '../../shared/components/ncTable/partials/columnTypePartials/forms/DatetimeForm.vue'
import DatetimeDateForm from '../../shared/components/ncTable/partials/columnTypePartials/forms/DatetimeDateForm.vue'
import DatetimeTimeForm from '../../shared/components/ncTable/partials/columnTypePartials/forms/DatetimeTimeForm.vue'
import { NcDialog, NcCheckboxRadioSwitch } from '@nextcloud/vue'
import SelectionForm from '../../shared/components/ncTable/partials/columnTypePartials/forms/SelectionForm.vue'
import SelectionMultiForm from '../../shared/components/ncTable/partials/columnTypePartials/forms/SelectionMultiForm.vue'
import { showError, showInfo, showSuccess, showWarning } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/style.css'
import ColumnTypeSelection from '../main/partials/ColumnTypeSelection.vue'
import TextRichForm from '../../shared/components/ncTable/partials/columnTypePartials/forms/TextRichForm.vue'
import { ColumnTypes } from '../../shared/components/ncTable/mixins/columnHandler.js'
import UsergroupForm from '../../shared/components/ncTable/partials/columnTypePartials/forms/UsergroupForm.vue'
import { useTablesStore } from '../../store/store.js'
import { useDataStore } from '../../store/data.js'
import { mapActions } from 'pinia'
import { COLUMN_WIDTH_MAX, COLUMN_WIDTH_MIN } from '../../shared/constants.js'

export default {
	name: 'CreateColumn',
	components: {
		ColumnTypeSelection,
		NcDialog,
		NumberForm,
		TextLineForm,
		TextLinkForm,
		TextLongForm,
		TextRichForm,
		MainForm,
		NumberStarsForm,
		NumberProgressForm,
		SelectionCheckForm,
		DatetimeDateForm,
		DatetimeForm,
		DatetimeTimeForm,
		NcCheckboxRadioSwitch,
		SelectionForm,
		SelectionMultiForm,
		UsergroupForm,
	},
	props: {
		showModal: {
			type: Boolean,
			default: false,
		},
		isView: {
			type: Boolean,
			default: false,
		},
		element: {
			type: Object,
			default: null,
		},
		isCustomSave: {
			type: Boolean,
			default: false,
		},
		preset: {
			type: Object,
			default: null,
		},
	},
	data() {
		return {
			column: {
				type: 'text',
				subtype: 'line',
				title: '',
				description: '',
				numberPrefix: '',
				numberSuffix: '',
				selectedViews: [],
				mandatory: false,
				numberDefault: null,
				numberMin: 0,
				numberMax: 100,
				numberDecimals: 2,
				textDefault: '',
				textAllowedPattern: '',
				textMaxLength: null,
				textUnique: false,
				selectionOptions: null,
				selectionDefault: null,
				datetimeDefault: '',
				usergroupDefault: null,
				usergroupMultipleItems: false,
				usergroupSelectUsers: true,
				usergroupSelectGroups: false,
				usergroupSelectTeams: false,
				showUserStatus: false,
				customSettings: {},
			},
			textAppAvailable: !!window.OCA?.Text?.createEditor,
			addNewAfterSave: false,
			typeMissingError: false,
			widthInvalidError: false,
			titleMissingError: false,
			typeOptions: [
				{ id: 'text', label: t('tables', 'Text') },
				{ id: 'text-link', label: t('tables', 'Link') },

				{ id: 'number', label: t('tables', 'Number') },
				{ id: 'number-stars', label: t('tables', 'Stars rating') },
				{ id: 'number-progress', label: t('tables', 'Progress bar') },

				{ id: 'selection', label: t('tables', 'Selection') },

				{ id: 'datetime', label: t('tables', 'Date and time') },
				{ id: 'usergroup', label: t('tables', 'Users and groups') },
			],
		}
	},
	computed: {
		combinedType: {
			get() {
				return this.column.type ? this.column.type + ((this.column.subtype) ? ('-' + this.column.subtype) : '') : null
			},
			set(newValue) {
				if (newValue) {
					const types = newValue.split('-')
					this.column.type = types[0]
					this.column.subtype = types[1] || ''
				}
			},
		},
		getColumnForm() {
			const form = this.snakeToCamel(this.combinedType) + 'Form'
			if (this.$options.components && this.$options.components[form]) {
				return form
			} else {
				throw Error('Form ' + form + ' does not exist')
			}
		},
		type() {
			return this.column.type
		},
	},
	watch: {
		combinedType() {
			this.reset(false, false)
			if (this.preset) {
				for (const key in this.preset) {
					if (['type', 'subtype'].includes(key)) {
						continue
					}
					if (Object.hasOwn(this.column, key)) {
						this.column[key] = this.preset[key]
					}
				}
			}
		},
		preset: {
			handler() {
				if (this.preset) {
					const combinedType = this.preset.type + (this.preset.subtype ? `-${this.preset.subtype}` : '')
					if (combinedType !== this.combinedType) {
						this.combinedType = combinedType
					} else {
						for (const key in this.preset) {
							if (Object.hasOwn(this.column, key)) {
								this.column[key] = this.preset[key]
							}
						}
					}
				}
			},
			deep: true,
		},
		showModal() {
			if (this.showModal) {
				this.$nextTick(() => {
					this.$el.querySelector('input')?.focus()
				})
			}
		},
	},
	methods: {
		...mapActions(useTablesStore, ['reloadViewsOfTable']),
		...mapActions(useDataStore, ['insertNewColumn']),
		snakeToCamel(str) {
			str = str.toLowerCase().replace(/([-_][a-z])/g, group =>
				group
					.toUpperCase()
					.replace('_', '')
					.replace('-', ''),
			)
			return str.charAt(0).toUpperCase() + str.slice(1)
		},
		async actionConfirm() {
			if (!this.column.title) {
				showInfo(t('tables', 'Please insert a title for the new column.'))
				this.titleMissingError = true
			} else if (this.column.customSettings?.width
				&& (this.column.customSettings?.width < COLUMN_WIDTH_MIN || this.column.customSettings?.width > COLUMN_WIDTH_MAX)) {
				showError(t('tables', 'Cannot save column. Column width must be between {min} and {max}.', { min: COLUMN_WIDTH_MIN, max: COLUMN_WIDTH_MAX }))
				this.widthInvalidError = true
			} else if (this.column.type === null) {
				this.titleMissingError = false
				showInfo(t('tables', 'You need to select a type for the new column.'))
				this.typeMissingError = true
			} else {
				this.$emit('save', this.prepareSubmitData())
				if (this.isCustomSave) {
					this.reset()
					this.$emit('close')
					return
				}
				await this.sendNewColumnToBE()
				if (this.addNewAfterSave) {
					this.reset(true, true, false)
				} else {
					this.reset()
					this.$emit('close')
				}
			}
		},
		actionCancel() {
			this.reset()
			this.$emit('close')
		},
		prepareSubmitData() {
			const data = {
				type: this.column.type,
				subtype: this.column.subtype,
				title: this.column.title,
				description: this.column.description,
				selectedViewIds: this.column.selectedViews.map(view => view.id),
				mandatory: this.column.mandatory,
				viewId: this.isView ? this.element.id : null,
				tableId: !this.isView ? this.element.id : null,
				customSettings: { width: this.column.customSettings.width },
			}
			if (this.combinedType === ColumnTypes.TextLine) {
				data.textUnique = this.column.textUnique
			}

			if (this.combinedType === ColumnTypes.TextLine || this.combinedType === ColumnTypes.TextLong) {
				data.textDefault = this.column.textDefault
				data.textMaxLength = this.column.textMaxLength
			} else if (this.combinedType === ColumnTypes.TextRich) {
				data.textDefault = this.column.textDefault
			} else if (this.combinedType === ColumnTypes.TextLink) {
				data.textAllowedPattern = this.column.textAllowedPattern
			} else if (this.column.type === 'selection') {
				data.selectionDefault = typeof this.column.selectionDefault !== 'string' ? JSON.stringify(this.column.selectionDefault) : this.column.selectionDefault
				if (this.column.subtype !== 'check') {
					data.selectionOptions = JSON.stringify(this.column.selectionOptions)
				}
			} else if (this.column.type === 'datetime') {
				data.datetimeDefault = this.column.datetimeDefault ? this.column.subtype === 'date' ? 'today' : 'now' : ''
			} else if (this.column.type === 'usergroup') {
				data.usergroupDefault = JSON.stringify(this.column.usergroupDefault)
				data.usergroupMultipleItems = this.column.usergroupMultipleItems
				data.usergroupSelectUsers = this.column.usergroupSelectUsers
				data.usergroupSelectGroups = this.column.usergroupSelectGroups
				data.usergroupSelectTeams = this.column.usergroupSelectTeams
				data.showUserStatus = this.column.showUserStatus
			} else if (this.column.type === 'number') {
				data.numberDefault = this.column.numberDefault
				if (this.column.subtype === '') {
					data.numberDecimals = this.column.numberDecimals
					data.numberMin = this.column.numberMin
					data.numberMax = this.column.numberMax
					data.numberPrefix = this.column.numberPrefix
					data.numberSuffix = this.column.numberSuffix
				}
			}
			return data
		},
		async sendNewColumnToBE() {
			try {
				const data = this.prepareSubmitData()
				const res = await this.insertNewColumn({
					isView: this.isView,
					elementId: this.element.id,
					data,
				})

				if (res) {
					showSuccess(t('tables', 'The column "{column}" was created.', { column: this.column.title }))
				} else {
					showWarning(t('tables', 'Sorry, something went wrong.'))
					console.debug('axios error', res)
				}

				await this.reloadViewsOfTable({
					tableId: this.isView ? this.element.tableId : this.element.id,
				})
			} catch (e) {
				console.error(e)
				showError(t('tables', 'Could not create new column.'))
			}
		},
		reset(mainForm = true, type = true, selectedViews = true) {
			this.column = {
				type: this.column.type,
				subtype: this.column.subtype,
				title: this.column.title,
				description: this.column.description,
				selectedViews: this.column.selectedViews,
				mandatory: this.column.mandatory,
				numberPrefix: null,
				numberSuffix: null,
				numberDefault: null,
				numberMin: 0,
				numberMax: 100,
				numberDecimals: 2,
				textDefault: '',
				textAllowedPattern: '',
				textMaxLength: null,
				textUnique: false,
				selectionOptions: null,
				selectionDefault: null,
				datetimeDefault: '',
				usergroupDefault: null,
				usergroupMultipleItems: false,
				usergroupSelectUsers: true,
				usergroupSelectGroups: false,
				usergroupSelectTeams: false,
				showUserStatus: false,
				customSettings: {},
			}
			if (mainForm) {
				this.column.title = ''
				this.column.description = ''
				this.column.mandatory = false
			}
			if (type) {
				this.column.type = 'text'
				this.column.subtype = 'line'
			}
			if (selectedViews) {
				this.column.selectedViews = []
			}
			this.titleMissingError = false
			this.widthInvalidError = false
			this.typeMissingError = false
		},
	},
}
</script>
<style lang="scss" scoped>

.padding-right {
	padding-inline-end: calc(var(--default-grid-baseline) * 3);
}

.typeSelections {
	display: inline-flex;
	flex-wrap: wrap;
}

.typeSelections span {
	padding-inline-end: 21px;
}

.multiSelectOptionLabel {
	padding-inline-start: calc(var(--default-grid-baseline) * 1);
}

</style>
