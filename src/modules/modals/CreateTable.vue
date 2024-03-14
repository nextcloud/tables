<template>
	<NcModal v-if="showModal" size="normal"
		@close="actionCancel">
		<div class="modal__content">
			<div class="row">
				<div class="col-4">
					<h2>{{ t('tables', 'Create table') }}</h2>
				</div>
			</div>
			<div class="row space-T">
				<div class="col-4 mandatory">
					{{ t('tables', 'Title') }}
				</div>
				<div class="col-4" style="display: inline-flex;">
					<NcEmojiPicker :close-on-select="true" @select="setIcon">
						<NcButton type="tertiary"
							:aria-label="t('tables', 'Select emoji for table')"
							:title="t('tables', 'Select emoji')"
							@click.prevent>
							{{ icon }}
						</NcButton>
					</NcEmojiPicker>
					<input v-model="title"
						:class="{missing: errorTitle}"
						type="text"
						:placeholder="t('tables', 'Title of the new table')"
						@input="titleChangedManually">
				</div>
			</div>
			<div class="row space-T">
				<div class="col-2 block space-R space-B">
					<NcTile
						:title="t('tables', '🔧 Custom table')"
						:body="t('tables', 'Custom table from scratch.')"
						:active="templateChoice === 'custom'"
						:tabbable="true"
						@set-template="setTemplate('custom')" />
				</div>
				<div v-for="template in templates" :key="template.name" class="col-2 block space-R space-B">
					<NcTile
						:title="template.icon + ' ' + template.title"
						:body="template.description"
						:active="templateChoice === template.name"
						:tabbable="true"
						@set-template="setTemplate(template.name)" />
				</div>
			</div>
			<div class="row space-R">
				<div class="fix-col-4 end">
					<NcButton type="primary" :aria-label="t('tables', 'Create table')" @click="submit">
						{{ t('tables', 'Create table') }}
					</NcButton>
				</div>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { NcModal, NcEmojiPicker, NcButton } from '@nextcloud/vue'
import { showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/style.css'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import NcTile from '../../shared/components/ncTile/NcTile.vue'
import displayError from '../../shared/utils/displayError.js'

export default {
	name: 'CreateTable',
	components: {
		NcModal,
		NcEmojiPicker,
		NcButton,
		NcTile,
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
			errorTitle: false,
			templates: null,
			templateChoice: 'custom',
			customIconChosen: false,
			customTitleChosen: false,
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
	beforeMount() {
		this.loadTemplatesFromBE()
	},
	methods: {
		titleChangedManually() {
			this.customTitleChosen = true
		},
		setIcon(icon) {
			this.icon = icon
			this.customIconChosen = true
		},
		setTemplate(name) {
			this.templateChoice = name

			if (!this.customIconChosen) {
				if (name === 'custom') {
					this.icon = '🔧'
				} else {
					const templateObject = this.templates?.find(item => item.name === name) || ''
					this.icon = templateObject?.icon
				}
			}

			if (!this.customTitleChosen) {
				if (name === 'custom') {
					this.title = ''
				} else {
					const templateObject = this.templates?.find(item => item.name === name) || ''
					this.title = templateObject?.title || ''
				}
			}
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
				showError(t('tables', 'Cannot create new table. Title is missing.'))
				this.errorTitle = true
			} else {
				const newTableId = await this.sendNewTableToBE(this.templateChoice)
				if (newTableId) {
					await this.$router.push('/table/' + newTableId)
					this.actionCancel()
				}
			}
		},
		async sendNewTableToBE(template) {
			const data = {
				title: this.title,
				emoji: this.icon,
				template,
			}
			const res = await this.$store.dispatch('insertNewTable', { data })
			if (res) {
				return res.id
			} else {
				showError(t('tables', 'Could not create new table'))
			}
		},
		reset() {
			this.title = ''
			this.errorTitle = false
			this.templateChoice = 'custom'
			this.icon = ''
			this.customIconChosen = false
			this.customTitleChosen = false
		},
		async loadTemplatesFromBE() {
			try {
				const res = await axios.get(generateUrl('/apps/tables/table/templates'))
				this.templates = res.data
			} catch (e) {
				displayError(e, t('tables', 'Could not load templates.'))
			}
		},
	},
}
</script>
<style lang="scss" scoped>

.modal__content {
	padding-right: 0 !important;
}

</style>
