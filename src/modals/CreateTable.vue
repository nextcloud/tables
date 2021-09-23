<template>
	<div>
		<AppNavigationNew
			:text="t('tables', 'New table')"
			:disabled="false"
			button-class="icon-add"
			@click="showModal = true" />
		<Modal
			v-if="showModal"
			@close="showModal = false" />
	</div>
</template>

<script>
import AppNavigationNew from '@nextcloud/vue/dist/Components/AppNavigationNew'
import Modal from '@nextcloud/vue/dist/Components/Modal'

export default {
	name: 'CreateTable',
	components: {
		AppNavigationNew,
		Modal,
	},
	data() {
		return {
			showModal: false,
		}
	},
}
</script>
