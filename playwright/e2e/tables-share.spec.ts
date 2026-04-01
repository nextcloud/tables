/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test, expect } from '../support/fixtures'
import { createRandomUser } from '../support/api'
import { loadTable } from '../support/commands'
import { login } from '../support/login'

test.describe('Share a table', () => {
	const tableTitle = 'Shared todo'

	test('Share table', async ({ userPage: { page }, request }) => {
		const localUser2 = await createRandomUser(request)

		await page.goto('/index.php/apps/tables')

		await page.locator('[data-cy="navigationCreateTableIcon"]').click({ force: true })

		const createModal = page.locator('.modal__content')
		await createModal.locator('input[type="text"]').clear()
		await createModal.locator('input[type="text"]').fill(tableTitle)
		await page.locator('.tile').filter({ hasText: 'ToDo' }).click({ force: true })
		await page.locator('[data-cy="createTableSubmitBtn"]').scrollIntoViewIfNeeded()
		await page.locator('[data-cy="createTableSubmitBtn"]').click()

		await loadTable(page, tableTitle)

		await page.locator('[data-cy="customTableAction"] button').click()
		await page.locator('[data-cy="dataTableShareBtn"]').click()

		const userSearchReqPromise = page.waitForResponse(r => r.url().includes(`/autocomplete/get?search=${localUser2.userId}`) && r.request().method() === 'GET')
		const shareInput = page.locator('[data-cy="shareFormSelect"] input')
		await shareInput.click()
		await shareInput.pressSequentially(localUser2.userId, { delay: 50 })
		await userSearchReqPromise

		const userItem = page.locator('.vs__dropdown-menu li').filter({ hasText: localUser2.userId }).first()
		await userItem.waitFor({ state: 'visible', timeout: 10000 })
		await userItem.click()
		await expect(page.locator('[data-cy="sharedWithList"]')).toContainText(localUser2.userId)

		// Create a new context to login as a different user
		await page.context().clearCookies()
		await login(page, localUser2)
		await page.goto('/index.php/apps/tables')

		await expect(page.locator('[data-cy="navigationTableItem"]').filter({ hasText: tableTitle })).toBeVisible()
	})
})
