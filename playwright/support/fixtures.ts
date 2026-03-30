/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { type Page, test as base, expect } from '@playwright/test'
import { createRandomUser, type TestUser } from './api'
import { login } from './login'

export const test = base.extend<{
	userPage: {
		page: Page
		user: TestUser
	}
}>({
	// eslint-disable-next-line no-empty-pattern
	userPage: [async ({ browser, baseURL }, use, testInfo) => {
		const context = await browser.newContext({ baseURL })
		const page = await context.newPage()
		const logPrefix = `[fixture][worker ${testInfo.workerIndex}] ${testInfo.title}`

		if (process.env.CI) {
			console.log(`${logPrefix} creating user`)
		}

		// Create a random user for this test
		const user = await createRandomUser(page.request)
		await context.clearCookies()

		if (process.env.CI) {
			console.log(`${logPrefix} logging in as ${user.userId}`)
		}

		// Login as the user (this also sets the requesttoken)
		await login(page, user)

		if (process.env.CI) {
			console.log(`${logPrefix} fixture ready`)
		}

		await use({ page, user })

		await context.close()
	}, { timeout: 120000 }],
})

export { expect }
