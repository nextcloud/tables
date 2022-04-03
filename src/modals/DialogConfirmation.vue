<template>
	<Modal v-if="showModal" @close="actionCancel">
		<div class="modal__content">
			<div class="row">
				<div v-if="title" class="col-4">
					<h2>{{ title }}</h2>
				</div>
				<div v-if="description" class="col-4">
					<p>{{ description }}</p>
				</div>
			</div>
			<div class="row space-T">
				<div class="col-4 col-stretch-elements">
					<button :class="{ error: cancelClass === 'error', success: cancelClass === 'success', warning: cancelClass === 'warning' }" @click="actionCancel">
						{{ cancelTitle }}
					</button>
					<button :class="{ error: confirmClass === 'error', success: confirmClass === 'success', warning: confirmClass === 'warning' }" @click="actionConfirm">
						{{ confirmTitle }}
					</button>
				</div>
			</div>
		</div>
	</Modal>
</template>

<script>
import Modal from '@nextcloud/vue/dist/Components/Modal'

export default {
	name: 'DialogConfirmation',
	components: {
		Modal,
	},
	props: {
		showModal: {
			type: Boolean,
			default: false,
		},
		title: {
			type: String,
			default: t('tables', 'Confirmation'),
		},
		description: {
			type: String,
			default: null,
		},
		confirmTitle: {
			type: String,
			default: t('tables', 'Confirm'),
		},
		confirmClass: {
			type: String,
			default: 'success', // error, warning, success
		},
		cancelTitle: {
			type: String,
			default: t('tables', 'Cancel'),
		},
		cancelClass: {
			type: String,
			default: null, // error, warning, success
		},
	},
	methods: {
		actionConfirm() {
			this.$emit('confirm')
		},
		actionCancel() {
			this.$emit('cancel')
		},
	},
}
</script>
<style scoped>

	.modal__content {
		padding: 20px;
		min-height: inherit;
		max-height: inherit;
	}

</style>
