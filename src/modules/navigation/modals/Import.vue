<template>
	<NcModal v-if="showModal" size="normal" @close="actionCancel">
		<div class="modal__content">
			<div class="row">
				<div class="col-4">
					<h2>{{ t('tables', 'Import') }}</h2>
				</div>
			</div>

			<!-- Starting -->
			<div v-if="!loading && result === null && !waitForReload">
				<RowFormWrapper :title="t('tables', 'File')" :description="t('tables', 'Choose a file that should be imported. Supported formats are xlsx, xls, html, xml and csv.')">
					<div class="fix-col-4 space-T-small middle">
						<NcButton @click="pickFile">
							<template #icon>
								<IconFolder :size="20" />
							</template>
							{{ t('tables', 'Select a file') }}
						</NcButton>
						<input v-model="path" :class="{ missing: pathError }">
					</div>
				</RowFormWrapper>

				<RowFormWrapper :title="t('tables', 'Create missing columns')" :description="t('tables', 'Columns are identifies by the titles. If there is no match, a new text-line column will be created.')">
					<div class="fix-col-2">
						<NcCheckboxRadioSwitch :checked.sync="createMissingColumns" type="switch" :disabled="!canCreateMissingColumns">
							{{ t('tables', 'Create missing columns') }}
						</NcCheckboxRadioSwitch>
					</div>
					<p v-if="!canCreateMissingColumns" class="fix-col-2 span">
						{{ t('tables', '⚠️ You don\'t have the permission to create columns.') }}
					</p>
				</RowFormWrapper>

				<p class="fix-col-4 span">
					{{ t('tables', 'Note that imported data will be added to the table. Updating of existing rows is not possible at the moment.') }}
					{{ t('tables', 'The possible importing size depends on the system configuration and is only limited by execution time and memory.') }}
				</p>

				<div class="row">
					<div class="fix-col-4 space-T end">
						<NcButton type="primary" @click="actionSubmit">
							{{ t('tables', 'Import') }}
						</NcButton>
					</div>
				</div>
			</div>

			<!-- show results -->
			<div v-if="!loading && result !== null && !waitForReload">
				<RowFormWrapper :title="t('tables', 'Result')">
					<div class="fix-col-1">
						{{ t('tables', 'Found columns') }}
					</div>
					<div class="fix-col-3">
						{{ result['found columns'] }}
					</div>
					<div class="fix-col-1">
						{{ t('tables', 'Created columns') }}
					</div>
					<div class="fix-col-3">
						{{ result['created columns'] }}
					</div>
					<div class="fix-col-1">
						{{ t('tables', 'Inserted rows') }}
					</div>
					<div class="fix-col-3">
						{{ result['inserted rows'] }}
					</div>
					<div class="fix-col-1">
						{{ t('tables', 'Errors') }}
					</div>
					<div class="fix-col-3">
						{{ result['errors (see logs)'] }}
					</div>
				</RowFormWrapper>

				<div class="row">
					<div class="fix-col-4 space-T end">
						<NcButton type="primary" @click="actionCloseAndReload">
							{{ t('tables', 'Done') }}
						</NcButton>
					</div>
				</div>
			</div>

			<!-- show loading -->
			<div v-if="loading && !waitForReload">
				<NcEmptyContent :title="t('tables', 'Importing...')" :description="t('tables', 'Please wait while we try our best to import your data. This might take some time, depending on the server configuration.')">
					<template #icon>
						<IconTimerSand v-if="sandIcon === 0" :size="20" />
						<IconTimerSandPaused v-if="sandIcon === 1" :size="20" />
						<IconTimerSandComplete v-if="sandIcon === 2" :size="20" />
					</template>
				</NcEmptyContent>
			</div>

			<div v-if="waitForReload">
				<NcLoadingIcon :title="t('tables', 'Loading table data')" :size="64" />
			</div>
		</div>
	</NcModal>
</template>

