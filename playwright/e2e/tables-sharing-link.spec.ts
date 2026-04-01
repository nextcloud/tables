/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test, expect } from '../support/fixtures'
import type { Page } from '@playwright/test'
import { loadTable } from '../support/commands'

async function setupPublicShareTable(page: Page, title: string) {
	await page.goto('/index.php/apps/tables')
	await expect(page.locator('.icon-loading').first()).toBeHidden()
	await page.locator('[data-cy="navigationCreateTableIcon"]').click({ force: true })

	const createModal = page.locator('.modal__content')
	await createModal.waitFor({ state: 'visible' })
	await createModal.locator('input[type="text"]').clear()
	await createModal.locator('input[type="text"]').fill(title)
	await page.locator('.tile').filter({ hasText: 'ToDo' }).first().click({ force: true })
	await page.locator('[data-cy="createTableSubmitBtn"]').scrollIntoViewIfNeeded()
	await page.locator('[data-cy="createTableSubmitBtn"]').click()

	await loadTable(page, title)
}

test.describe('Public link sharing', () => {

	test('Create, access and delete a public link share', async ({ userPage: { page } }) => {
		const tableTitle = 'Public Share Test Table 1'
		await setupPublicShareTable(page, tableTitle)

		const menuButton1 = page.locator('[data-cy="customTableAction"] button').first()
		await menuButton1.waitFor({ state: 'visible' })
		await menuButton1.hover()
		await menuButton1.click({ force: true })
		await page.locator('[data-cy="dataTableShareBtn"]').click()
		await expect(page.getByText('Public links')).toBeVisible()

		const createShareReqPromise = page.waitForResponse(r => r.url().includes('/apps/tables/api/2/tables/') && r.url().includes('/share') && r.request().method() === 'POST')
		await page.locator('[data-cy="sharingEntryLinkCreateButton"]').click()
		await page.locator('[data-cy="sharingEntryLinkCreateFormCreateButton"]').click()

		const interception = await createShareReqPromise
		expect(interception.status()).toBe(200)
		const body = await interception.json()
		const shareToken = body.ocs.data.shareToken
		expect(typeof shareToken).toBe('string')

		const publicContext = await page.context().browser()!.newContext()
		const publicPage = await publicContext.newPage()
		const origin = new URL(page.url()).origin
		await publicPage.goto(`${origin}/index.php/apps/tables/s/${shareToken}`)
		await expect(publicPage.locator('[data-cy="publicTableElement"]')).toBeVisible({ timeout: 15000 })
		await expect(publicPage.locator('h1').filter({ hasText: tableTitle })).toBeVisible({ timeout: 15000 })
		await expect(publicPage.getByRole('button', { name: /Create row/i })).toHaveCount(0)
		await publicContext.close()

		// Login again to delete share
		await page.goto('/index.php/apps/tables')
		await loadTable(page, tableTitle)

		const menuButton2 = page.locator('[data-cy="customTableAction"] button').first()
		await menuButton2.waitFor({ state: 'visible' })
		await menuButton2.hover()
		await menuButton2.click({ force: true })
		await page.locator('[data-cy="dataTableShareBtn"]').click()

		await expect(page.locator('[data-cy="sharingEntryLinkTitle"]')).toBeVisible()
		await page.locator('[data-cy="sharingEntryLinkDeleteButton"]').click()

		await expect(page.locator('[data-cy="sharingEntryLinkTitle"]')).toBeHidden()

		// Verify share is gone
		const verifyContext = await page.context().browser()!.newContext()
		const verifyPage = await verifyContext.newPage()
		await verifyPage.goto(`${origin}/index.php/apps/tables/s/${shareToken}`)
		await expect(verifyPage.locator('h2').filter({ hasText: 'Share not found' })).toBeVisible()
		await verifyContext.close()
	})

	test('Create, access and delete a password protected public link share', async ({ userPage: { page } }) => {
		const tableTitle = 'Public Share Test Table 2'
		await setupPublicShareTable(page, tableTitle)
		const password = 'extremelySafePassword123'

		const menuButton3 = page.locator('[data-cy="customTableAction"] button').first()
		await menuButton3.waitFor({ state: 'visible' })
		await menuButton3.hover()
		await menuButton3.click({ force: true })
		await page.locator('[data-cy="dataTableShareBtn"]').click()
		await expect(page.getByText('Public links')).toBeVisible()

		const createShareReqPromise = page.waitForResponse(r => r.url().includes('/apps/tables/api/2/tables/') && r.url().includes('/share') && r.request().method() === 'POST')

		// Open create form
		await page.locator('[data-cy="sharingEntryLinkCreateButton"]').click()

		// Set password
		await page.locator('[data-cy="sharingEntryLinkPasswordCheck"]').click()
		await page.locator('[data-cy="sharingEntryLinkPasswordInput"] input').fill(password)

		// Create
		await page.locator('[data-cy="sharingEntryLinkCreateFormCreateButton"]').click()

		const interception = await createShareReqPromise
		expect(interception.status()).toBe(200)
		const body = await interception.json()
		const shareToken = body.ocs.data.shareToken
		expect(typeof shareToken).toBe('string')

		const publicContext = await page.context().browser()!.newContext()
		const publicPage = await publicContext.newPage()
		const origin = new URL(page.url()).origin
		await publicPage.goto(`${origin}/index.php/apps/tables/s/${shareToken}`)

		// Password Gate
		await expect(publicPage.locator('input[type="password"]')).toBeVisible()
		await publicPage.locator('input[type="password"]').pressSequentially(password, { delay: 50 })
		const submitBtn = publicPage.locator('button[type="submit"], input[type="submit"]').first()
		await expect(submitBtn).toBeEnabled({ timeout: 5000 })
		await submitBtn.click()
		await expect(publicPage.locator('[data-cy="publicTableElement"]')).toBeVisible()
		await publicContext.close()

		// Login again to delete share
		await page.goto('/index.php/apps/tables')
		await loadTable(page, tableTitle)

		await page.locator('[data-cy="customTableAction"] button').click()
		await page.locator('[data-cy="dataTableShareBtn"]').click()

		await expect(page.locator('[data-cy="sharingEntryLinkTitle"]')).toBeVisible()
		await page.locator('[data-cy="sharingEntryLinkDeleteButton"]').click()

		await expect(page.locator('[data-cy="sharingEntryLinkTitle"]')).toBeHidden()

		// Verify share is gone
		const verifyContext = await page.context().browser()!.newContext()
		const verifyPage = await verifyContext.newPage()
		await verifyPage.goto(`${origin}/index.php/apps/tables/s/${shareToken}`)
		await expect(verifyPage.locator('h2').filter({ hasText: 'Share not found' })).toBeVisible()
		await verifyContext.close()
	})
})
