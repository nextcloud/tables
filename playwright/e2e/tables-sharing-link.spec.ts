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

async function createPublicLinkShare(page: Page, options: { password?: string, permissions?: { read?: boolean, create?: boolean, update?: boolean, delete?: boolean } } = {}) {
	const menuButton = page.locator('[data-cy="customTableAction"] button').first()
	await menuButton.waitFor({ state: 'visible' })
	await menuButton.hover()
	await menuButton.click({ force: true })
	await page.locator('[data-cy="dataTableShareBtn"]').click()
	await expect(page.getByText('Public links')).toBeVisible()

	const createShareReqPromise = page.waitForResponse(r => r.url().includes('/apps/tables/api/2/tables/') && r.url().includes('/share') && r.request().method() === 'POST')

	await page.locator('[data-cy="sharingEntryLinkCreateButton"]').click()

	if (options.password) {
		await page.locator('[data-cy="sharingEntryLinkPasswordCheck"]').click()
		await page.locator('[data-cy="sharingEntryLinkPasswordInput"] input').fill(options.password)
	}

	await page.locator('[data-cy="sharingEntryLinkCreateFormCreateButton"]').click()

	const interception = await createShareReqPromise
	expect(interception.status()).toBe(200)
	const body = await interception.json()
	const shareToken = body.ocs.data.shareToken
	expect(typeof shareToken).toBe('string')

	if (options.permissions) {
		await setSharePermissions(page, options.permissions)
	}

	return shareToken as string
}

async function setSharePermissions(page: Page, permissions: { read?: boolean, create?: boolean, update?: boolean, delete?: boolean }) {
	const isCanEdit = permissions.read && permissions.create && permissions.update && permissions.delete
	const isViewOnly = permissions.read && !permissions.create && !permissions.update && !permissions.delete

	await page.locator('.share-permission-select .action-item__menutoggle').click()

	if (isCanEdit) {
		await page.locator('button[role="menuitemradio"]').filter({ hasText: 'Can edit' }).click()
	} else if (isViewOnly) {
		await page.locator('button[role="menuitemradio"]').filter({ hasText: 'View only' }).click()
	} else {
		await page.locator('button[role="menuitemradio"]').filter({ hasText: 'Custom permissions' }).click()
		for (const [key, value] of Object.entries(permissions)) {
			if (value === undefined) continue
			const checkbox = page.locator(`[data-cy="sharePermission${key.charAt(0).toUpperCase() + key.slice(1)}"] input[type="checkbox"]`)
			const isChecked = await checkbox.isChecked()
			if (isChecked !== value) {
				await checkbox.click({ force: true })
				await page.waitForResponse(r => r.url().includes('/share/') && r.url().includes('/permissions') && r.request().method() === 'PUT')
				await page.waitForResponse(r => r.url().includes('/apps/tables/share/') && r.request().method() === 'GET')
			}
		}
		return
	}

	await page.waitForResponse(r => r.url().includes('/share/') && r.url().includes('/permissions') && r.request().method() === 'PUT')
}

