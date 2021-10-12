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

				<div class="fix-col-1 mandatory">
					{{ t('tables', 'Type') }}
				</div>
				<div class="fix-col-3 margin-bottom">
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
				<div v-if="type === 'number'" class="row">
					<div class="fix-col-1">
						{{ t('tables', 'Default') }}
					</div>
					<div class="fix-col-3 margin-bottom">
						<input v-model="numberDefault" type="number">
					</div>

					<div class="fix-col-1">
						{{ t('tables', 'Decimals') }}
					</div>
					<div class="fix-col-3 margin-bottom">
						<input v-model="numberDecimals" type="number">
					</div>

					<div class="fix-col-1">
						{{ t('tables', 'Minimum') }}
					</div>
					<div class="fix-col-3 margin-bottom">
						<input v-model="numberMin" type="number">
					</div>

					<div class="fix-col-1">
						{{ t('tables', 'Maximum') }}
					</div>
					<div class="fix-col-3 margin-bottom">
						<input v-model="numberMax" type="number">
					</div>
				</div>

				<div v-if="type === 'textline' || type === 'longtext'">
					<div class="fix-col-1">
						{{ t('tables', 'Default') }}
					</div>
					<div v-if="type === 'textline'" class="fix-col-3 margin-bottom">
						<input v-model="textDefault">
					</div>
					<div v-if="type === 'longtext'" class="fix-col-3 margin-bottom">
						<textarea v-model="textDefault" />
					</div>

					<div class="fix-col-1">
						{{ t('tables', 'Allowed pattern (regex)') }}
					</div>
					<div class="fix-col-3 margin-bottom">
						<input v-model="textAllowedPattern">
					</div>

					<div class="fix-col-1">
						{{ t('tables', 'Maximum text length') }}
					</div>
					<div class="fix-col-3 margin-bottom">
						<input v-model="textMaxLength">
					</div>
				</div>

				<div class="row">
					<div class="col-4 margin-bottom">
						<button>{{ t('tables', 'Cancel') }}</button>
						<button class="success">
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
		}
	},
	methods: {
		actionConfirm() {
			this.$emit('submit')
		},
		actionCancel() {
			this.$emit('close')
		},
	},
}
</script>
