<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog v-if="showModal" size="normal" :name="t('tables', 'Import structure')" @closing="actionCancel">
		<div class="modal__content">
			<div class="row space-T">
				{{ t('tables', 'Select a table structure JSON file to preview and apply changes.') }}
			</div>

			<div v-if="selectedFileName" class="row import-filename">
				<IconFile :size="20" />{{ selectedFileName }}
			</div>

			<div class="row space-T">
				<div class="fix-col-4 middle">
					<NcButton :aria-label="t('tables', 'Select from Files')" @click="pickFile">
						<template #icon>
							<IconFolder :size="20" />
						</template>
						{{ t('tables', 'Select from Files') }}
					</NcButton>
					<NcButton :aria-label="t('tables', 'Upload from device')" @click="selectUploadFile">
						<template #icon>
							<IconUpload :size="20" />
						</template>
						{{ t('tables', 'Upload from device') }}
					</NcButton>
					<input ref="uploadFileInput"
						type="file"
						aria-hidden="true"
						class="hidden-visually"
						accept="application/json"
						@change="onUploadFileInputChange">
				</div>
			</div>

			<div class="row">
				<p class="fix-col-4 span">
					{{ t('tables', 'Supported format: JSON (table structure export)') }}
				</p>
			</div>

			<div v-if="loading" class="row space-T">
				<NcLoadingIcon :size="32" :name="t('tables', 'Loading diff…')" />
			</div>

			<div class="row space-T">
				<div class="fix-col-4 end">
					<NcButton :aria-label="t('tables', 'Preview changes')" type="primary"
						:disabled="loading" @click="actionPreview">
						{{ t('tables', 'Preview changes') }}
					</NcButton>
				</div>
			</div>
		</div>
	</NcDialog>
</template>

<script>
import { NcDialog, NcButton, NcLoadingIcon } from '@nextcloud/vue'
import { getFilePickerBuilder, FilePickerType, showError, showWarning } from '@nextcloud/dialogs'
import { generateOcsUrl, generateUrl } from '@nextcloud/router'
import { emit } from '@nextcloud/event-bus'
import axios from '@nextcloud/axios'
import IconFolder from 'vue-material-design-icons/Folder.vue'
import IconUpload from 'vue-material-design-icons/TrayArrowUp.vue'
import IconFile from 'vue-material-design-icons/File.vue'

export default {
	components: {
		NcDialog,
		NcButton,
		NcLoadingIcon,
		IconFolder,
		IconUpload,
		IconFile,
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
			loading: false,
			cloudPath: '',
			uploadFile: null,
		}
	},

	computed: {
		hasFile() {
			return this.uploadFile !== null || this.cloudPath !== ''
		},
		selectedFileName() {
			if (this.uploadFile) {
				return this.uploadFile.name
			}
			if (this.cloudPath) {
				return this.cloudPath.split('/').pop()
			}
			return ''
		},
	},

	methods: {
		selectUploadFile() {
			this.$refs.uploadFileInput.click()
		},

		onUploadFileInputChange(event) {
			this.uploadFile = event.target.files[0] ?? null
			this.cloudPath = ''
		},

		async pickFile() {
			const filePicker = getFilePickerBuilder(t('tables', 'Select structure JSON file'))
				.setType(FilePickerType.Custom)
				.setMultiSelect(false)
				.setMimeTypeFilter(['application/json'])
				.addButton({
					label: t('tables', 'Select'),
					callback: (nodes) => {
						this.cloudPath = nodes[0]?.path ?? ''
						this.uploadFile = null
					},
					type: 'primary',
				})
				.build()
			await filePicker.pick()
		},

		async actionPreview() {
			if (!this.uploadFile && !this.cloudPath) {
				showWarning(t('tables', 'Please select a file.'))
				return
			}
			let scheme
			this.loading = true
			try {
				scheme = await this.readScheme()
			} catch (e) {
				console.error('Failed to read structure file', e)
				showError(e.message)
				this.loading = false
				return
			}

			try {
				const url = generateOcsUrl('/apps/tables/api/2/tables/' + this.tableId + '/scheme/diff')
				const response = await axios.post(url, { scheme })
				const diff = response.data?.ocs?.data
				this.$emit('close')
				emit('tables:modal:importStructure', { tableId: this.tableId, scheme, diff })
			} catch (e) {
				console.error('Failed to compute structural differences', e)
				showError(t('tables', 'Failed to compute structural differences. Please try again.'))
			} finally {
				this.loading = false
			}
		},

		async readScheme() {
			let text
			if (this.uploadFile) {
				text = await this.uploadFile.text()
			} else if (this.cloudPath) {
				const response = await axios.get(generateUrl('/remote.php/webdav') + this.cloudPath)
				text = typeof response.data === 'string' ? response.data : JSON.stringify(response.data)
			} else {
				throw new Error(t('tables', 'Please select a file.'))
			}

			let scheme
			try {
				scheme = JSON.parse(text)
			} catch (_e) {
				throw new Error(t('tables', 'Could not parse the JSON file. Please check that it is valid JSON.'))
			}

			if (!Array.isArray(scheme?.columns) || !Array.isArray(scheme?.views)) {
				throw new Error(t('tables', 'The selected file does not appear to be a valid table structure export.'))
			}

			return scheme
		},

		actionCancel() {
			this.reset()
			this.$emit('close')
		},

		reset() {
			this.cloudPath = ''
			this.uploadFile = null
			this.loading = false
			if (this.$refs.uploadFileInput) {
				this.$refs.uploadFileInput.value = ''
			}
		},
	},
}
</script>

<style scoped>
.middle {
	align-items: center;
}

.middle button {
	min-width: fit-content;
	margin-inline-end: calc(var(--default-grid-baseline) * 3);
}

.import-filename {
	display: inline-flex;
	align-items: center;
	gap: 12px;
	padding-inline-start: 12px;
	padding-bottom: 16px;
}
</style>
