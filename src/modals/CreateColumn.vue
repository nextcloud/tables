<template>
	<Modal v-if="showModal" @close="actionCancel">
		<div class="modal__content">
			<div class="row">
				<div class="col-4">
					<h2>{{ t('tables', 'Create column') }}</h2>
				</div>

				<div class="fix-col-1 mandatory">
					{{ t('tables', 'Title') }}
				</div>
				<div class="fix-col-3 margin-bottom">
					<input v-model="title" :placeholder="t('tables', 'Title for the column.')">
				</div>

				<div class="fix-col-1">
					{{ t('tables', 'Description') }}
				</div>
				<div class="fix-col-3 margin-bottom">
					<textarea v-model="description" />
				</div>

				<div class="fix-col-1">
					{{ t('tables', 'prefix') }}
				</div>
				<div class="fix-col-3 margin-bottom">
					<input v-model="prefix">
				</div>

				<div class="col-1">
					{{ t('tables', 'suffix') }}
					<Popover>
						<template #trigger>
							<button class="icon-details" />
						</template>
						<p>
							{{ t('tables', 'Here is a good place to put your unit for example.') }}
						</p>
					</Popover>
				</div>
				<div class="fix-col-3 margin-bottom">
					<input v-model="suffix">
				</div>

				<div class="col-1">
					{{ t('tables', 'mandatory') }}
					<Popover>
						<template #trigger>
							<button class="icon-details" />
						</template>
						<p>
							{{ t('tables', 'Check if this field is mandatory. If so, it will be required in every form.') }}
						</p>
					</Popover>
				</div>
				<div class="fix-col-3 margin-bottom">
					<CheckboxRadioSwitch type="switch" :checked.sync="mandatory" />
				</div>

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
				<div v-if="type === 'number'">
					<div class="row">
						<div class="col-4">
							<h3>{{ t('tables', 'Number column specific parameters') }}</h3>
						</div>
					</div>

					<!-- default -->
					<div class="row">
						<div class="fix-col-1">
							{{ t('tables', 'Default') }}
						</div>
						<div class="fix-col-1">
							&nbsp;
						</div>
						<div class="fix-col-2 margin-bottom">
							<input v-model="numberDefault" type="number">
						</div>
					</div>

					<!-- decimals -->
					<div class="row">
						<div class="fix-col-1">
							{{ t('tables', 'Decimals') }}
						</div>
						<div class="fix-col-1">
							&nbsp;
						</div>
						<div class="fix-col-2 margin-bottom">
							<input v-model="numberDecimals" type="number">
						</div>
					</div>

					<!-- min -->
					<div class="row">
						<div class="fix-col-1">
							{{ t('tables', 'Minimum') }}
						</div>
						<div class="fix-col-1">
							&nbsp;
						</div>
						<div class="fix-col-2 margin-bottom">
							<input v-model="numberMin" type="number">
						</div>
					</div>

					<!-- max -->
					<div class="row">
						<div class="fix-col-1">
							{{ t('tables', 'Maximum') }}
						</div>
						<div class="fix-col-1">
							&nbsp;
						</div>
						<div class="fix-col-2 margin-bottom">
							<input v-model="numberMax" type="number">
						</div>
					</div>
				</div>

				<div v-if="type === 'textline' || type === 'longtext'">
					<div class="row">
						<div class="col-4">
							<h3>{{ t('tables', 'Text column specific parameters') }}</h3>
						</div>
					</div>

					<!-- default -->
					<div class="row">
						<div class="fix-col-1">
							{{ t('tables', 'Default') }}
						</div>
						<div v-if="type === 'textline'" class="fix-col-3 margin-bottom">
							<input v-model="textDefault">
						</div>
						<div v-if="type === 'longtext'" class="fix-col-3 margin-bottom">
							<textarea v-model="textDefault" />
						</div>
					</div>

					<!-- allowed pattern -->
					<div class="row">
						<div class="fix-col-1">
							{{ t('tables', 'Allowed pattern (regex)') }}
						</div>
						<div class="fix-col-3 margin-bottom">
							<input v-model="textAllowedPattern">
						</div>
					</div>

					<!-- max text length -->
					<div class="row">
						<div class="fix-col-1">
							{{ t('tables', 'Maximum text length') }}
						</div>
						<div class="fix-col-1">
							&nbsp;
						</div>
						<div class="fix-col-2 margin-bottom">
							<input v-model="textMaxLength">
						</div>
					</div>
				</div>

				<div class="row">
					<div class="col-4 margin-bottom">
						<button @click="actionCancel">
							{{ t('tables', 'Cancel') }}
						</button>
						<button class="success" @click="actionConfirm">
							{{ t('tables', 'Save') }}
						</button>
					</div>
				</div>
			</div>
		</div>
	</Modal>
</template>

<script>
import Modal from '@nextcloud/vue/dist/Components/Modal'
import CheckboxRadioSwitch from '@nextcloud/vue/dist/Components/CheckboxRadioSwitch'
import Popover from '@nextcloud/vue/dist/Components/Popover'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError, showInfo, showSuccess } from '@nextcloud/dialogs'
import { mapGetters } from 'vuex'

export default {
	name: 'CreateColumn',
	components: {
		Modal,
		CheckboxRadioSwitch,
		Popover,
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
			mandatory: false,
			numberDefault: null,
			numberMin: 0,
			numberMax: 100,
			numberDecimals: 2,
			textDefault: '',
			textAllowedPattern: '',
			textMaxLength: null,
			typeMissingError: false,
		}
	},
	computed: {
		...mapGetters(['activeTable']),
	},
	methods: {
		async actionConfirm() {
			console.debug('try to submit new column', null)
			if (this.type === null) {
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
			this.mandatory = false
			this.numberDefault = null
			this.numberMin = 0
			this.numberMax = 100
			this.numberDecimals = 2
			this.textDefault = ''
			this.textAllowedPattern = ''
			this.textMaxLength = null
		},
	},
}
</script>
