<template>
	<NcModal v-if="showModal" @close="$emit('cancel')">
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
				<div class="col-4 col-stretch-elements" style="display: inline-flex;">
					<NcButton :type="cancelClass"
						@click="$emit('cancel')">
						{{ cancelTitle }}
					</NcButton>
          &nbsp;&nbsp;
					<NcButton :type="confirmClass" @click="$emit('confirm')">
						{{ confirmTitle }}
					</NcButton>
				</div>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { NcModal, NcButton } from '@nextcloud/vue'

export default {
	name: 'DialogConfirmation',
	components: {
		NcModal,
		NcButton,
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
			default: 'success', // primary, secondary, tertiary, tertiary-no-background, tertiary-on-primary, error, warning, success
		},
		cancelTitle: {
			type: String,
			default: t('tables', 'Cancel'),
		},
		cancelClass: {
			type: String,
			default: 'secondary', // primary, secondary, tertiary, tertiary-no-background, tertiary-on-primary, error, warning, success
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
