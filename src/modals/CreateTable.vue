<template>
	<Modal
		v-if="showModal"
		@close="actionCancel">
		<div class="modal__content">
			<div class="row">
				<div class="col-4">
					<h2>Add table</h2>
				</div>
				<div class="col-4">
					<p>{{ t('tables', 'Please choose one of the templates or create a table from scratch.') }}</p>
				</div>
			</div>
			<div class="row space-T">
				<div class="col-4 mandatory">
					{{ t('tables', 'Title') }}
				</div>
				<div class="col-2">
					<input v-model="title"
						:class="{missing: errorTitle}"
						type="text"
						:placeholder="t('tables', 'Title of the new table')">
				</div>
				<div class="col-2">
					<EmojiPicker @select="addIconToTitle">
						<button>{{ t('tables', 'Add an icon as prefix') }}</button>
					</EmojiPicker>
				</div>
			</div>
			<div class="row space-T">
				<div class="box-1" style="min-height:180px;">
					<div class="icon-category-customization icon-left">
						<div class="header">
							{{ t('tables', 'Custom table') }}
						</div>
					</div>
					<p>
						{{ t('tables', 'Custom table from scratch.') }}
					</p>
					<button @click="submit('custom')">
						{{ t('tables', 'Create table') }}
					</button>
				</div>

				<!-- templates boxes -->
				<div v-for="template in templates"
					:key="template.name"
					class="box-1"
					style="min-height:180px;">
					<div class="icon-left" :class="template.icon">
						<div class="header">
							{{ template.title }}
						</div>
					</div>
					<p>
						{{ template.description }}
					</p>
					<button @click="submit(template.name)">
						{{ t('tables', 'Create table') }}
					</button>
				</div>
			</div>
		</div>
	</Modal>
</template>

<script>
import Modal from '@nextcloud/vue/dist/Components/Modal'
import { showError, showSuccess, showWarning } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import EmojiPicker from '@nextcloud/vue/dist/Components/EmojiPicker'

export default {
	name: 'CreateTable',
	components: {
		Modal,
		EmojiPicker,
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
		}
	},
	watch: {
		title(val) {
			if (this.title.length >= 200) {
				showError(t('tables', 'The title limit is reached with 200 characters. Please use a shorter title.'))
				this.title = this.title.slice(0, 199)
			}
		},
	},
	beforeMount() {
		this.loadTemplatesFromBE()
	},
	methods: {
		actionCancel() {
			this.reset()
			this.$emit('close')
		},
		async submit(template) {
			if (this.title === '') {
				showError(t('tables', 'Cannot create new table. Title is missing.'))
				this.errorTitle = true
			} else {
				const newTableId = await this.sendNewTableToBE(template)
				if (newTableId) {
					await this.$router.push('/table/' + newTableId)
					showSuccess(t('tables', 'The table "{table}" was created.', { table: this.title }))
				}
				this.actionCancel()
			}
		},
		async sendNewTableToBE(template) {
			let ret = null
			try {
				const data = {
					title: this.title,
					template,
				}
				const res = await axios.post(generateUrl('/apps/tables/table'), data)
				if (res.status !== 200) {
					showWarning(t('tables', 'Sorry, something went wrong.'))
					console.debug('axios error', res)
					return false
				}
				ret = res.data.id
				await this.$store.dispatch('loadTablesFromBE')
			} catch (e) {
				console.error(e)
				showError(t('tables', 'Could not create new table'))
			}
			return ret
		},
		reset() {
			this.title = ''
			this.errorTitle = false
		},
		addIconToTitle(icon) {
			this.title = icon + ' ' + this.title
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
