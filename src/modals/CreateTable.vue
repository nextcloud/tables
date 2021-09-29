<template>
	<div>
		<AppNavigationNew
			:text="t('tables', 'New table')"
			:disabled="false"
			button-class="icon-add"
			@click="showModal = true" />
		<Modal
			v-if="showModal"
			@close="showModal = false">
			<div class="modal__content">
				<div class="row">
					<div class="col-4">
						<h2>Add table</h2>
					</div>
					<div class="col-4">
						<p>{{ t('tables', 'Please choose one of the templates or create a table from scratch.') }}</p>
					</div>
				</div>
				<div class="row">
					<div class="col-4 mandatory">
						{{ t('tables', 'Title') }}
					</div>
					<div class="col-3">
						<input v-model="title"
							:class="{missing: errorTitle}"
							type="text"
							:placeholder="t('tables', 'Title of the new table')">
					</div>
				</div>
				<div class="row">
					<div class="box-1">
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
				</div>
			</div>
		</Modal>
	</div>
</template>

<script>
import AppNavigationNew from '@nextcloud/vue/dist/Components/AppNavigationNew'
import Modal from '@nextcloud/vue/dist/Components/Modal'
import { showError, showSuccess } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'CreateTable',
	components: {
		AppNavigationNew,
		Modal,
	},
	data() {
		return {
			showModal: true,
			title: '',
			errorTitle: false,
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
	methods: {
		async submit(template) {
			console.debug('try to add table from template', template)
			if (this.title === '') {
				showError(t('tables', 'Can not create new table. Title is missing.'))
				this.errorTitle = true
			} else {
				console.debug('submit okay, try to send to BE')
				await this.sendNewTableToBE()
				this.showModal = false
				showSuccess(t('tables', 'The table $table was created.', { table: this.title }))
				this.reset()
			}
		},
		async sendNewTableToBE() {
			try {
				const data = {
					title: this.title,
				}
				const response = await axios.post(generateUrl('/apps/tables/table'), data)
				console.debug('table created: ', response)
				// eslint-disable-next-line vue/custom-event-name-casing
				this.$emit('updateTables')
			} catch (e) {
				console.error(e)
				showError(t('tables', 'Could not create new table'))
			}
		},
		reset() {
			this.title = ''
			this.errorTitle = false
		},
	},
}
</script>
