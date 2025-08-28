<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog v-if="showModal" size="normal" close-on-click-outside :name="t('tables', 'Import scheme')"
		@closing="actionCancel">
		<div class="modal__content">
			<NcButton :aria-label="t('tables', 'Upload from device')" @click="selectUploadFile">
				<template #icon>
					<IconUpload :size="20" />
				</template>
				{{ t('tables', 'Upload from device') }}
			</NcButton>
			<input ref="uploadFileInput" type="file" aria-hidden="true" class="hidden-visually"
				:accept="mimeTypes.join(',')" @change="onUploadFileInputChange">
			<span v-if="selectedUploadFile">{{ selectedUploadFile?.name }}</span>
			<div class="row">
				<div class="fix-col-4 end">
					<NcButton :aria-label="t('tables', 'Import')" type="primary" @click="actionSubmit">
						{{ t('tables', 'Import') }}
					</NcButton>
				</div>
			</div>
		</div>
	</NcDialog>
</template>
<script>
import { NcDialog, NcButton } from '@nextcloud/vue'
import IconUpload from 'vue-material-design-icons/TrayArrowUp.vue'
import axios from '@nextcloud/axios'
import { showError, showWarning } from '@nextcloud/dialogs'
import { generateOcsUrl } from '@nextcloud/router'
import { mapActions } from 'pinia'
import { useTablesStore } from '../../store/store.js'

export default {
	components: {
		NcDialog,
		IconUpload,
		NcButton,
	},
	props: {
		showModal: {
			type: Boolean,
			default: false,
		},
		title: {
			type: String,
			default: '',
		},
	},
	data() {
		return {
			mimeTypes: [
				'application/json',
			],
			selectedUploadFile: null,
		}
	},
	methods: {
		...mapActions(useTablesStore, ['loadTablesFromBE', 'loadViewsSharedWithMeFromBE']),
		actionCancel() {
			this.$emit('close')
		},
		actionSubmit() {
			if (this.selectedUploadFile && this.selectedUploadFile.type !== '' && !this.mimeTypes.includes(this.selectedUploadFile.type)) {
				showWarning(t('tables', 'The selected file is not supported.'))
				return null
			}
			if (this.selectedUploadFile) {
				this.uploadFile()
			}
		},
		uploadFile() {
			const reader = new FileReader()
			reader.readAsText(this.selectedUploadFile, 'UTF-8')
			reader.onload = (evt) => {
				const json = JSON.parse(evt.target.result)
				if (this.title !== '') {
					json.title = this.title
				}
				axios.post(generateOcsUrl('/apps/tables/api/2/tables/scheme'), json).then(async res => {
					if (res.status === 200) {
						await this.loadTablesFromBE()
						await this.loadViewsSharedWithMeFromBE()
						this.actionCancel()
						return
					}
					showError(t('tables', res.data?.message || 'Could not import data due to unknown errors.'))
				})
			}

		},
		selectUploadFile() {
			this.$refs.uploadFileInput.click()
		},
		onUploadFileInputChange(event) {
			this.selectedUploadFile = event.target.files[0]
		},
	},
}
</script>
