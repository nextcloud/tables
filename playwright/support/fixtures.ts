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
	userPage: [async ({ browser, baseURL }, use) => {
		const context = await browser.newContext({ baseURL })
		const page = await context.newPage()

		// Create a random user for this test
		const user = await createRandomUser(page.request)
		await context.clearCookies()

		// Login as the user (this also sets the requesttoken)
		await login(page, user)

		await use({ page, user })

		await context.close()
	}, { timeout: 120000 }],
})

export { expect }
