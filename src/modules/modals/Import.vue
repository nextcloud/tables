<template>
	<NcModal v-if="showModal" size="normal" @close="actionCancel">
		<div class="modal__content">
			<div class="row">
				<div class="col-4">
					<h2>{{ t('tables', 'Import table') }}</h2>
				</div>
			</div>

			<!-- Starting -->
			<div v-if="!loading && result === null && !waitForReload">
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
							{{ t('tables', 'First row of the file must contain column headings.') }}
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
						<NcButton :aria-label="t('tables', 'Import')" type="primary" @click="actionSubmit">
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
				<NcEmptyContent :name="t('tables', 'Importing data from ') + importFileName" :description="t('tables', 'This might take a while...')">
					<template #icon>
						<NcIconTimerSand />
					</template>
				</NcEmptyContent>
			</div>

			<div v-if="waitForReload">
				<NcLoadingIcon :name="t('tables', 'Loading table data')" :size="64" />
			</div>
		</div>
	</NcModal>
</template>

<script>
import { NcModal, NcButton, NcCheckboxRadioSwitch, NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'
import { getFilePickerBuilder, FilePickerType, showError, showWarning } from '@nextcloud/dialogs'
import RowFormWrapper from '../../shared/components/ncTable/partials/rowTypePartials/RowFormWrapper.vue'
import permissionsMixin from '../../shared/components/ncTable/mixins/permissionsMixin.js'
import IconFolder from 'vue-material-design-icons/Folder.vue'
import IconUpload from 'vue-material-design-icons/Upload.vue'
import IconFile from 'vue-material-design-icons/File.vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { mapGetters } from 'vuex'
import NcIconTimerSand from '../../shared/components/ncIconTimerSand/NcIconTimerSand.vue'
import ImportResults from './ImportResults.vue'

export default {

	components: {
		NcIconTimerSand,
		NcLoadingIcon,
		IconFolder,
		IconUpload,
		IconFile,
		NcModal,
		NcButton,
		ImportResults,
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
			result: null,
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
		}
	},

	computed: {
		...mapGetters(['activeElement', 'isView']),
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
				await this.$store.dispatch('loadTablesFromBE')
				await this.$store.dispatch('loadViewsSharedWithMeFromBE')
				await this.$store.dispatch('loadColumnsFromBE', {
					view: this.isElementView ? this.element : null,
					table: !this.isElementView ? this.element : null,
				})
				if (this.canReadData(this.element)) {
					await this.$store.dispatch('loadRowsFromBE', {
						viewId: this.isElementView ? this.element.id : null,
						tableId: !this.isElementView ? this.element.id : null,
					})
				}
				this.waitForReload = false
			}

			this.actionCancel()
		},
		actionSubmit() {
			if (this.selectedUploadFile && this.selectedUploadFile.type !== '' && !this.mimeTypes.includes(this.selectedUploadFile.type)) {
				showWarning(t('tables', 'The selected file is not supported.'))
				return null
			}

			if (this.selectedUploadFile) {
				this.uploadFile()
				return
			}

			if (this.path === '') {
				showWarning(t('tables', 'Please select a file.'))
				this.pathError = true
				return null
			}
			this.pathError = false
			this.import()
		},
		async import() {
			this.loading = true
			try {
				const res = await axios.post(generateUrl('/apps/tables/import/' + (this.isElementView ? 'view' : 'table') + '/' + this.element.id), { path: this.path, createMissingColumns: this.getCreateMissingColumns })
				if (res.status === 200) {
					this.result = res.data
					this.loading = false
				} else if (res.status === 401) {
					console.debug('error while importing', res)
					showError(t('tables', 'Could not import, not authorized. Are you logged in?'))
				} else if (res.status === 403) {
					console.debug('error while importing', res)
					showError(t('tables', 'Could not import, missing needed permission.'))
				} else if (res.status === 404) {
					console.debug('error while importing', res)
					showError(t('tables', 'Could not import, needed resources were not found.'))
				} else {
					showError(t('tables', 'Could not import data due to unknown errors.'))
					console.debug('error while importing', res)
				}
			} catch (e) {
				console.error(e)
				return false
			}
		},
		async uploadFile() {
			this.loading = true
			try {
				const url = generateUrl('/apps/tables/importupload/' + (this.isElementView ? 'view' : 'table') + '/' + this.element.id)
				const formData = new FormData()
				formData.append('uploadfile', this.selectedUploadFile)
				formData.append('createMissingColumns', this.getCreateMissingColumns)

				const res = await axios.post(url, formData, {
					headers: {
						'Content-Type': 'multipart/form-data',
					},
				})

				if (res.status === 200) {
					this.result = res.data
					this.loading = false
				} else if (res.status === 401) {
					console.debug('error while importing', res)
					showError(t('tables', 'Could not import, not authorized. Are you logged in?'))
				} else if (res.status === 403) {
					console.debug('error while importing', res)
					showError(t('tables', 'Could not import, missing needed permission.'))
				} else if (res.status === 404) {
					console.debug('error while importing', res)
					showError(t('tables', 'Could not import, needed resources were not found.'))
				} else {
					showError(t('tables', 'Could not import data due to unknown errors.'))
					console.debug('error while importing', res)
				}
			} catch (e) {
				console.error(e)
				return false
			}
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
			this.loading = false
		},
		pickFile() {
			const filePicker = getFilePickerBuilder(t('text', 'Select file for the import'))
				.setMultiSelect(false)
				.setMimeTypeFilter(this.mimeTypes)
				.setType(FilePickerType.Choose)
				.startAt(this.path)
				.build()

			filePicker.pick().then((file) => {
				const client = OC.Files.getClient()
				client.getFileInfo(file).then((_status, fileInfo) => {
					this.path = fileInfo.path === '/' ? `/${fileInfo.name}` : `${fileInfo.path}/${fileInfo.name}`
				})
			})
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
	},

}
</script>
<style lang="scss" scoped>

	h2 {
		margin-bottom: 0;
	}

	:deep(.slot), .middle {
		align-items: center;
	}

	.slot button {
		min-width: fit-content;
		margin-right: calc(var(--default-grid-baseline) * 3);
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
		padding-left: 12px;
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
