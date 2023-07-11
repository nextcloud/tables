<template>
	<NcModal v-if="showModal"
		size="normal"
		@close="actionCancel">
		<div class="modal__content">
			<div class="row">
				<div class="col-4">
					<h2>{{ t('tables', 'Edit view') }}</h2>
				</div>
			</div>
			<div class="row">
				<div class="col-4 mandatory">
					{{ t('tables', 'Title') }}
				</div>
				<div class="col-3 inline">
					<NcEmojiPicker :close-on-select="true" @select="emoji => icon = emoji">
						<NcButton type="tertiary"
							:aria-label="t('tables', 'Select emoji for view')"
							:title="t('tables', 'Select emoji')"
							@click.prevent>
							{{ icon ? icon : '...' }}
						</NcButton>
					</NcEmojiPicker>
					<input v-model="title"
						:class="{missing: errorTitle}"
						type="text"
						:placeholder="t('tables', 'Title of the new view')">
				</div>
			</div>
			<div class="row">
				<div class="fix-col-4 space-T justify-between">
					<NcButton v-if="!prepareDelete" :aria-label="t('tables', 'Delete')" type="error" @click="prepareDelete = true">
						{{ t('tables', 'Delete') }}
					</NcButton>
					<NcButton v-if="prepareDelete"
						:aria-label="t('tables', 'I really want to delete this view!')"
						:wide="true"
						type="error"
						@click="actionDeleteView">
						{{ t('tables', 'I really want to delete this view!') }}
					</NcButton>
					<NcButton type="primary" :aria-label="t('tables', 'Save')" @click="submit">
						{{ t('tables', 'Save') }}
					</NcButton>
				</div>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { NcModal, NcEmojiPicker, NcButton } from '@nextcloud/vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/dist/index.css'
import { mapGetters } from 'vuex'

export default {
	name: 'EditViewTitle',
	components: {
		NcModal,
		NcEmojiPicker,
		NcButton,
	},
	props: {
		showModal: {
			type: Boolean,
			default: false,
		},
		view: {
			type: Object,
			default: null,
		},
	},
	data() {
		return {
			title: '',
			icon: '',
			errorTitle: false,
			prepareDelete: false,
		}
	},
	computed: {
		...mapGetters(['activeView']),
	},
	watch: {
		title() {
			if (this.title && this.title.length >= 200) {
				showError(t('tables', 'The title limit is reached with 200 characters. Please use a shorter title.'))
				this.title = this.title.slice(0, 199)
			}
		},
		view() {
			this.reset()
		},
	},
	methods: {
		actionCancel() {
			this.$emit('close')
		},
		async submit() {
			if (this.title === '') {
				showError(t('tables', 'Cannot update view. Title is missing.'))
				this.errorTitle = true
			} else {
				const res = await this.$store.dispatch('updateView', { id: this.view.id, data: { data: { title: this.title, emoji: this.icon } } })
				if (res) {
					showSuccess(t('tables', 'Updated view "{emoji}{view}".', { emoji: this.icon ? this.icon + ' ' : '', view: this.title }))
					this.actionCancel()
				}
			}
		},
		reset() {
			if (this.view) {
				this.title = this.view.title
				this.icon = this.view.emoji
				this.errorTitle = false
				this.prepareDelete = false
			}
		},
		async actionDeleteView() {
			const deleteId = this.view.id
			const activeViewId = this.activeView.id
			this.prepareDelete = false
			const res = await this.$store.dispatch('removeView', { viewId: this.view.id })
			if (res) {
				showSuccess(t('tables', 'View "{emoji}{view}" removed.', { emoji: this.icon ? this.icon + ' ' : '', view: this.title }))

				// if the actual view was deleted, go to startpage
				if (deleteId === activeViewId) {
					await this.$router.push('/').catch(err => err)
				}

				this.actionCancel()
			}

		},
	},
}
</script>
