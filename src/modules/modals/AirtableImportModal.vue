<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog v-if="showModal"
		:name="dialogTitle"
		size="normal"
		@closing="actionCancel">
		<div class="modal__content">

			<!-- ================================================================
			     Step 1 — Input form
			     ================================================================ -->
			<div v-if="step === 'input'">
				<div class="row space-T">
					<p class="description">
						{{ t('tables', 'Paste a public Airtable share URL to import all tables from that base into Nextcloud Tables.') }}
					</p>
				</div>

				<div class="row space-T">
					<label class="col-4 mandatory" for="airtable-share-url">
						{{ t('tables', 'Airtable share URL') }}
					</label>
					<div class="col-4">
						<input id="airtable-share-url"
							v-model="shareUrl"
							:class="{ missing: urlError }"
							type="url"
							:placeholder="t('tables', 'https://airtable.com/shrXXXXXXXX')"
							autocomplete="off"
							@input="urlError = false">
					</div>
				</div>

				<!-- Advanced section -->
				<details class="advanced-section row space-T">
					<summary class="col-4">
						{{ t('tables', 'Advanced options') }}
					</summary>
					<div class="advanced-body">
						<div class="row space-T">
							<label class="col-4" for="airtable-session-cookie">
								{{ t('tables', 'Session cookie') }}
							</label>
							<div class="col-4">
								<input id="airtable-session-cookie"
									v-model="sessionCookie"
									type="password"
									autocomplete="off"
									:placeholder="t('tables', '__Host-airtable-session value (optional, for private bases)')">
							</div>
						</div>
						<div class="row space-T">
							<div class="col-4">
								<NcCheckboxRadioSwitch :checked="false"
									type="switch"
									:disabled="true">
									{{ t('tables', 'Skip importing file attachments') }}
								</NcCheckboxRadioSwitch>
								<p class="hint">
									{{ t('tables', 'File attachment import is not yet supported (planned for Phase 1). Attachment columns will be listed in the import report.') }}
								</p>
							</div>
						</div>
					</div>
				</details>

				<div class="row space-T">
					<div class="fix-col-4 end">
						<NcButton :aria-label="t('tables', 'Cancel')" @click="actionCancel">
							{{ t('tables', 'Cancel') }}
						</NcButton>
						<NcButton type="primary"
							:aria-label="t('tables', 'Start import')"
							:disabled="loading"
							@click="startImport">
							<template v-if="loading" #icon>
								<NcLoadingIcon :size="20" />
							</template>
							{{ t('tables', 'Start import') }}
						</NcButton>
					</div>
				</div>
			</div>

			<!-- ================================================================
			     Step 2 — Progress (rendered by AirtableImportProgress)
			     ================================================================ -->
			<AirtableImportProgress v-else-if="step === 'running'"
				:job-status="jobStatus"
				:progress-done="progressDone"
				:progress-total="progressTotal"
				:error-message="errorMessage"
				@cancel="cancelImport"
				@retry="retryImport"
				@close="actionCancel"
				@go-to-tables="goToTables" />

		</div>
	</NcDialog>
</template>

