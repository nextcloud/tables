<template>
	<NcModal v-if="showModal" size="large" @close="actionCancel">
		<div class="modal__content">
			<div class="row">
				<div class="col-4">
					<h2>{{ t('tables', 'Create column') }}</h2>
				</div>

				<div class="fix-col-2">
					<MainForm :description.sync="description"
						:mandatory.sync="mandatory"
						:order-weight.sync="orderWeight"
						:title.sync="title"
						:title-missing-error="titleMissingError" />
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

					<div v-if="combinedType === 'number'" class="row space-L no-padding-on-mobile">
						<NumberForm :number-default.sync="numberDefault"
							:number-min.sync="numberMin"
							:number-max.sync="numberMax"
							:number-decimals.sync="numberDecimals"
							:number-prefix.sync="numberPrefix"
							:number-suffix.sync="numberSuffix" />
					</div>

					<div v-if="combinedType === 'number-stars'" class="row space-L no-padding-on-mobile">
						<NumberStarsForm :number-default.sync="numberDefault" />
					</div>

					<div v-if="combinedType === 'number-progress'" class="row space-L no-padding-on-mobile">
						<NumberProgressForm :number-default.sync="numberDefault" />
					</div>

					<div v-if="type === 'text' && subtype !== 'link'" class="row no-padding-on-mobile space-L">
						<div class="col-4 typeSelections space-B space-T space-L">
							<NcCheckboxRadioSwitch :checked.sync="subtype" value="line" name="textTypeSelection" type="radio">
								{{ t('tables', 'Text line') }}
							</NcCheckboxRadioSwitch>
							<NcCheckboxRadioSwitch v-if="!textAppAvailable" :checked.sync="subtype" value="long" name="textTypeSelection" type="radio">
								{{ t('tables', 'Simple text') }}
							</NcCheckboxRadioSwitch>
							<NcCheckboxRadioSwitch v-if="textAppAvailable" :checked.sync="subtype" value="rich" name="textTypeSelection" type="radio">
								{{ t('tables', 'Rich text') }}
							</NcCheckboxRadioSwitch>
						</div>

						<TextLineForm v-if="subtype === 'line'"
							:text-default.sync="textDefault"
							:text-allowed-pattern.sync="textAllowedPattern"
							:text-max-length.sync="textMaxLength" />
						<TextLongForm v-if="subtype === 'long'"
							:text-default.sync="textDefault"
							:text-max-length.sync="textMaxLength" />
						<TextRichForm v-if="subtype === 'rich'"
							:text-default.sync="textDefault" />
					</div>

					<div v-if="type === 'selection'" class="row no-padding-on-mobile space-L">
						<div class="col-4 typeSelections space-B space-T space-L">
							<NcCheckboxRadioSwitch :checked.sync="combinedType" value="selection" name="selectionTypeSelection" type="radio">
								{{ t('tables', 'Single selection') }}
							</NcCheckboxRadioSwitch>
							<NcCheckboxRadioSwitch :checked.sync="combinedType" value="selection-multi" name="selectionTypeSelection" type="radio">
								{{ t('tables', 'Multiple selection') }}
							</NcCheckboxRadioSwitch>
							<NcCheckboxRadioSwitch :checked.sync="combinedType" value="selection-check" name="selectionTypeSelection" type="radio">
								{{ t('tables', 'Yes/No') }}
							</NcCheckboxRadioSwitch>
						</div>

						<SelectionForm v-if="combinedType === 'selection'" :selection-options.sync="selectionOptions" :selection-default.sync="selectionDefault" />

						<SelectionMultiForm v-if="combinedType === 'selection-multi'" :selection-options.sync="selectionOptions" :selection-default.sync="selectionDefault" />

						<SelectionCheckForm v-if="combinedType === 'selection-check'" :selection-default.sync="selectionDefault" />
					</div>

					<div v-if="type === 'datetime'" class="row no-padding-on-mobile space-L">
						<div class="col-4 typeSelections space-B space-T space-L">
							<NcCheckboxRadioSwitch :checked.sync="combinedType" value="datetime-date" name="datetimeTypeSelection" type="radio">
								{{ t('tables', 'Date') }}
							</NcCheckboxRadioSwitch>
							<NcCheckboxRadioSwitch :checked.sync="combinedType" value="datetime-time" name="datetimeTypeSelection" type="radio">
								{{ t('tables', 'Time') }}
							</NcCheckboxRadioSwitch>
							<NcCheckboxRadioSwitch :checked.sync="combinedType" value="datetime" name="datetimeTypeSelection" type="radio">
								{{ t('tables', 'Date and time') }}
							</NcCheckboxRadioSwitch>
						</div>

						<DatetimeForm v-if="combinedType === 'datetime'" :datetime-default.sync="datetimeDefault" />
						<DatetimeDateForm v-if="combinedType === 'datetime-date'" :datetime-default.sync="datetimeDefault" />
						<DatetimeTimeForm v-if="combinedType === 'datetime-time'" :datetime-default.sync="datetimeDefault" />
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
					<button class="primary" @click="actionConfirm()">
						{{ t('tables', 'Save') }}
					</button>
				</div>
			</div>
		</div>
	</NcModal>
