<template>
	<Modal v-if="showModal" @close="actionCancel">
		<div class="modal__content">
			<div class="row">
				<div class="col-4">
					<h2>{{ t('tables', 'Create column') }}</h2>
				</div>

				<div class="col-2">
					<MainForm :description.sync="description"
						:mandatory.sync="mandatory"
						:order-weight.sync="orderWeight"
						:title.sync="title"
						:title-missing-error="titleMissingError" />
				</div>
				<div class="col-2">
					<div class="row space-L no-padding-on-mobile">
						<div class="col-4 mandatory space-T" :class="{error: typeMissingError}">
							{{ t('tables', 'Type') }}
						</div>
						<div class="col-4">
							<Multiselect v-model="combinedTypeObject"
								:options="typeOptions"
								track-by="id"
								label="label"
								style="width: 100%" />
						</div>
					</div>

					<!-- type specific parameter -------------------------------- -->

					<div v-if="combinedType === 'number'" class="row space-L no-padding-on-mobile">
						<div class="col-4 space-T space-B">
							<h4>{{ t('tables', 'Number column specific parameters') }}</h4>
						</div>
						<NumberForm
							:number-default.sync="numberDefault"
							:number-min.sync="numberMin"
							:number-max.sync="numberMax"
							:number-decimals.sync="numberDecimals"
							:number-prefix.sync="numberPrefix"
							:number-suffix.sync="numberSuffix" />
					</div>

					<div v-if="combinedType === 'number-stars'" class="row space-L no-padding-on-mobile">
						<div class="col-4 space-T space-B">
							<h4>{{ t('tables', 'Stars rating column specific parameters') }}</h4>
						</div>
						<NumberStarsForm :number-default.sync="numberDefault" />
					</div>

					<div v-if="combinedType === 'number-progress'" class="row space-L no-padding-on-mobile">
						<div class="col-4 space-T space-B">
							<h4>{{ t('tables', 'Progress bar column specific parameters') }}</h4>
						</div>
						<NumberProgressForm :number-default.sync="numberDefault" />
					</div>

					<div v-if="type === 'text' && subtype !== 'link'" class="row space-L no-padding-on-mobile">
						<div class="col-4 space-T space-B">
							<h4>{{ t('tables', 'Text column specific parameters') }}</h4>
						</div>
						<TextLineForm v-if="subtype === 'line'"
							:text-default.sync="textDefault"
							:text-allowed-pattern.sync="textAllowedPattern"
							:text-max-length.sync="textMaxLength" />
						<TextLongForm v-if="subtype === 'long'"
							:text-default.sync="textDefault"
							:text-max-length.sync="textMaxLength" />
					</div>

					<!--
			<div v-if="combinedType === 'selection'">
				<div class="row">
					<div class="col-4">
						<h4>{{ t('tables', 'Selection column specific parameters') }}</h4>
					</div>
				</div>
				<SelectionForm :selection-options.sync="selectionOptions" :selection-default.sync="selectionDefault" />
			</div>

			<div v-if="combinedType === 'selection-multi'">
				<div class="row">
					<div class="col-4">
						<h4>{{ t('tables', 'Multiple selection column specific parameters') }}</h4>
					</div>
				</div>
				<SelectionMultiForm :selection-options.sync="selectionOptions" :selection-default.sync="selectionDefault" />
			</div>
-->

					<div v-if="combinedType === 'selection-check'" class="row space-L no-padding-on-mobile">
						<div class="col-4 space-T space-B">
							<h4>{{ t('tables', 'Yes/No column specific parameters') }}</h4>
						</div>
						<SelectionCheckForm :selection-default.sync="selectionDefault" />
					</div>

					<div v-if="combinedType === 'datetime'" class="row space-L no-padding-on-mobile">
						<div class="col-4 space-T space-B">
							<h4>{{ t('tables', 'Date and time column specific parameters') }}</h4>
						</div>
						<DatetimeForm :datetime-default.sync="datetimeDefault" />
					</div>

					<div v-if="combinedType === 'datetime-date'" class="row space-L no-padding-on-mobile">
						<div class="col-4 space-T space-B">
							<h4>{{ t('tables', 'Date column specific parameters') }}</h4>
						</div>
						<DatetimeDateForm :datetime-default.sync="datetimeDefault" />
					</div>

					<div v-if="combinedType === 'datetime-time'" class="row space-L no-padding-on-mobile">
						<div class="col-4 space-T space-B">
							<h4>{{ t('tables', 'Time column specific parameters') }}</h4>
						</div>
						<DatetimeTimeForm :datetime-default.sync="datetimeDefault" />
					</div>
				</div>
			</div>
			<div class="row space-T space-B">
				<div class="col-4">
					<button class="secondary" @click="actionCancel">
						{{ t('tables', 'Cancel') }}
					</button>
					<button class="primary" @click="actionConfirm">
						{{ t('tables', 'Save') }}
					</button>
				</div>
			</div>
		</div>
	</Modal>
