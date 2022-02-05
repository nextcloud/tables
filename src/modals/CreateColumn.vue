<template>
	<Modal v-if="showModal" @close="actionCancel">
		<div class="modal__content">
			<div class="row">
				<div class="col-4">
					<h2>{{ t('tables', 'Create column') }}</h2>
				</div>

				<MainForm :description.sync="description"
					:mandatory.sync="mandatory"
					:order-weight.sync="orderWeight"
					:prefix.sync="prefix"
					:suffix.sync="suffix"
					:title.sync="title"
					:title-missing-error="titleMissingError" />

				<div class="fix-col-1 mandatory" :class="{error: typeMissingError}">
					{{ t('tables', 'Type') }}
				</div>
				<div class="fix-col-3 margin-bottom" :class="{error: typeMissingError}">
					<CheckboxRadioSwitch :checked.sync="type"
						value="textline"
						name="type"
						class="row"
						type="radio">
						{{ t('tables', 'Textline') }}
					</CheckboxRadioSwitch>
					<CheckboxRadioSwitch :checked.sync="type"
						value="longtext"
						name="type"
						class="row"
						type="radio">
						{{ t('tables', 'Longtext') }}
					</CheckboxRadioSwitch>
					<CheckboxRadioSwitch :checked.sync="type"
						value="number"
						name="type"
						class="row"
						type="radio">
						{{ t('tables', 'Number') }}
					</CheckboxRadioSwitch>
				</div>

				<!-- type specific parameter -------------------------------- -->

				<div v-if="type === 'number'">
					<div class="row">
						<div class="col-4">
							<h3>{{ t('tables', 'Number column specific parameters') }}</h3>
						</div>
					</div>
					<NumberForm
						:number-default.sync="numberDefault"
						:number-min.sync="numberMin"
						:number-max.sync="numberMax"
						:number-decimals.sync="numberDecimals" />
				</div>

				<div v-if="type === 'textline' || type === 'longtext'">
					<div class="row">
						<div class="col-4">
							<h3>{{ t('tables', 'Text column specific parameters') }}</h3>
						</div>
					</div>
					<TextlineForm v-if="type === 'textline'"
						:text-default.sync="textDefault"
						:text-allowed-pattern.sync="textAllowedPattern"
						:text-max-length.sync="textMaxLength" />
					<LongtextForm v-if="type === 'longtext'"
						:text-default.sync="textDefault"
						:text-max-length.sync="textMaxLength" />
				</div>

				<div class="row">
					<div class="col-4 margin-bottom">
						<button class="secondary" @click="actionCancel">
							{{ t('tables', 'Cancel') }}
						</button>
						<button class="primary" @click="actionConfirm">
							{{ t('tables', 'Save') }}
						</button>
					</div>
				</div>
			</div>
		</div>
	</Modal>
</template>

<script>
import NumberForm from '../columnTypePartials/forms/NumberForm'
import TextlineForm from '../columnTypePartials/forms/TextlineForm'
import LongtextForm from '../columnTypePartials/forms/LongtextForm'
import MainForm from '../columnTypePartials/forms/MainForm'
import Modal from '@nextcloud/vue/dist/Components/Modal'
import CheckboxRadioSwitch from '@nextcloud/vue/dist/Components/CheckboxRadioSwitch'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError, showInfo, showSuccess } from '@nextcloud/dialogs'
import { mapGetters } from 'vuex'

export default {
	name: 'CreateColumn',
	components: {
		Modal,
		CheckboxRadioSwitch,
		NumberForm,
		TextlineForm,
		LongtextForm,
		MainForm,
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
			title: '',
			description: '',
			prefix: '',
			suffix: '',
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
		}
	},
	computed: {
		...mapGetters(['activeTable']),
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
				let type = this.type
				let textMultiline = false
				if (type === 'textline') {
					type = 'text'
				} else if (type === 'longtext') {
					type = 'text'
					textMultiline = true
				}
				const data = {
					type,
					title: this.title,
					description: this.description,
					prefix: this.prefix,
					suffix: this.suffix,
					orderWeight: this.orderWeight,
					mandatory: this.mandatory,
					numberDefault: this.numberDefault,
					numberMin: this.numberMin,
					numberMax: this.numberMax,
					numberDecimals: this.numberDecimals,
					textDefault: this.textDefault,
					textAllowedPattern: this.textAllowedPattern,
					textMaxLength: this.textMaxLength,
					textMultiline,
					tableId: this.activeTable.id,
				}
				// console.debug('try so send new column', data)
				await axios.post(generateUrl('/apps/tables/column'), data)
				showSuccess(t('tables', 'The column »{column}« was created.', { column: data.title }))
				await this.$store.dispatch('loadTablesFromBE')
			} catch (e) {
				console.error(e)
				showError(t('tables', 'Could not create new column'))
			}
		},
		reset() {
			this.type = null
			this.title = ''
			this.description = ''
			this.prefix = ''
			this.suffix = ''
			this.orderWeight = 0
			this.mandatory = false
			this.numberDefault = null
			this.numberMin = 0
			this.numberMax = 100
			this.numberDecimals = 2
			this.textDefault = ''
			this.textAllowedPattern = ''
			this.textMaxLength = null
			this.titleMissingError = false
			this.typeMissingError = false
		},
	},
}
</script>