<script>
import { NcDialog, NcButton, NcCheckboxRadioSwitch, NcLoadingIcon } from '@nextcloud/vue'
import { showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import AirtableImportProgress from './AirtableImportProgress.vue'
import { startAirtableImport, getAirtableImportStatus, cancelAirtableImport } from '../../api/airtableImport.js'

const POLL_INTERVAL_MS = 3000

export default {
	name: 'AirtableImportModal',

	components: {
		NcDialog,
		NcButton,
		NcCheckboxRadioSwitch,
		NcLoadingIcon,
		AirtableImportProgress,
	},

	props: {
		showModal: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['close'],

	data() {
		return {
			// Input step
			step: 'input',
			shareUrl: '',
			sessionCookie: '',
			urlError: false,
			loading: false,

			// Progress step
			jobId: null,
			jobStatus: null,
			progressDone: 0,
			progressTotal: 0,
			errorMessage: null,
			pollingTimer: null,
		}
	},

	computed: {
		dialogTitle() {
			if (this.step === 'running') {
				if (this.jobStatus === 'finished') {
					return t('tables', 'Airtable import — done')
				}
				if (this.jobStatus === 'failed') {
					return t('tables', 'Airtable import — failed')
				}
				if (this.jobStatus === 'cancelled') {
					return t('tables', 'Airtable import — cancelled')
				}
				return t('tables', 'Airtable import — running…')
			}
			return t('tables', 'Import from Airtable')
		},
	},

	beforeUnmount() {
		this.stopPolling()
	},

	methods: {
		// -----------------------------------------------------------------------
		// Input step
		// -----------------------------------------------------------------------

		async startImport() {
			const url = this.shareUrl.trim()
			if (url === '') {
				this.urlError = true
				showError(t('tables', 'Please enter an Airtable share URL.'))
				return
			}

			this.loading = true
			try {
				const data = await startAirtableImport(url, this.sessionCookie.trim() || null)
				this.jobId = data.jobId
				this.jobStatus = data.status
				this.step = 'running'
				this.startPolling()
			} catch (e) {
				const message = e?.response?.data?.message
					|| t('tables', 'Could not start import. Please check the URL and try again.')
				showError(message)
			} finally {
				this.loading = false
			}
		},

		// -----------------------------------------------------------------------
		// Progress step
		// -----------------------------------------------------------------------

		startPolling() {
			this.pollingTimer = setInterval(this.pollStatus, POLL_INTERVAL_MS)
			// Poll immediately for a snappy first update
			this.pollStatus()
		},

		stopPolling() {
			if (this.pollingTimer !== null) {
				clearInterval(this.pollingTimer)
				this.pollingTimer = null
			}
		},

		async pollStatus() {
			if (this.jobId === null) {
				return
			}
			try {
				const data = await getAirtableImportStatus(this.jobId)
				this.jobStatus = data.status
				this.progressDone = data.progressDone ?? 0
				this.progressTotal = data.progressTotal ?? 0
				this.errorMessage = data.errorMessage ?? null
				if (['finished', 'failed', 'cancelled'].includes(this.jobStatus)) {
					this.stopPolling()
				}
			} catch (e) {
				console.error('AirtableImportModal: polling error', e)
			}
		},

		async cancelImport() {
			if (this.jobId === null) {
				return
			}
			try {
				await cancelAirtableImport(this.jobId)
				this.stopPolling()
				this.jobStatus = 'cancelled'
			} catch (e) {
				const message = e?.response?.data?.message
					|| t('tables', 'Could not cancel import.')
				showError(message)
			}
		},

		// -----------------------------------------------------------------------
		// Navigation
		// -----------------------------------------------------------------------

		goToTables() {
			this.actionCancel()
			this.$router.push('/')
		},

		retryImport() {
			this.stopPolling()
			this.jobId = null
			this.jobStatus = null
			this.progressDone = 0
			this.progressTotal = 0
			this.errorMessage = null
			this.step = 'input'
		},

		// -----------------------------------------------------------------------
		// Lifecycle
		// -----------------------------------------------------------------------

		actionCancel() {
			this.stopPolling()
			this.reset()
			this.$emit('close')
		},

		reset() {
			this.step = 'input'
			this.shareUrl = ''
			this.sessionCookie = ''
			this.urlError = false
			this.loading = false
			this.jobId = null
			this.jobStatus = null
			this.progressDone = 0
			this.progressTotal = 0
			this.errorMessage = null
		},
	},
}
</script>

<style lang="scss" scoped>
.description {
	color: var(--color-text-maxcontrast);
}

.hint {
	color: var(--color-text-maxcontrast);
	font-size: 0.85em;
	margin-top: calc(var(--default-grid-baseline) * 1);
}

.advanced-section {
	summary {
		cursor: pointer;
		font-weight: bold;
		user-select: none;
		padding-block: calc(var(--default-grid-baseline) * 2);
		color: var(--color-main-text);

		&:hover {
			color: var(--color-primary-element);
		}
	}

	.advanced-body {
		padding-inline-start: calc(var(--default-grid-baseline) * 2);
		border-inline-start: 2px solid var(--color-border);
	}
}

.fix-col-4.end {
	display: flex;
	justify-content: flex-end;
	gap: calc(var(--default-grid-baseline) * 2);
}
</style>