</template>

<script>
import NumberForm from '../columnTypePartials/forms/NumberForm'
import NumberStarsForm from '../columnTypePartials/forms/NumberStarsForm'
import NumberProgressForm from '../columnTypePartials/forms/NumberProgressForm'
import TextLineForm from '../columnTypePartials/forms/TextLineForm'
import TextLongForm from '../columnTypePartials/forms/TextLongForm'
// import SelectionForm from '../columnTypePartials/forms/SelectionForm'
// import SelectionMultiForm from '../columnTypePartials/forms/SelectionMultiForm'
import SelectionCheckForm from '../columnTypePartials/forms/SelectionCheckForm'
import MainForm from '../columnTypePartials/forms/MainForm'
import Modal from '@nextcloud/vue/dist/Components/Modal'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError, showInfo, showSuccess, showWarning } from '@nextcloud/dialogs'
import { mapGetters } from 'vuex'
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'
import DatetimeForm from '../columnTypePartials/forms/DatetimeForm'
import DatetimeDateForm from '../columnTypePartials/forms/DatetimeDateForm'
import DatetimeTimeForm from '../columnTypePartials/forms/DatetimeTimeForm'

export default {
	name: 'CreateColumn',
	components: {
		Modal,
		NumberForm,
		TextLineForm,
		TextLongForm,
		MainForm,
		Multiselect,
		NumberStarsForm,
		NumberProgressForm,
		// SelectionForm,
		// SelectionMultiForm,
		SelectionCheckForm,
		DatetimeDateForm,
		DatetimeForm,
		DatetimeTimeForm,
	},
	props: {
		showModal: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			type: null,
			subtype: null,
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
				{ id: 'text-line', label: t('tables', 'Text line') },
				{ id: 'text-long', label: t('tables', 'Long text') },
				{ id: 'text-link', label: t('tables', 'Link') },

				{ id: 'number', label: t('tables', 'Number') },
				{ id: 'number-stars', label: t('tables', 'Stars rating') },
				{ id: 'number-progress', label: t('tables', 'Progress bar') },

				// { id: 'selection', label: t('tables', 'Selection') },
				// { id: 'selection-multi', label: t('tables', 'Multiselect') },
				{ id: 'selection-check', label: t('tables', 'Yes/No') },

				{ id: 'datetime', label: t('tables', 'Date and time') },
				{ id: 'datetime-date', label: t('tables', 'Date') },
				{ id: 'datetime-time', label: t('tables', 'Time') },
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
					this.subtype = types[1]
				}
			},
		},
		combinedTypeObject: {
			get() {
				return this.combinedType ? this.typeOptions.filter(item => item.id === this.combinedType) : null
			},
			set(o) {
				if (o) this.combinedType = o.id
			},
		},
	},
	methods: {
		async actionConfirm() {
			console.debug('try to submit new column', null)
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
				this.$emit('close')
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
					selectionOptions: this.selectionOptions,
					selectionDefault: this.selectionDefault ? 'true' : 'false',
					datetimeDefault: this.datetimeDefault,
					tableId: this.activeTable.id,
				}
				// console.debug('try so send new column', data)
				const res = await axios.post(generateUrl('/apps/tables/column'), data)
				if (res.status === 200) {
					showSuccess(t('tables', 'The column "{column}" was created.', { column: data.title }))
				} else {
					showWarning(t('tables', 'Sorry, something went wrong.'))
					console.debug('axios error', res)
				}
				// await this.$store.dispatch('loadTablesFromBE')
			} catch (e) {
				console.error(e)
				showError(t('tables', 'Could not create new column'))
			}
		},
		reset() {
			this.type = null
			this.title = ''
			this.description = ''
			this.numberPrefix = ''
			this.numberSuffix = ''
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
