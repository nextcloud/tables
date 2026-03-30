/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { type Page } from '@playwright/test'

async function isInternalServerErrorPage(page: Page) {
	return page
		.getByRole('heading', { name: /Internal Server Error/i })
		.isVisible()
		.catch(() => false)
}

async function isLoginFormVisible(page: Page) {
	return page.locator('#user').isVisible({ timeout: 5000 }).catch(() => false)
}

async function openLoginPage(page: Page) {
	let lastError: Error | null = null

	for (let attempt = 1; attempt <= 3; attempt++) {
		try {
			await page.goto('/index.php/login', {
				waitUntil: 'domcontentloaded',
				timeout: 60000,
			})

			if (await isLoginFormVisible(page)) {
				return
			}

			if (await isInternalServerErrorPage(page)) {
				throw new Error('Login page returned an internal server error')
			}

			throw new Error('Login form was not visible')
		} catch (error) {
			lastError = error as Error
			await page.waitForTimeout(attempt * 1000)
		}
	}

	throw lastError ?? new Error('Failed to open the login page')
}

async function fetchRequestToken(
	page: Page,
	userId: string,
	password: string,
) {
	const tokenFromPage = await page.evaluate(() => {
		return document.head?.dataset?.requesttoken
			?? window.OC?.requestToken
			?? null
	}).catch(() => null)

	if (tokenFromPage) {
		return tokenFromPage
	}

	let lastError: Error | null = null

	for (let attempt = 1; attempt <= 3; attempt++) {
		try {
			const requestTokenRes = await page.request.get('/index.php/csrftoken', {
				headers: {
					Authorization:
						'Basic ' + Buffer.from(`${userId}:${password}`).toString('base64'),
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
		} catch (error) {
			lastError = error as Error
			await page.waitForTimeout(attempt * 1000)
		}
	}

	throw lastError ?? new Error('Failed to fetch CSRF token')
}

/**
 * Log in as the given user, or the admin user by default.
 * This method is best used in a beforeEach hook.
 *
 * @param page The page object to use
 * @param user Optional user object to log in as
 * @param user.userId The user id to log in with
 * @param user.password The password to log in with
 */
export async function login(
	page: Page,
	user?: { userId: string; password?: string },
): Promise<void> {
	const userId = user?.userId ?? process.env.NC_USER ?? 'admin'
	const password = user?.password ?? process.env.NC_PASS ?? 'admin'

	let lastError: Error | null = null

	for (let attempt = 1; attempt <= 3; attempt++) {
		try {
			await openLoginPage(page)
			await page.locator('#user').fill(userId)
			await page.locator('#password').fill(password)
			await page.locator('#password').press('Enter')

			// Login can land on different authenticated pages depending on enabled apps.
			await page.waitForFunction(
				() => !window.location.pathname.includes('/login'),
				undefined,
				{ timeout: 20000 },
			)
			await page.waitForLoadState('domcontentloaded', { timeout: 60000 })
			await page.locator('#user').waitFor({ state: 'hidden', timeout: 10000 }).catch(() => {})
			lastError = null
			break
		} catch (error) {
			lastError = error as Error

			if (attempt === 3) {
				break
			}

			if ((await isInternalServerErrorPage(page)) || (await isLoginFormVisible(page))) {
				await page.waitForTimeout(attempt * 1000)
				continue
			}

			throw error
		}
	}

	if (lastError) {
		throw lastError
	}

	// Reuse the token from the authenticated page when possible to avoid an extra flaky round-trip.
	const token = await fetchRequestToken(page, userId, password)
	await page.context().setExtraHTTPHeaders({ requesttoken: token })
}
