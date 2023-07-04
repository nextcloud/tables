<template>
	<NcModal v-if="showModal" @close="actionCancel">
		<div class="modal__content">
			<div class="row">
				<div class="col-4">
					<h2 style="padding: 0">
						{{ t('tables', 'Create view') }}
					</h2>
				</div>
			</div>

			<div class="row space-T">
				<div class="col-4 mandatory">
					{{ t('tables', 'Title') }}
				</div>
				<div class="col-4" style="display: inline-flex;">
					<NcEmojiPicker :close-on-select="true" @select="setIcon">
						<NcButton type="tertiary"
							:aria-label="t('tables', 'Select emoji for view')"
							:title="t('tables', 'Select emoji')"
							@click.prevent>
							{{ icon }}
						</NcButton>
					</NcEmojiPicker>
					<input v-model="title"
						:class="{missing: errorTitle}"
						type="text"
						:placeholder="t('tables', 'Title of the new view')">
				</div>
			</div>
			<div class="row">
				<div class="fix-col-4 space-T end">
					<button v-if="!localLoading" class="primary" :aria-label="t('tables', 'Save View')" @click="actionConfirm()">
						{{ t('tables', 'Save') }}
					</button>
				</div>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { NcModal, NcEmojiPicker, NcButton } from '@nextcloud/vue'
import { showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/dist/index.css'

export default {
	name: 'CreateView',
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
		tableId: {
			type: Number,
			default: null,
		},
	},
	data() {
		return {
			title: '',
			icon: '',
			errorTitle: false,
			localLoading: false,
		}
	},
	watch: {
		title() {
			if (this.title.length >= 200) {
				showError(t('tables', 'The title limit is reached with 200 characters. Please use a shorter title.'))
				this.title = this.title.slice(0, 199)
			}
		},
		showModal() {
			this.reset()
			this.loadEmoji()
		},
	},
	methods: {
		setIcon(icon) {
			this.icon = icon
		},
		loadEmoji() {
			const emojis = ['😀', '😃', '😄', '😁', '😆', '😅', '🤣', '😂', '🙂', '🙃', '🫠', '😉', '😊', '😇']
			this.icon = emojis[~~(Math.random() * emojis.length)]
		},
		actionCancel() {
			this.reset()
			this.$emit('close')
			console.debug('Closed')
		},
		async actionConfirm() {
			if (this.title === '') {
				showError(t('tables', 'Cannot create new view. Title is missing.'))
				this.errorTitle = true
			} else {
				this.localLoading = true
				const newViewId = await this.sendNewViewToBE()
				this.localLoading = false
				if (newViewId) {
					await this.$router.push('/view/' + newViewId)
					this.actionCancel()
				}
			}
		},
		async sendNewViewToBE() {
			const data = {
				tableId: this.tableId,
				title: this.title,
				emoji: this.icon,
			}
			const res = await this.$store.dispatch('insertNewView', { data })
			if (res) {
				return res
			} else {
				showError(t('tables', 'Could not create new view'))
			}
		},
		reset() {
			this.title = ''
			this.icon = ''
			this.errorTitle = false
			this.localLoading = false
		},
	},
}
</script>
<style lang="scss" scoped>

.padding-right {
	padding-right: calc(var(--default-grid-baseline) * 3);
}

</style>
