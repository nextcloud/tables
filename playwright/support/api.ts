/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { APIRequestContext } from '@playwright/test'

export interface TestUser {
	userId: string
	password: string
}

type OcsRequestForm = Record<string, string | number | boolean>
type OcsRequestData = Record<string, unknown>

const REQUEST_TIMEOUT = 30000

async function fetchRequestToken(
	request: APIRequestContext,
	user: TestUser,
) {
	let lastError: Error | null = null

	for (let attempt = 1; attempt <= 3; attempt++) {
		try {
			const requestTokenRes = await request.get('/index.php/csrftoken', {
				timeout: REQUEST_TIMEOUT,
				headers: {
					Authorization:
						'Basic '
						+ Buffer.from(`${user.userId}:${user.password}`).toString('base64'),
				},
			})

			if (!requestTokenRes.ok()) {
				throw new Error(`Failed to fetch CSRF token: ${requestTokenRes.status()}`)
			}

			const { token } = await requestTokenRes.json()
			if (!token) {
				throw new Error('CSRF token response did not include a token')
			}

			return token
		} catch (error: unknown) {
			lastError = error instanceof Error ? error : new Error(String(error))
		}

		await new Promise(resolve => setTimeout(resolve, attempt * 1000))
	}

	throw lastError ?? new Error('Failed to fetch CSRF token')
}

export async function createRandomUser(request: APIRequestContext) {
	const password = 'extremelySafePassword123'
	let userId = ''
	let lastError: Error | null = null

	// Stagger the initial request slightly to avoid race conditions on the admin account
	await new Promise(resolve => setTimeout(resolve, Math.random() * 1500))

	for (let attempt = 1; attempt <= 5; attempt++) {
		userId = 'user_' + Date.now() + '_' + Math.random().toString(36).slice(2, 10)
		try {
			const res = await request.post('/ocs/v2.php/cloud/users?format=json', {
				timeout: REQUEST_TIMEOUT,
				headers: {
					'OCS-APIRequest': 'true',
					Authorization: 'Basic ' + Buffer.from('admin:admin').toString('base64'),
				},
				form: {
					userid: userId,
					password,
					displayname: userId,
				},
			})

			if (res.ok()) {
				return { userId, password }
			}
			lastError = new Error(`Failed to create user (attempt ${attempt}): ` + (await res.text()))
		} catch (error: unknown) {
			lastError = error instanceof Error ? error : new Error(String(error))
		}
		// Wait before retry
		await new Promise(resolve => setTimeout(resolve, attempt * 1500))
	}

	throw lastError || new Error('Failed to create user after multiple attempts')
}

export async function ocsRequest(
	request: APIRequestContext,
	user: TestUser,
	options: { method: string; url: string; data?: OcsRequestData; form?: OcsRequestForm },
) {
	const res = await request.fetch(options.url, {
		timeout: REQUEST_TIMEOUT,
		method: options.method,
		headers: {
			'OCS-APIRequest': 'true',
			Authorization:
				'Basic '
				+ Buffer.from(`${user.userId}:${user.password}`).toString('base64'),
			'Content-Type': options.form
				? 'application/x-www-form-urlencoded'
				: 'application/json',
		},
		data: options.data,
		form: options.form,
	})

	if (!res.ok()) {
		throw new Error(
			`OCS Request failed: ${options.method} ${
				options.url
			} - ${await res.text()}`,
		)
	}

	return res
}

export async function createGroup(
	request: APIRequestContext,
	groupName: string,
) {
	await request.post('/ocs/v2.php/cloud/groups?format=json', {
		timeout: REQUEST_TIMEOUT,
		headers: {
			'OCS-APIRequest': 'true',
			Authorization: 'Basic ' + Buffer.from('admin:admin').toString('base64'),
		},
		form: {
			groupid: groupName,
			displayname: groupName,
		},
	})
}

export async function addUserToGroup(
	request: APIRequestContext,
	userId: string,
	groupId: string,
) {
	await request.post(`/ocs/v2.php/cloud/users/${userId}/groups?format=json`, {
		timeout: REQUEST_TIMEOUT,
		headers: {
			'OCS-APIRequest': 'true',
			Authorization: 'Basic ' + Buffer.from('admin:admin').toString('base64'),
		},
		form: {
			groupid: groupId,
		},
	})
}

export async function uploadFile(
	request: APIRequestContext,
	user: TestUser,
	fileName: string,
	mimeType: string,
	content: Buffer | string,
) {
	const token = await fetchRequestToken(request, user)

	const res = await request.put(`/remote.php/webdav/${fileName}`, {
		timeout: REQUEST_TIMEOUT,
		headers: {
			requesttoken: token,
			'Content-Type': mimeType,
			Authorization:
				'Basic '
				+ Buffer.from(`${user.userId}:${user.password}`).toString('base64'),
		},
		data: content,
	})

	if (!res.ok()) {
		throw new Error(`Upload failed: ${await res.text()}`)
	}

	return Number(res.headers()['oc-fileid']?.replace('oc', ''))
}
