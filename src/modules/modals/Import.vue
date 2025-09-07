<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog v-if="showModal"
		:name="title"
		size="normal"
		@closing="actionCancel">
		<div class="modal__content">
			<!-- Starting -->
			<div v-if="!loading && result === null && preview === null && !waitForReload">
				<div class="row space-T">
					{{ t('tables', 'Add data to the table from a file') }}
				</div>
				<RowFormWrapper>
					<div v-if="importFileName.length" class="import-filename">
						<IconFile :size="20" />{{ importFileName }}
					</div>
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
							:accept="mimeTypes.join(',')"
							@change="onUploadFileInputChange">
					</div>
					<div class="fix-col-4">
						<p class="span">
							{{ t('tables', 'Supported formats: xlsx, xls, csv, html, xml') }}
							<br>
							{{ t('tables', 'First row of the file must contain column headings without gaps.') }}
						</p>
					</div>
				</RowFormWrapper>

				<div class="row">
					<div class="fix-col-2">
						<NcCheckboxRadioSwitch :checked.sync="createMissingColumns" type="switch" :disabled="!canCreateMissingColumns">
							{{ t('tables', 'Create missing columns') }}
						</NcCheckboxRadioSwitch>
					</div>
					<p v-if="(isElementView && !canManageTable(element)) || !canManageElement(element)" class="fix-col-2 span">
						{{ t('tables', '⚠️ You don\'t have the permission to create columns.') }}
					</p>
				</div>

				<div class="row">
					<div class="fix-col-4 end">
						<NcButton :aria-label="t('tables', 'Preview')" type="primary" @click="actionPreview">
							{{ t('tables', 'Preview') }}
						</NcButton>
					</div>
				</div>
			</div>

			<!-- show preview -->
			<div v-if="!loading && preview !== null && !result && !waitForReload">
				<ImportPreview :preview-data="preview" :element="element" :create-missing-columns="createMissingColumns" @update:columns="onUpdateColumnsConfig" />

				<div class="row">
					<div class="fix-col-4 space-T end">
						<NcButton :aria-label="t('tables', 'Import')" type="primary" @click="actionImport">
							{{ t('tables', 'Import') }}
						</NcButton>
					</div>
				</div>
			</div>

			<!-- show results -->
			<div v-if="!loading && result !== null && !waitForReload">
				<ImportResults :results="result" />

				<div class="row">
					<div class="fix-col-4 space-T end">
						<NcButton :aria-label="t('tables', 'Done')" type="primary" @click="actionCloseAndReload">
							{{ t('tables', 'Done') }}
						</NcButton>
					</div>
				</div>
			</div>

			<!-- show loading -->
			<div v-if="loading && !waitForReload">
				<div v-if="!importFailed">
					<NcEmptyContent :name="t('tables', 'Importing data from ') + importFileName"
						:description="t('tables', 'This might take a while...')">
						<template #icon>
							<NcIconTimerSand />
						</template>
					</NcEmptyContent>
				</div>
				<div v-else>
					<NcEmptyContent :name="t('tables', 'Failed')" :description="errorMessage" />
				</div>
			</div>

			<div v-if="waitForReload">
				<NcLoadingIcon :name="t('tables', 'Loading table data')" :size="64" />
			</div>
		</div>
	</NcDialog>
</template>

