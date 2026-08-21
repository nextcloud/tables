<!--
	- SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
	- SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog v-if="showModal"
		:name="title"
		size="normal"
		@closing="actionCancel">
		<div class="modal__content">
			<!-- Starting -->
			<div v-if="showStarting">
				<div class="row space-T">
					{{ t('tables', 'Import application scheme from a file') }}
				</div>
				<RowFormWrapper>
					<div v-if="importFileName.length" class="import-filename">
						<IconFile :size="20" />{{ importFileName }}
					</div>
					<div class="fix-col-4 middle">
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
							{{ t('tables', 'Supported formats: json') }}
						</p>
					</div>
				</RowFormWrapper>
			</div>

			<!-- show preview -->
			<div v-if="showPreview">
				<ImportContextSchemePreview :preview-data="preview" :table="table" @update="onUpdateScheme" />
			</div>

			<!-- show results -->
			<div v-if="showResults">
				<div v-if="!importFailed" class="row space-T">
					{{ t('tables', 'Import completed successfully.') }}
				</div>
				<div v-else>
					<NcEmptyContent :name="t('tables', 'Failed')" :description="errorMessage" />
				</div>
			</div>

			<!-- show loading -->
			<div v-if="showLoading">
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

		<template #actions>
			<NcButton v-if="showStarting" :aria-label="t('tables', 'Preview')" variant="primary" @click="actionPreview">
				{{ t('tables', 'Preview') }}
			</NcButton>
			<NcButton v-if="showPreview" :aria-label="t('tables', 'Back')" @click="actionBack">
				{{ t('tables', 'Back') }}
			</NcButton>
			<NcButton v-if="showPreview" :aria-label="t('tables', 'Import')" variant="primary" @click="actionImport">
				{{ t('tables', 'Import') }}
			</NcButton>
			<NcButton v-if="showResults" :aria-label="t('tables', 'Done')" variant="primary" @click="actionCloseAndReload">
				{{ t('tables', 'Done') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { NcDialog, NcButton, NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'
import { showWarning } from '@nextcloud/dialogs'
import RowFormWrapper from '../../shared/components/ncTable/partials/rowTypePartials/RowFormWrapper.vue'
import permissionsMixin from '../../shared/components/ncTable/mixins/permissionsMixin.js'
import IconUpload from 'vue-material-design-icons/TrayArrowUp.vue'
import IconFile from 'vue-material-design-icons/File.vue'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { useTablesStore } from '../../store/store.js'
import { mapState, mapActions } from 'pinia'
import NcIconTimerSand from '../../shared/components/ncIconTimerSand/NcIconTimerSand.vue'
import ImportContextSchemePreview from './ImportContextSchemePreview.vue'
import { translate as t } from '@nextcloud/l10n'
import { useDataStore } from '../../store/data.js'

export default {
	name: 'ImportTableScheme',

	components: {
		NcIconTimerSand,
		NcLoadingIcon,
		IconUpload,
		IconFile,
		NcDialog,
		NcButton,
		ImportContextSchemePreview,
		RowFormWrapper,
		NcEmptyContent,
	},

	mixins: [permissionsMixin],

	props: {
		showModal: {
			type: Boolean,
			default: false,
		},
		contextId: {
			type: Number,
			default: null,
		},
	},
	emits: [
		'close',
	],
	data() {
		return {
			loading: false,
			importFailed: false,
			result: null,
			importFinished: false,
			preview: null,
			schemeData: null,
			waitForReload: false,
			mimeTypes: [
				'application/json',
			],
			selectedUploadFile: null,
			errorMessage: t('tables', 'Could not import due to unknown errors.'),
		}
	},

	computed: {
		...mapState(useTablesStore, ['getContext', 'tables', 'views', 'activeContextId']),
		importFileName() {
			const fileName = this.selectedUploadFile ? this.selectedUploadFile.name : ''

			if (fileName.length > 30) {
				const extension = fileName.split('.').pop()
				return fileName.substring(0, 30 - extension.length - 3) + '...' + extension
			}

			return fileName
		},
		title() {
			let title = t('tables', 'Import application scheme')

			if (!this.loading && this.preview !== null && !this.importFinished && !this.waitForReload && !this.importFailed) {
				title = t('tables', 'Preview application scheme changes')
			}

			return title
		},
		showStarting() {
			return !this.loading && !this.importFinished && this.preview === null && !this.waitForReload
		},
		showPreview() {
			return !this.loading && this.preview !== null && !this.importFinished && !this.importFailed && !this.waitForReload
		},
		showResults() {
			return !this.loading && (this.importFinished || this.importFailed) && !this.waitForReload
		},
		showLoading() {
			return this.loading && !this.waitForReload
		},
	},

	watch: {
		showModal(newVal) {
			if (newVal) {
				this.reset()
				this.$nextTick(() => this.clearSelectedUploadFile())
			}
		},
	},

	methods: {
		...mapActions(useTablesStore, ['loadContext', 'loadTablesFromBE', 'loadViewsSharedWithMeFromBE']),
		...mapActions(useDataStore, ['loadRowsFromBE', 'loadColumnsFromBE']),
		async actionCloseAndReload() {
			this.waitForReload = true
			await this.loadContext({ id: this.contextId })
			await this.loadTablesFromBE()
			this.waitForReload = false
			this.actionCancel()
		},
		actionPreview() {
			if (!this.selectedUploadFile) {
				showWarning(t('tables', 'No file selected for import.'))
				return null
			}

			if (this.selectedUploadFile.type !== '' && !this.mimeTypes.includes(this.selectedUploadFile.type)) {
				showWarning(t('tables', 'The selected file is not supported.'))
				return null
			}

			this.previewImportFromUploadFile()
		},
		async previewImportFromUploadFile() {
			this.loading = true
			this.importFailed = false
			this.errorMessage = t('tables', 'Could not import due to unknown errors.')

			try {
				const url = generateOcsUrl('/apps/tables/api/2/contexts/' + this.contextId + '/scheme/preview-changes')
				const res = await axios.post(url, {
					updateScheme: this.schemeData
				})
				this.preview = res.data?.ocs?.data || null
			} catch (e) {
				this.errorMessage = t('tables', 'Could not import data due to unknown errors.')
				console.error(e)
				this.importFailed = true
			} finally {
				this.loading = false
			}
		},
		onUpdateScheme(newScheme) {
			this.schemeData = newScheme
		},
		async actionImport() {
			if (!this.schemeData) {
				showWarning(t('tables', 'No scheme data to import.'))
				return null
			}

			this.loading = true
			this.importFailed = false
			try {
				const url = generateOcsUrl('/apps/tables/api/2/contexts/' + this.contextId + '/scheme/import')
				const res = await axios.post(url, this.schemeData)

				if (res.status === 200) {
					this.importFinished = true
					this.result = res.data
				} else {
					console.debug('error while importing', res)
					this.importFailed = true
					this.errorMessage = t('tables', res.data?.message || 'Could not import scheme due to unknown errors.')
				}
			} catch (e) {
				this.importFailed = true
				this.handleResponse(e.response, e)
			} finally {
				this.loading = false
			}
		},
		actionCancel() {
			this.reset()
			this.$emit('close')
		},
		actionBack() {
			this.reset()
			this.$nextTick(() => this.clearSelectedUploadFile())
		},
		reset() {
			this.result = null
			this.importFinished = false
			this.importFailed = false
			this.preview = null
			this.loading = false
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

			const reader = new FileReader()
			reader.readAsText(this.selectedUploadFile, 'UTF-8')
			reader.onload = (evt) => {
				try {
					this.schemeData = JSON.parse(evt.target.result)
				} catch (parseError) {
					this.importFailed = true
					this.errorMessage = t('tables', 'The selected file is not valid JSON.')
					console.error(parseError)
				} finally {
					this.loading = false
				}
			}
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
				this.errorMessage = t('tables', e?.response?.data?.message || 'Could not import scheme due to unknown errors.')
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
