<template>
	<NcModal v-if="showModal"
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
				<div class="col-3" style="display: inline-flex;">
					<NcEmojiPicker :close-on-select="true" @select="emoji => icon = emoji">
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
						:placeholder="t('tables', 'Title of the new table')">
				</div>
			</div>
			<div class="row space-T">
				<div class="box-2" style="height:120px;">
					<div class="header">
						<NcCheckboxRadioSwitch name="template"
							type="radio"
							value="custom"
							:checked.sync="templateChoice">
							{{ t('tables', '🔧 Custom table') }}
						</NcCheckboxRadioSwitch>
					</div>
					<p>
						{{ t('tables', 'Custom table from scratch.') }}
					</p>
				</div>

				<!-- templates boxes -->
				<div v-for="template in templates"
					:key="template.name"
					class="box-2"
					style="height:120px; overflow: auto;">
					<div class="header">
						<NcCheckboxRadioSwitch name="template"
							type="radio"
							:value="template.name"
							:checked.sync="templateChoice"
							@update:checked="icon = template.icon">
							{{ template.icon + ' ' + template.title }}
						</NcCheckboxRadioSwitch>
					</div>
					<p>
						{{ template.description }}
					</p>
				</div>
			</div>
			<div class="row">
				<div class="fix-col-4 space-B space-T">
					<NcButton type="secondary" @click="$emit('close')">
						{{ t('tables', 'Cancel') }}
					</NcButton>
        &nbsp;&nbsp;
					<NcButton type="primary" @click="submit">
						{{ t('tables', 'Create table') }}
					</NcButton>
				</div>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { NcModal, NcEmojiPicker, NcButton, NcCheckboxRadioSwitch } from '@nextcloud/vue'
import { showError, showWarning } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/dist/index.css'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'CreateTable',
	components: {
		NcModal,
		NcEmojiPicker,
		NcButton,
		NcCheckboxRadioSwitch,
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
					// showSuccess(t('tables', 'The table "{emoji} {table}" is ready to use.', { emoji: this.icon, table: this.title }))
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
				return res
			} else {
				showError(t('tables', 'Could not create new table'))
			}
		},
		reset() {
			this.title = ''
			this.errorTitle = false
			this.templateChoice = 'custom'
			this.icon = ''
		},
		async loadTemplatesFromBE() {
			try {
				const res = await axios.get(generateUrl('/apps/tables/table/templates'))
				if (res.status !== 200) {
					showWarning(t('tables', 'Sorry, something went wrong.'))
					console.debug('axios error', res)
				}
				this.templates = res.data
			} catch (e) {
				console.error(e)
				showError(t('tables', 'Could not fetch templates from backend'))
			}
		},
	},
}
</script>