<script>
import { NcDialog, NcButton, NcCheckboxRadioSwitch, NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'
import { getFilePickerBuilder, FilePickerType, showWarning } from '@nextcloud/dialogs'
import RowFormWrapper from '../../shared/components/ncTable/partials/rowTypePartials/RowFormWrapper.vue'
import permissionsMixin from '../../shared/components/ncTable/mixins/permissionsMixin.js'
import IconFolder from 'vue-material-design-icons/Folder.vue'
import IconUpload from 'vue-material-design-icons/TrayArrowUp.vue'
import IconFile from 'vue-material-design-icons/File.vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { useTablesStore } from '../../store/store.js'
import { mapState, mapActions } from 'pinia'
import NcIconTimerSand from '../../shared/components/ncIconTimerSand/NcIconTimerSand.vue'
import ImportResults from './ImportResults.vue'
import ImportPreview from './ImportPreview.vue'
import { translate as t } from '@nextcloud/l10n'
import { useDataStore } from '../../store/data.js'

export default {

	components: {
		NcIconTimerSand,
		NcLoadingIcon,
		IconFolder,
		IconUpload,
		IconFile,
		NcDialog,
		NcButton,
		ImportResults,
		ImportPreview,
		NcCheckboxRadioSwitch,
		RowFormWrapper,
		NcEmptyContent,
	},

	mixins: [permissionsMixin],

	props: {
		showModal: {
			type: Boolean,
			default: false,
		},
		element: {
			type: Object,
			default: null,
		},
		isElementView: {
			type: Boolean,
			default: true,
		},
	},
	data() {
		return {
			path: '',
			createMissingColumns: true,
			pathError: false,
			loading: false,
			importFailed: false,
			result: null,
			preview: null,
			columnsConfig: [],
			waitForReload: false,
			mimeTypes: [
				'text/csv',
				'application/vnd.ms-excel',
				'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
				'application/xml',
				'text/html',
				'application/vnd.oasis.opendocument.spreadsheet',
			],
			selectedUploadFile: null,
			errorMessage: t('tables', 'Could not import data due to unknown errors.'),
		}
	},

	computed: {
		...mapState(useTablesStore, ['activeElement', 'isView']),
		canCreateMissingColumns() {
			return this.isElementView ? this.canManageTable(this.element) : this.canManageElement(this.element)
		},
		getCreateMissingColumns() {
			return this.canCreateMissingColumns && this.createMissingColumns
		},
		importFileName() {
			const fileName = this.selectedUploadFile ? this.selectedUploadFile.name : this.path

			if (fileName.length > 30) {
				const extension = fileName.split('.').pop()
				return fileName.substring(0, 30 - extension.length - 3) + '...' + extension
			}

			return fileName
		},
		title() {
			let title = t('tables', 'Import table')

			if (!this.loading && this.preview !== null && !this.result && !this.waitForReload) {
				title = t('tables', 'Preview imported table')
			}

			return title
		},
	},
	watch: {
		element() {
			if (this.element) {
				this.createMissingColumns = this.canCreateMissingColumns
			}
		},
		path(val) {
			if (val !== '') {
				this.clearSelectedUploadFile()
			}
		},
		selectedUploadFile(val) {
			if (val !== null) {
				this.path = ''
			}
		},
	},

	methods: {
		...mapActions(useTablesStore, ['loadTablesFromBE', 'loadViewsSharedWithMeFromBE']),
		...mapActions(useDataStore, ['loadRowsFromBE', 'loadColumnsFromBE']),
		async actionCloseAndReload() {
			if (!this?.activeElement) {
				return
			}

			// reload data if active element was affected
			if ((this.isView && this.isElementView && this.activeElement.tableId === this.element.tableId)
			|| (this.isView && !this.isElementView && this.activeElement.tableId === this.element.id)
			|| (!this.isView && this.isElementView && this.activeElement.id === this.element.tableId)
			|| (!this.isView && !this.isElementView && this.activeElement.id === this.element.id)) {
				this.waitForReload = true
				await this.loadTablesFromBE()
				await this.loadViewsSharedWithMeFromBE()
				await this.loadColumnsFromBE({
					view: this.isElementView ? this.element : null,
					tableId: !this.isElementView ? this.element.id : null,
				})
				if (this.canReadData(this.element)) {
					await this.loadRowsFromBE({
						viewId: this.isElementView ? this.element.id : null,
						tableId: !this.isElementView ? this.element.id : null,
					})
				}
				this.waitForReload = false
			}

			this.actionCancel()
		},
		actionPreview() {
			if (this.selectedUploadFile && this.selectedUploadFile.type !== '' && !this.mimeTypes.includes(this.selectedUploadFile.type)) {
				showWarning(t('tables', 'The selected file is not supported.'))
				return null
			}

			if (this.selectedUploadFile) {
				this.previewImportFromUploadFile()
				return
			}

			if (this.path === '') {
				showWarning(t('tables', 'Please select a file.'))
				this.pathError = true
				return null
			}
			this.pathError = false
			this.previewImportFromPath()
		},
		async previewImportFromPath() {
			this.loading = true
			try {
				const res = await axios.post(generateUrl('/apps/tables/import-preview/' + (this.isElementView ? 'view' : 'table') + '/' + this.element.id), { path: this.path })
				if (res.status === 200) {
					this.preview = res.data
					this.loading = false
				} else {
					this.handleResponse(res, null)
					this.importFailed = true
				}
			} catch (e) {
				this.errorMessage = t('tables', 'Could not import data due to unknown errors.')
				console.error(e)
				this.importFailed = true
				return false
			}
		},
		async previewImportFromUploadFile() {
			this.loading = true
			try {
				const url = generateUrl('/apps/tables/importupload-preview/' + (this.isElementView ? 'view' : 'table') + '/' + this.element.id)
				const formData = new FormData()
				formData.append('uploadfile', this.selectedUploadFile)

				const res = await axios.post(url, formData, {
					headers: {
						'Content-Type': 'multipart/form-data',
					},
				})

				if (res.status === 200) {
					this.preview = res.data
					this.loading = false
				} else {
					this.handleResponse(res, null)
					this.importFailed = true
				}
			} catch (e) {
				this.errorMessage = t('tables', 'Could not import data due to unknown errors.')
				console.error(e)
				this.importFailed = true
				return false
			}
		},
		actionImport() {
			if (this.selectedUploadFile && this.selectedUploadFile.type !== '' && !this.mimeTypes.includes(this.selectedUploadFile.type)) {
				showWarning(t('tables', 'The selected file is not supported.'))
				return null
			}

			if (!this.validateColumnsConfig()) {
				return null
			}

			if (this.selectedUploadFile) {
				this.importFromUploadFile()
				return null
			}

			if (this.path === '') {
				showWarning(t('tables', 'Please select a file.'))
				this.pathError = true
				return null
			}
			this.pathError = false
			this.importFromPath()
		},
		validateColumnsConfig() {
			const existColumnCount = {}
			for (const column of this.columnsConfig) {
				if (column.action === 'exist') {
					if (!column.existColumn) {
						showWarning(t('tables', 'Please select column for mapping.'))
						return false
					}
					if (existColumnCount[column.existColumn.id] === undefined) {
						existColumnCount[column.existColumn.id] = 1
					} else {
						existColumnCount[column.existColumn.id]++
					}
				}
			}
			if (Object.values(existColumnCount).some(count => count > 1)) {
				showWarning(t('tables', 'Cannot map same exist column for multiple columns.'))
				return false
			}

			return true
		},
		async importFromPath() {
			this.loading = true
			try {
				const res = await axios.post(
					generateUrl('/apps/tables/import/' + (this.isElementView ? 'view' : 'table') + '/' + this.element.id),
					{ path: this.path, createMissingColumns: this.getCreateMissingColumns, columnsConfig: this.columnsConfig },
				)
				if (res.status === 200) {
					this.result = res.data
				} else {
					console.debug('error while importing', res)
					this.errorMessage = t('tables', res.data?.message || 'Could not import data due to unknown errors.')
				}
			} catch (e) {
				this.handleResponse(e.response, e)
			}
			this.loading = false
		},
		async importFromUploadFile() {
			this.loading = true
			try {
				const url = generateUrl('/apps/tables/importupload/' + (this.isElementView ? 'view' : 'table') + '/' + this.element.id)
				const formData = new FormData()
				formData.append('uploadfile', this.selectedUploadFile)
				formData.append('createMissingColumns', this.getCreateMissingColumns)
				formData.append('columnsConfig', JSON.stringify(this.columnsConfig))

				const res = await axios.post(url, formData, {
					headers: {
						'Content-Type': 'multipart/form-data',
					},
				})

				if (res.status === 200) {
					this.result = res.data
				} else {
					console.debug('error while importing', res)
					this.errorMessage = t('tables', res.data?.message || 'Could not import data due to unknown errors.')
				}
			} catch (e) {
				this.handleResponse(e.response, e)
			}
			this.loading = false
		},
		actionCancel() {
			this.reset()
			this.$emit('close')
		},
		reset() {
			this.path = ''
			this.pathError = false
			this.createMissingColumns = true
			this.result = null
			this.preview = null
			this.loading = false
		},
		async pickFile() {
			const filePicker = getFilePickerBuilder(t('text', 'Select file for the import'))
				.setType(FilePickerType.Custom)
				.setMultiSelect(false)
				.setMimeTypeFilter(this.mimeTypes)
				.addButton({
					label: t('tables', 'Import'),
					callback: (nodes) => {
						const fileInfo = nodes[0]
						this.path = fileInfo.path
					},
					type: 'primary',
				})
				.startAt(this.path)
				.build()
			await filePicker.pick()
		},
		selectUploadFile() {
			this.$refs.uploadFileInput.click()
		},
		clearSelectedUploadFile() {
			this.selectedUploadFile = null
			this.$refs.uploadFileInput.value = ''
		},
		onUploadFileInputChange(event) {
			this.selectedUploadFile = event.target.files[0]
		},
		onUpdateColumnsConfig(event) {
			this.columnsConfig = event
		},
		handleResponse(res, e) {
			if (res?.status === 401) {
				console.debug('error while importing', e || res)
				this.errorMessage = t('tables', 'Could not import, not authorized. Are you logged in?')
			} else if (res?.status === 403) {
				console.debug('error while importing', e || res)
				this.errorMessage = t('tables', 'Could not import, missing needed permission.')
			} else if (res?.status === 404) {
				console.debug('error while importing', e || res)
				this.errorMessage = t('tables', 'Could not import, needed resources were not found.')
			} else {
				console.debug('error while importing', e || res)
				this.errorMessage = t('tables', e?.response?.data?.message || 'Could not import data due to unknown errors.')
			}
		},
	},

}
</script>
<style lang="scss" scoped>
	:deep(.slot), .middle {
		align-items: center;
	}

	.slot button {
		min-width: fit-content;
		margin-inline-end: calc(var(--default-grid-baseline) * 3);
	}

	:deep(.empty-content p) {
		text-align: center;
	}

	:deep(.slot) {
		display: block;
	}

	.information :deep(.row.space-T) {
		padding-top: calc(var(--default-grid-baseline) * 2);
	}

	.import-filename {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		gap: 12px;
		padding-inline-start: 12px;
		padding-bottom: 16px;
	}

	.result-headline {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		gap: 8px;
		font-size: medium;
	}

	.errors-count {
		display: flex;
		gap: 4px;
	}

</style>
