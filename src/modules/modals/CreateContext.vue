<template>
	<NcModal v-if="showModal" size="normal" @close="actionCancel">
		<div class="modal__content">
			<div class="row">
				<div class="col-4">
					<h2>{{ t('tables', 'Create a context') }}</h2>
				</div>
			</div>
			<div>
				<div class="col-4 mandatory">
					{{ t('tables', 'Title') }}
				</div>
				<div class="row" style="display: inline-flex;">
					<NcEmojiPicker :close-on-select="true" @select="setIcon">
						<NcButton type="tertiary" :aria-label="t('tables', 'Select emoji for the context')"
							:title="t('tables', 'Select emoji')" @click.prevent>
							{{ icon }}
						</NcButton>
					</NcEmojiPicker>
					<input v-model="title" :class="{ missing: errorTitle }" type="text"
						:placeholder="t('tables', 'Title of the new context')" @input="titleChangedManually">
				</div>
				<div class="row">
						<div class="col-4 mandatory">
							{{ t('tables', 'Description') }}
						</div>
						<input v-model="description" type="text"
							:placeholder="t('tables', 'Description of the new context')">
					</div>
				<div class="row">
					<div>
						{{ t('tables', 'Resources') }}
					</div>
				</div>
				<NcContextResource :resources.sync="resources" />
			</div>
			<div class="row space-R">
				<div class="fix-col-4 end">
					<NcButton type="primary" :aria-label="t('tables', 'Create context')" @click="submit">
						{{ t('tables', 'Create context') }}
					</NcButton>
				</div>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { NcModal, NcEmojiPicker, NcButton } from '@nextcloud/vue'
import { showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/dist/index.css'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import NcTile from '../../shared/components/ncTile/NcTile.vue'
import displayError from '../../shared/utils/displayError.js'
import NcContextResource from '../../shared/components/ncContextResource/NcContextResource.vue'

export default {
	name: 'CreateContext',
	components: {
		NcModal,
		NcEmojiPicker,
		NcButton,
		NcContextResource,
	},
	props: {
		showModal: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			title: '',
			icon: '',
			customIconChosen: false,
			customTitleChosen: false,
			errorTitle: false,
			description: '',
			resources: [],
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
			// every time when the modal opens chose a new emoji
			this.loadEmoji()
		},
	},
	methods: {
		titleChangedManually() {
			this.customTitleChosen = true
		},
		setIcon(icon) {
			this.icon = icon
			this.customIconChosen = true
		},
		loadEmoji() {
			const emojis = ['😀', '😃', '😄', '😁', '😆', '😅', '🤣', '😂', '🙂', '🙃', '🫠', '😉', '😊', '😇']
			this.icon = emojis[~~(Math.random() * emojis.length)]
		},
		actionCancel() {
			this.reset()
			this.$emit('close')
		},
		async submit() {
			if (this.title === '') {
				showError(t('tables', 'Cannot create new context. Title is missing.'))
				this.errorTitle = true
			} else {
				const newContextId = await this.sendNewContextToBE()
				console.log('new context id', newContextId)
				if (newContextId) {
					await this.$router.push('/context/' + newContextId)
					this.actionCancel()
				}
			}
		},
		async sendNewContextToBE(e) {
			const dataResources = this.resources.map(resource => {
				return {
					id: parseInt(resource.id),
					type: parseInt(resource.nodeType),
					permissions: 660,
				}
			})
			const data = {
				name: this.title,
				iconName: this.icon,
				description: this.description,
				nodes: dataResources,
			}
			console.log('data to send', data)
			const res = await this.$store.dispatch('insertNewContext', { data })
			if (res) {
				return res.id
			} else {
				showError(t('tables', 'Could not create new table'))
			}
		},
		reset() {
			this.title = ''
			this.errorTitle = false
			this.icon = ''
			this.customIconChosen = false
			this.customTitleChosen = false
		},
	},
}
</script>

<style lang="scss" scoped>
.modal__content {
	padding-right: 0 !important;
}
</style>