test.describe('Public link sharing', () => {

	test('Create, access and delete a public link share', async ({ userPage: { page } }) => {
		const tableTitle = 'Public Share Test Table 1'
		await setupPublicShareTable(page, tableTitle)
		const origin = new URL(page.url()).origin
		const shareToken = await createPublicLinkShare(page)

		const publicContext = await page.context().browser()!.newContext()
		const publicPage = await publicContext.newPage()
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
		const origin = new URL(page.url()).origin
		const shareToken = await createPublicLinkShare(page, { password })

		const publicContext = await page.context().browser()!.newContext()
		const publicPage = await publicContext.newPage()
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

	test('View only public share has no create/edit/delete buttons', async ({ userPage: { page } }) => {
		const tableTitle = 'Public Share Permissions - View only'
		await setupPublicShareTable(page, tableTitle)
		const origin = new URL(page.url()).origin
		const shareToken = await createPublicLinkShare(page)

		const publicContext = await page.context().browser()!.newContext()
		const publicPage = await publicContext.newPage()
		await publicPage.goto(`${origin}/index.php/apps/tables/s/${shareToken}`)
		await expect(publicPage.locator('[data-cy="publicTableElement"]')).toBeVisible({ timeout: 15000 })
		await expect(publicPage.getByRole('button', { name: /Create row/i })).toHaveCount(0)
		await expect(publicPage.locator('[data-cy="editRowBtn"]')).toHaveCount(0)
		await publicContext.close()
	})

	test('Can edit public share allows create, edit and delete rows', async ({ userPage: { page } }) => {
		const tableTitle = 'Public Share Permissions Test - Can edit'
		await setupPublicShareTable(page, tableTitle)
		const origin = new URL(page.url()).origin
		const shareToken = await createPublicLinkShare(page, {
			permissions: { read: true, create: true, update: true, delete: true },
		})

		const publicContext = await page.context().browser()!.newContext()
		const publicPage = await publicContext.newPage()
		await publicPage.goto(`${origin}/index.php/apps/tables/s/${shareToken}`)
		await expect(publicPage.locator('[data-cy="publicTableElement"]')).toBeVisible({ timeout: 15000 })

		// Verify create button exists
		await expect(publicPage.getByRole('button', { name: /Create row/i })).toBeVisible()

		// Create a row
		await publicPage.getByRole('button', { name: /Create row/i }).click()
		await expect(publicPage.locator('[data-cy="createRowModal"]')).toBeVisible()
		await publicPage.locator('[data-cy="createRowModal"]').getByRole('textbox').first().fill('Test row for editing')
		await publicPage.locator('[data-cy="createRowSaveButton"]').click()
		await expect(publicPage.locator('[data-cy="ncTable"]').getByText('Test row for editing')).toBeVisible()

		// Verify edit button exists and edit the row
		await expect(publicPage.locator('[data-cy="editRowBtn"]').last()).toBeVisible()
		await publicPage.locator('[data-cy="editRowBtn"]').last().click()
		await expect(publicPage.locator('[data-cy="editRowModal"]')).toBeVisible()
		await publicPage.locator('[data-cy="editRowModal"]').getByRole('textbox').first().fill('Updated row')
		await publicPage.locator('[data-cy="editRowSaveButton"]').click()
		await expect(publicPage.locator('[data-cy="ncTable"]').getByText('Updated row')).toBeVisible()
		await expect(publicPage.locator('[data-cy="ncTable"]').getByText('Test row for editing')).toHaveCount(0)

		// Delete the row
		await publicPage.locator('[data-cy="editRowBtn"]').last().click()
		await expect(publicPage.locator('[data-cy="editRowModal"]')).toBeVisible()
		await publicPage.locator('[data-cy="editRowDeleteButton"]').click()
		await publicPage.locator('[data-cy="editRowDeleteConfirmButton"]').click()
		await expect(publicPage.locator('[data-cy="editRowModal"]')).toBeHidden()
		await expect(publicPage.locator('[data-cy="ncTable"]').getByText('Updated row')).toHaveCount(0)

		await publicContext.close()
	})

	test('Create only public share shows form mode and allows submitting', async ({ userPage: { page } }) => {
		const tableTitle = 'Public Share Permissions Test - Create only'
		await setupPublicShareTable(page, tableTitle)
		const origin = new URL(page.url()).origin
		const shareToken = await createPublicLinkShare(page, {
			permissions: { read: false, create: true, update: false, delete: false },
		})

		const publicContext = await page.context().browser()!.newContext()
		const publicPage = await publicContext.newPage()
		await publicPage.goto(`${origin}/index.php/apps/tables/s/${shareToken}`)

		await expect(publicPage.getByText('This is a public form.')).toBeVisible({ timeout: 15000 })
		await expect(publicPage.getByRole('button', { name: /Fill form/i })).toBeVisible()
		await expect(publicPage.locator('[data-cy="ncTable"] table')).toHaveCount(0)

		await publicPage.getByRole('button', { name: /Fill form/i }).click()
		await expect(publicPage.locator('[data-cy="createRowModal"]')).toBeVisible()
		await publicPage.locator('[data-cy="createRowModal"]').getByRole('textbox').first().fill('Form submission')
		await publicPage.locator('[data-cy="createRowSaveButton"]').click()
		await expect(publicPage.locator('.toastify.toast-success')).toBeVisible()
		await publicContext.close()

		await page.goto('/index.php/apps/tables')
		await loadTable(page, tableTitle)
		await expect(page.locator('[data-cy="ncTable"]').getByText('Form submission')).toBeVisible()
	})
})
