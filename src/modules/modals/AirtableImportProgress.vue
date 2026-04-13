<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<!--
  Pure display component for the progress step of the Airtable import flow.

  Receives the current job state as props and emits user-action events back to
  AirtableImportModal, which owns the polling logic and API calls.

  Props:
    jobStatus     — 'pending' | 'running' | 'finished' | 'failed' | 'cancelled'
    progressDone  — number of rows imported so far
    progressTotal — total rows to import (0 = unknown / still preparing)
    errorMessage  — server-side error message; non-null only when jobStatus === 'failed'

  Emits:
    cancel        — user clicked "Cancel import" (pending / running)
    retry         — user clicked "Retry" (failed) or "Start a new import" (cancelled)
    close         — user clicked "Close"
    go-to-tables  — user clicked "Go to imported tables" (finished)
-->
<template>
	<div class="airtable-progress">

		<!-- Running / pending -->
		<template v-if="isActive">
			<NcEmptyContent :name="t('tables', 'Import in progress…')"
				:description="progressDescription">
				<template #icon>
					<NcLoadingIcon :size="64" />
				</template>
			</NcEmptyContent>
			<NcProgressBar class="progress-bar" :value="progressPercent" />
			<div class="row space-T">
				<div class="actions-end">
					<NcButton :aria-label="t('tables', 'Cancel import')"
						@click="$emit('cancel')">
						{{ t('tables', 'Cancel import') }}
					</NcButton>
				</div>
			</div>
		</template>

		<!-- Finished -->
		<template v-else-if="jobStatus === 'finished'">
			<NcEmptyContent :name="t('tables', 'Import finished!')"
				:description="t('tables', 'All tables have been imported successfully.')">
				<template #icon>
					<IconCheck :size="64" />
				</template>
			</NcEmptyContent>
			<div class="row space-T">
				<div class="actions-end">
					<NcButton :aria-label="t('tables', 'Close')"
						@click="$emit('close')">
						{{ t('tables', 'Close') }}
					</NcButton>
					<NcButton type="primary"
						:aria-label="t('tables', 'Go to imported tables')"
						@click="$emit('go-to-tables')">
						{{ t('tables', 'Go to imported tables') }}
					</NcButton>
				</div>
			</div>
		</template>

		<!-- Failed -->
		<template v-else-if="jobStatus === 'failed'">
			<NcEmptyContent :name="t('tables', 'Import failed')"
				:description="errorMessage || t('tables', 'An unknown error occurred.')">
				<template #icon>
					<IconAlertCircle :size="64" />
				</template>
			</NcEmptyContent>
			<div class="row space-T">
				<div class="actions-end">
					<NcButton :aria-label="t('tables', 'Close')"
						@click="$emit('close')">
						{{ t('tables', 'Close') }}
					</NcButton>
					<NcButton type="primary"
						:aria-label="t('tables', 'Retry')"
						@click="$emit('retry')">
						{{ t('tables', 'Retry') }}
					</NcButton>
				</div>
			</div>
		</template>

		<!-- Cancelled -->
		<template v-else-if="jobStatus === 'cancelled'">
			<NcEmptyContent :name="t('tables', 'Import cancelled')"
				:description="t('tables', 'The import was cancelled.')">
				<template #icon>
					<IconCancel :size="64" />
				</template>
			</NcEmptyContent>
			<div class="row space-T">
				<div class="actions-end">
					<NcButton :aria-label="t('tables', 'Close')"
						@click="$emit('close')">
						{{ t('tables', 'Close') }}
					</NcButton>
					<NcButton type="primary"
						:aria-label="t('tables', 'Start a new import')"
						@click="$emit('retry')">
						{{ t('tables', 'Start a new import') }}
					</NcButton>
				</div>
			</div>
		</template>

	</div>
</template>

<script>
import { NcButton, NcEmptyContent, NcLoadingIcon, NcProgressBar } from '@nextcloud/vue'
import IconCheck from 'vue-material-design-icons/Check.vue'
import IconAlertCircle from 'vue-material-design-icons/AlertCircle.vue'
import IconCancel from 'vue-material-design-icons/Cancel.vue'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'AirtableImportProgress',

	components: {
		NcButton,
		NcEmptyContent,
		NcLoadingIcon,
		NcProgressBar,
		IconCheck,
		IconAlertCircle,
		IconCancel,
	},

	props: {
		jobStatus: {
			type: String,
			default: null,
		},
		progressDone: {
			type: Number,
			default: 0,
		},
		progressTotal: {
			type: Number,
			default: 0,
		},
		errorMessage: {
			type: String,
			default: null,
		},
	},

	emits: ['cancel', 'retry', 'close', 'go-to-tables'],

	computed: {
		isActive() {
			return this.jobStatus === 'pending' || this.jobStatus === 'running'
		},
		progressPercent() {
			if (this.progressTotal <= 0) {
				return 0
			}
			return Math.round((this.progressDone / this.progressTotal) * 100)
		},
		progressDescription() {
			if (this.progressTotal > 0) {
				return t('tables', '{done} of {total} rows imported', {
					done: this.progressDone,
					total: this.progressTotal,
				})
			}
			return t('tables', 'Fetching schema and preparing tables…')
		},
	},
}
</script>

<style lang="scss" scoped>
.progress-bar {
	margin-block: calc(var(--default-grid-baseline) * 3);
}

.actions-end {
	display: flex;
	justify-content: flex-end;
	gap: calc(var(--default-grid-baseline) * 2);
	width: 100%;
}
</style>
