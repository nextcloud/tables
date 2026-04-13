/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const BASE_URL = '/apps/tables/api/1/import/airtable'

/**
 * Enqueue a new Airtable import job.
 *
 * @param {string}      shareUrl      Public Airtable share URL.
 * @param {string|null} sessionCookie Optional __Host-airtable-session cookie
 *                                    value for private bases.
 * @return {Promise<{jobId: number, status: string}>}
 */
export async function startAirtableImport(shareUrl, sessionCookie = null) {
	const res = await axios.post(generateUrl(BASE_URL), {
		shareUrl,
		sessionCookie: sessionCookie || null,
	})
	return res.data
}

/**
 * Poll the status of an existing Airtable import job.
 *
 * @param {number} jobId
 * @return {Promise<{status: string, progressDone: number, progressTotal: number, errorMessage: string|null}>}
 */
export async function getAirtableImportStatus(jobId) {
	const res = await axios.get(generateUrl(`${BASE_URL}/${jobId}/status`))
	return res.data
}

/**
 * Cancel a pending or running Airtable import job.
 *
 * @param {number} jobId
 * @return {Promise<void>}
 */
export async function cancelAirtableImport(jobId) {
	await axios.delete(generateUrl(`${BASE_URL}/${jobId}`))
}