</template>

<script>
import NumberForm from '../../../shared/components/ncTable/partials/columnTypePartials/forms/NumberForm.vue'
import NumberStarsForm from '../../../shared/components/ncTable/partials/columnTypePartials/forms/NumberStarsForm.vue'
import NumberProgressForm from '../../../shared/components/ncTable/partials/columnTypePartials/forms/NumberProgressForm.vue'
import TextLineForm from '../../../shared/components/ncTable/partials/columnTypePartials/forms/TextLineForm.vue'
import TextLongForm from '../../../shared/components/ncTable/partials/columnTypePartials/forms/TextLongForm.vue'
import SelectionCheckForm from '../../../shared/components/ncTable/partials/columnTypePartials/forms/SelectionCheckForm.vue'
import MainForm from '../../../shared/components/ncTable/partials/columnTypePartials/forms/MainForm.vue'
import DatetimeForm from '../../../shared/components/ncTable/partials/columnTypePartials/forms/DatetimeForm.vue'
import DatetimeDateForm from '../../../shared/components/ncTable/partials/columnTypePartials/forms/DatetimeDateForm.vue'
import DatetimeTimeForm from '../../../shared/components/ncTable/partials/columnTypePartials/forms/DatetimeTimeForm.vue'
import { NcModal, NcCheckboxRadioSwitch } from '@nextcloud/vue'
import SelectionForm from '../../../shared/components/ncTable/partials/columnTypePartials/forms/SelectionForm.vue'
import SelectionMultiForm from '../../../shared/components/ncTable/partials/columnTypePartials/forms/SelectionMultiForm.vue'
import { showError, showInfo, showSuccess, showWarning } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/dist/index.css'
import { mapGetters } from 'vuex'
import ColumnTypeSelection from '../partials/ColumnTypeSelection.vue'
import TextRichForm from '../../../shared/components/ncTable/partials/columnTypePartials/forms/TextRichForm.vue'