<script>
import { NcModal, NcButton, NcCheckboxRadioSwitch, NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'
import { FilePicker, FilePickerType, showError, showWarning } from '@nextcloud/dialogs'
import RowFormWrapper from '../../../shared/components/ncTable/partials/rowTypePartials/RowFormWrapper.vue'
import permissionMixin from '../../../shared/components/ncTable/mixins/permissionsMixin.js'
import IconFolder from 'vue-material-design-icons/Folder.vue'
import IconTimerSand from 'vue-material-design-icons/TimerSand.vue'
import IconTimerSandPaused from 'vue-material-design-icons/TimerSandPaused.vue'
import IconTimerSandComplete from 'vue-material-design-icons/TimerSandComplete.vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { mapGetters } from 'vuex'

export default {

	components: {
		NcLoadingIcon,
		IconTimerSand,
		IconTimerSandComplete,
		IconTimerSandPaused,
		IconFolder,
		NcModal,
		NcButton,
		NcCheckboxRadioSwitch,
		RowFormWrapper,
		NcEmptyContent,
	},

	mixins: [permissionMixin],

	props: {
		showModal: {
			type: Boolean,
			default: false,
		},
		table: {
		      type: Object,
		      default: null,
		    },
	},

	data() {
		return {
			path: '',
			createMissingColumns: true,
			pathError: false,
			loading: false,
			sandIcon: 0,
			sandIconTimer: null,
			sandIconFactor: -1,
			sandIconTimerInterval: 500, // milliseconds
			result: null,
			waitForReload: false,
		}
	},

	computed: {
		...mapGetters(['activeTable']),
		canCreateMissingColumns() {
			return this.canManageTable(this.table)
		},
		getCreateMissingColumns() {
			return this.canManageTable(this.table) && this.createMissingColumns
		},
	},

	methods: {
		async actionCloseAndReload() {
			// reload data if active table was affected
			if (this.activeTable.id === this.table.id) {
				this.waitForReload = true
				await this.$store.dispatch('loadTablesFromBE')
				await this.$store.dispatch('loadColumnsFromBE', { tableId: this.table.id })
				await this.$store.dispatch('loadRowsFromBE', { tableId: this.table.id })
				this.waitForReload = false
			}

			this.actionCancel()
		},
		updateSandIcon() {
			if (this.sandIcon === 2 || this.sandIcon === 0) {
				this.sandIconFactor = this.sandIconFactor * -1
			}
			this.sandIcon = this.sandIcon + this.sandIconFactor
			this.sandIconTimer = setTimeout(this.updateSandIcon, this.sandIconTimerInterval)
		},
		stopSandIconTimer() {
			clearTimeout(this.sandIconTimer)
		},
		actionSubmit() {
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
			this.updateSandIcon()
			try {
				const res = await axios.post(generateUrl('/apps/tables/import/table/' + this.table.id), { path: this.path, createMissingColumns: this.getCreateMissingColumns })
				if (res.status === 200) {
					this.result = res.data
					this.stopSandIconTimer()
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
			this.stopSandIconTimer()
			this.path = ''
			this.pathError = false
			this.createMissingColumns = true
			this.result = null
			this.loading = false
			this.sandIcon = 0
			this.sandIconFactor = -1
		},
		pickFile() {
			const filePicker = new FilePicker(
				t('text', 'Select file for the import'),
				false, // multiselect
				[
					'text/csv',
					'application/vnd.ms-excel',
					'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
					'application/xml',
					'text/html',
					'application/vnd.oasis.opendocument.spreadsheet',
				], // mime filter
				true, // modal
				FilePickerType.Choose, // type
				false, // directories
				this.path // path
			)

			filePicker.pick().then((file) => {
				const client = OC.Files.getClient()
				client.getFileInfo(file).then((_status, fileInfo) => {
					this.path = fileInfo.path === '/' ? `/${fileInfo.name}` : `${fileInfo.path}/${fileInfo.name}`
				})
			})
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

</style>
