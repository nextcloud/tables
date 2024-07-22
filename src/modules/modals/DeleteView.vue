<template>
	<div>
		<DialogConfirmation :description="getTranslatedDescription"
			:title="t('tables', 'Confirm view deletion')"
			:cancel-title="t('tables', 'Cancel')"
			:confirm-title="t('tables', 'Delete')"
			confirm-class="error"
			:show-modal="showModal"
			@confirm="deleteMe"
			@cancel="$emit('cancel')" />
	</div>
</template>

<script>

import DialogConfirmation from '../../shared/modals/DialogConfirmation.vue'
import { showSuccess } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/style.css'
import { mapGetters } from 'vuex'

export default {
	components: {
		DialogConfirmation,
	},
	props: {
		view: {
			type: Object,
			default: null,
		},
		showModal: {
			type: Boolean,
			default: false,
		},
	},
	computed: {
		...mapGetters(['activeView', 'isView']),
		getTranslatedDescription() {
			return t('tables', 'Do you really want to delete the view "{view}"?', { view: this.view?.title })
		},
	},
	methods: {
		async deleteMe() {
			const viewId = this.view.id
			const activeViewId = this.activeView?.id
			const res = await this.$store.dispatch('removeView', { viewId: this.view.id })
			if (res) {
				showSuccess(t('tables', 'View "{emoji}{view}" removed.', { emoji: this.view.emoji ? this.view.emoji + ' ' : '', view: this.view.title }))

				// if the actual view was deleted, go to startpage
				if (viewId === activeViewId) {
					await this.$router.push('/').catch(err => err)
				}
				this.$emit('cancel')
			}
		},
	},
}
</script>
