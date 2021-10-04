<template>
	<Modal v-if="showModal" @close="actionCancel">
		<div class="modal__content">
			<div class="row">
				<div class="col-4">
					<h2>{{ t('tables', 'Create column') }}</h2>
				</div>
				<div class="col-2">
					<div class="row">
						<div class="fix-col-4">
							{{ t('tables', 'Title') }}
						</div>
						<div class="fix-col-4 margin-bottom">
							<input v-model="title" :placeholder="t('tables', 'Title for the column.')">
						</div>
						<div class="fix-col-4">
							{{ t('tables', 'Description') }}
						</div>
						<div class="fix-col-4 margin-bottom">
							<textarea v-model="description" />
						</div>
						<div class="fix-col-4">
							<div class="row">
								<div class="col-1">
									{{ t('tables', 'prefix') }}
								</div>
								<div class="fix-col-3 margin-bottom">
									<input v-model="prefix">
								</div>
								<div class="col-1">
									{{ t('tables', 'suffix') }}
								</div>
								<div class="fix-col-3 margin-bottom">
									<input v-model="suffix">
								</div>
							</div>
						</div>
						<div class="fix-col-4 margin-bottom">
							<CheckboxRadioSwitch type="switch" :checked.sync="mandatory">
								{{ t('tables', 'Mandatory') }}
							</CheckboxRadioSwitch>
						</div>
					</div>
				</div>
				<div class="col-2">
					<div class="row">
						<div class="fix-col-4">
							{{ t('tables', 'Type') }}
						</div>
						<div class="fix-col-4 margin-bottom">
							<div class="fix-col-4">
								<CheckboxRadioSwitch :checked.sync="type"
									value="textline"
									name="type"
									type="radio">
									{{ t('tables', 'Textline') }}
								</CheckboxRadioSwitch>
							</div>
							<div class="fix-col-4">
								<CheckboxRadioSwitch :checked.sync="type"
									value="longtext"
									name="type"
									type="radio">
									{{ t('tables', 'Longtext') }}
								</CheckboxRadioSwitch>
							</div>
							<div class="fix-col-4">
								<CheckboxRadioSwitch :checked.sync="type"
									value="number"
									name="type"
									type="radio">
									{{ t('tables', 'Number') }}
								</CheckboxRadioSwitch>
							</div>
						</div>
					</div>
					<div v-if="type === 'Number'" class="row">
						<div class="col-4">
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
					</div>
				</div>
				<div class="col-4">
					<button>{{ t('tables', 'Cancel') }}</button>
					<button class="success">
						{{ t('tables', 'Go') }}
					</button>
				</div>
			</div>
		</div>
	</Modal>
</template>

<script>
import Modal from '@nextcloud/vue/dist/Components/Modal'
import CheckboxRadioSwitch from '@nextcloud/vue/dist/Components/CheckboxRadioSwitch'

export default {
	name: 'CreateColumn',
	components: {
		Modal,
		CheckboxRadioSwitch,
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
			this.$emit('cancel')
		},
	},
}
</script>