export default {
	name: 'CreateColumn',
	components: {
		ColumnTypeSelection,
		NcModal,
		NumberForm,
		TextLineForm,
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
	},
	props: {
		showModal: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			textAppAvailable: !!window.OCA?.Text?.createEditor,
			addNewAfterSave: false,
			type: 'text',
			subtype: 'line',
			title: '',
			description: '',
			numberPrefix: '',
			numberSuffix: '',
			orderWeight: 0,
			mandatory: false,
			numberDefault: null,
			numberMin: 0,
			numberMax: 100,
			numberDecimals: 2,
			textDefault: '',
			textAllowedPattern: '',
			textMaxLength: null,
			typeMissingError: false,
			titleMissingError: false,
			selectionOptions: null,
			selectionDefault: null,
			datetimeDefault: '',
			typeOptions: [
				{ id: 'text', label: t('tables', 'Text') },
				{ id: 'text-link', label: t('tables', 'Link') },

				{ id: 'number', label: t('tables', 'Number') },
				{ id: 'number-stars', label: t('tables', 'Stars rating') },
				{ id: 'number-progress', label: t('tables', 'Progress bar') },

				{ id: 'selection', label: t('tables', 'Selection') },

				{ id: 'datetime', label: t('tables', 'Date and time') },
			],
		}
	},
	computed: {
		...mapGetters(['activeTable']),
		combinedType: {
			get() {
				return this.type ? this.type + ((this.subtype) ? ('-' + this.subtype) : '') : null
			},
			set(newValue) {
				if (newValue) {
					const types = newValue.split('-')
					this.type = types[0]
					this.subtype = types[1] || ''
				}
			},
		},
		combinedTypeObject: {
			get() {
				const type = this.type
				let subtype = this.subtype

				if (type === 'text' && subtype !== 'link') {
					subtype = null
				}

				if (type === 'selection') {
					subtype = null
				}

				if (type === 'datetime') {
					subtype = null
				}

				let id = type
				if (subtype !== null && subtype !== '') {
					id += '-' + subtype
				}

				return this.combinedType ? this.typeOptions.filter(item => item.id === id) : null
			},
			set(o) {
				if (o) this.combinedType = o.id
			},
		},
	},
	methods: {
		async actionConfirm() {
			if (!this.title) {
				showInfo(t('tables', 'Please insert a title for the new column.'))
				this.titleMissingError = true
			} else if (this.type === null) {
				this.titleMissingError = false
				showInfo(t('tables', 'You need to select a type for the new column.'))
				this.typeMissingError = true
			} else {
				await this.sendNewColumnToBE()
				this.reset()
				if (!this.addNewAfterSave) {
					this.$emit('close')
				}
			}
		},
		actionCancel() {
			this.reset()
			this.$emit('close')
		},
		async sendNewColumnToBE() {
			try {
				const data = {
					type: this.type,
					subtype: this.subtype,
					title: this.title,
					description: this.description,
					numberPrefix: this.numberPrefix,
					numberSuffix: this.numberSuffix,
					orderWeight: this.orderWeight,
					mandatory: this.mandatory,
					numberDefault: this.numberDefault,
					numberMin: this.numberMin,
					numberMax: this.numberMax,
					numberDecimals: this.numberDecimals,
					textDefault: this.textDefault,
					textAllowedPattern: this.textAllowedPattern,
					textMaxLength: this.textMaxLength,
					selectionOptions: JSON.stringify(this.selectionOptions),
					selectionDefault: this.selectionDefault,
					datetimeDefault: this.datetimeDefault,
					tableId: this.activeTable.id,
				}
				const res = this.$store.dispatch('insertNewColumn', { data })
				if (res) {
					showSuccess(t('tables', 'The column "{column}" was created.', { column: this.title }))
				} else {
					showWarning(t('tables', 'Sorry, something went wrong.'))
					console.debug('axios error', res)
				}
			} catch (e) {
				console.error(e)
				showError(t('tables', 'Could not create new column.'))
			}
		},
		reset() {
			this.type = 'text'
			this.subtype = 'line'
			this.title = ''
			this.description = ''
			this.numberPrefix = null
			this.numberSuffix = null
			this.orderWeight = 0
			this.mandatory = false
			this.numberDefault = null
			this.numberMin = 0
			this.numberMax = 100
			this.numberDecimals = 2
			this.textDefault = ''
			this.textAllowedPattern = ''
			this.textMaxLength = null
			this.selectionOptions = null
			this.selectionDefault = null
			this.datetimeDefault = ''
			this.titleMissingError = false
			this.typeMissingError = false
		},
	},
}
</script>
<style lang="scss" scoped>

.padding-right {
	padding-right: calc(var(--default-grid-baseline) * 3);
}

.typeSelections {
	display: inline-flex;
	flex-wrap: wrap;
}

.typeSelections span {
	padding-right: 21px;
}

.multiSelectOptionLabel {
	padding-left: calc(var(--default-grid-baseline) * 1);
}

</style>
