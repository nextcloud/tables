/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import { startAirtableImport, getAirtableImportStatus, cancelAirtableImport } from '../api/airtableImport.js'

/**
 * Pinia store for the active Airtable import job.
 *
 * Owns all job state (id, status, progress, result) and the three
 * API calls so that:
 *  - AirtableImportModal.vue can bind to reactive job state without
 *    duplicating it in component-local data.
 *  - The notification handler (F0.6) can read importedTableIds to
 *    navigate the user to the right table after a background import.
 *
 * The polling *timer* stays in AirtableImportModal.vue because timers
 * need a component lifecycle anchor (beforeUnmount) to be safely
 * cleaned up.
 */
export const useAirtableImportStore = defineStore('airtableImport', {
	state: () => ({
		/** @type {number|null} */
		jobId: null,
		/** @type {null|'pending'|'running'|'finished'|'failed'|'cancelled'} */
		jobStatus: null,
		progressDone: 0,
		progressTotal: 0,
		/** @type {string|null} */
		errorMessage: null,
		/** @type {number[]} IDs of tables created by the last finished import */
		importedTableIds: [],
	}),

	getters: {
		/** True while the job is still running (pending or running). */
		isJobActive: (state) => state.jobStatus === 'pending' || state.jobStatus === 'running',

		/** True once a job has been queued (even if now finished). */
		hasActiveJob: (state) => state.jobId !== null,
	},

	actions: {
		/**
		 * Enqueue a new import job and store the returned job ID + initial
		 * status.  Throws on network / API errors so the caller can show UI.
		 *
		 * @param {string}      shareUrl
		 * @param {string|null} sessionCookie
		 */
		async startJob(shareUrl, sessionCookie = null) {
			const data = await startAirtableImport(shareUrl, sessionCookie)
			this.jobId = data.jobId
			this.jobStatus = data.status
			this.progressDone = 0
			this.progressTotal = 0
			this.errorMessage = null
			this.importedTableIds = []
		},

		/**
		 * Fetch the latest status from the server and update store state.
		 * Returns the raw response data so the polling component can decide
		 * whether to stop the timer.  Throws on network errors.
		 *
		 * @return {Promise<object>}
		 */
		async pollStatus() {
			if (this.jobId === null) {
				return null
			}
			const data = await getAirtableImportStatus(this.jobId)
			this.jobStatus = data.status
			this.progressDone = data.progressDone ?? 0
			this.progressTotal = data.progressTotal ?? 0
			this.errorMessage = data.errorMessage ?? null
			if (Array.isArray(data.importedTableIds)) {
				this.importedTableIds = data.importedTableIds
			}
			return data
		},

		/**
		 * Request cancellation of the current job.  Updates status to
		 * 'cancelled' optimistically on success.  Throws on API errors.
		 */
		async cancelJob() {
			if (this.jobId === null) {
				return
			}
			await cancelAirtableImport(this.jobId)
			this.jobStatus = 'cancelled'
		},

		/** Reset all job state (called when the user closes/retries). */
		resetJob() {
			this.jobId = null
			this.jobStatus = null
			this.progressDone = 0
			this.progressTotal = 0
			this.errorMessage = null
			this.importedTableIds = []
		},
	},
})
