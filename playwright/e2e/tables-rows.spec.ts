/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test as base } from '@playwright/test'
import { test, expect } from '../support/fixtures'
import type { BrowserContext, Page } from '@playwright/test'
import { createRandomUser } from '../support/api'
import { login } from '../support/login'
import { createTable, createTextLineColumn, fillInValueTextLine, loadTable, openCreateRowModal, openRowActionMenu } from '../support/commands'

test.describe('Rows for a table', () => {
	test.describe.configure({ mode: 'serial' })

	let context: BrowserContext
	let page: Page

	// @ts-expect-error - Playwright complex types mismatch in this environment
	base.beforeAll(async ({ browser, baseURL }) => {
		context = await browser.newContext({
			baseURL,
		})
		page = await context.newPage()

		const user = await createRandomUser(page.request)
		await login(page, user)
	}, 120000)

	test.afterAll(async () => {
		await context?.close()
	})

	test.beforeEach(async () => {
		await page.goto('/index.php/apps/tables')
		// Ensure no modals are left open.
		await page.keyboard.press('Escape')
	})

	test.setTimeout(60000)

	test('Create row', async () => {
		await page.goto('/index.php/apps/tables')
		await loadTable(page, 'Welcome to Nextcloud Tables!')
		await expect(page.locator('.icon-loading').first()).toBeHidden()
		await openCreateRowModal(page)

		await page.locator('[data-cy="createRowModal"] .slot input').first().fill('My first task')
		const editor = page.locator('[data-cy="createRowModal"] .ProseMirror').first()
		await editor.waitFor({ state: 'visible' })
		await editor.click()
		await editor.clear()
		await editor.fill('My first description')

		await page.locator('[data-cy="createRowModal"] [aria-label="Increase stars"]').click()
		await page.locator('[data-cy="createRowModal"] [aria-label="Increase stars"]').click()

		await page.locator('[data-cy="createRowSaveButton"]').click()

		await expect(page.locator('[data-cy="createRowModal"]')).toBeHidden()
		await expect(page.locator('[data-cy="ncTable"] table').filter({ hasText: 'My first task' }).first()).toBeVisible()
	})

	test('Edit and Delete row', async () => {
		await page.goto('/index.php/apps/tables')
		await createTable(page, 'Row edit table')
		await createTextLineColumn(page, 'title', '', '', true)

		await openCreateRowModal(page)
		await fillInValueTextLine(page, 'title', 'My first task')
		await page.locator('[data-cy="createRowSaveButton"]').click()
		await expect(page.locator('[data-cy="createRowModal"]')).toBeHidden()
		const createdRow = page.locator('[data-cy="ncTable"] [data-cy="customTableRow"]').filter({ hasText: 'My first task' }).first()
		await expect(createdRow).toBeVisible({ timeout: 10000 })

		// Edit
		await createdRow.getByText('My first task', { exact: true }).click()
		const titleInput = page.getByRole('textbox', { name: 'Cell input' }).first()
		await expect(titleInput).toBeVisible({ timeout: 10000 })
		const updateReqPromise = page.waitForResponse(r => r.url().includes('/apps/tables/row/') && r.request().method() === 'PUT')
		await titleInput.clear()
		await titleInput.fill('Changed column value')
		await titleInput.press('Enter')
		await updateReqPromise
		await expect(page.locator('.cell-input input').first()).toBeHidden({ timeout: 10000 })
		await expect(
			page.locator('[data-cy="ncTable"] [data-cy="customTableRow"]').filter({ hasText: 'Changed column value' }).first(),
		).toBeVisible({ timeout: 10000 })
		await expect(
			page.locator('[data-cy="ncTable"] [data-cy="customTableRow"]').filter({ hasText: 'My first task' }),
		).toHaveCount(0, { timeout: 10000 })

		// Delete
		const rowToDelete = page.locator('[data-cy="ncTable"] [data-cy="customTableRow"]').first()
		await openRowActionMenu(page, rowToDelete)
		await page.locator('[data-cy="editRowBtn"]').click()

		const deleteReqPromise = page.waitForResponse(r => r.url().includes('/apps/tables/row/') && r.request().method() === 'DELETE')
		await page.locator('[data-cy="editRowDeleteButton"]').click()
		await page.locator('[data-cy="editRowDeleteConfirmButton"]').click()
		await deleteReqPromise

		await expect(page.locator('[data-cy="editRowModal"]')).toBeHidden()
		await page.reload({ waitUntil: 'domcontentloaded' })
		await expect(page.locator('.icon-loading').first()).toBeHidden({ timeout: 10000 })
		await expect(page.locator('[data-cy="ncTable"] [data-cy="customTableRow"]')).toHaveCount(0, { timeout: 10000 })
	})

	test('Check mandatory fields error', async () => {
		await page.goto('/index.php/apps/tables')
		await expect(page.locator('.icon-loading').first()).toBeHidden()
		await page.locator('[data-cy="navigationCreateTableIcon"]').click({ force: true })

		const createModal = page.locator('[data-cy="createTableModal"]')
		await createModal.locator('input[type="text"]').clear()
		await createModal.locator('input[type="text"]').fill('to do list')
		await page.locator('.tile').filter({ hasText: 'ToDo' }).first().click({ force: true })

		await expect(createModal).toBeVisible()
		await page.locator('[data-cy="createTableSubmitBtn"]').click()

		await loadTable(page, 'to do list')
		await page.locator('[data-cy="createRowBtn"]').click({ force: true })

		await expect(page.locator('[data-cy="createRowModal"] .notecard--error')).toBeVisible()
		await expect(page.locator('[data-cy="createRowSaveButton"]')).toBeDisabled()

		await page.locator('[data-cy="createRowModal"] .slot input').first().fill('My first task')
		await expect(page.locator('[data-cy="createRowModal"] .notecard--error')).toBeHidden()
		await expect(page.locator('[data-cy="createRowSaveButton"]')).toBeEnabled()
		await page.locator('[data-cy="createRowSaveButton"]').click()

		await loadTable(page, 'to do list')
		const mandatoryRow = page.locator('[data-cy="ncTable"] [data-cy="customTableRow"]').filter({ hasText: 'My first task' }).first()
		await openRowActionMenu(page, mandatoryRow)
		await page.locator('[data-cy="editRowBtn"]').click()
		await expect(page.locator('[data-cy="editRowModal"] .notecard--error')).toBeHidden()

		await page.locator('[data-cy="editRowModal"] .slot input').first().clear()
		await expect(page.locator('[data-cy="editRowModal"] .notecard--error')).toBeVisible()
		await expect(page.locator('[data-cy="editRowSaveButton"]')).toBeDisabled()
	})

	test('Delete row via row action menu', async () => {
		await page.goto('/index.php/apps/tables')
		await createTable(page, 'Row delete menu table')
		await createTextLineColumn(page, 'title', '', '', true)

		await openCreateRowModal(page)
		await fillInValueTextLine(page, 'title', 'Row to delete')
		await page.locator('[data-cy="createRowSaveButton"]').click()
		await expect(page.locator('[data-cy="createRowModal"]')).toBeHidden()

		const rowToDelete = page.locator('[data-cy="ncTable"] [data-cy="customTableRow"]').filter({ hasText: 'Row to delete' }).first()
		await expect(rowToDelete).toBeVisible({ timeout: 10000 })

		const deleteReqPromise = page.waitForResponse(r => r.url().includes('/apps/tables/row/') && r.request().method() === 'DELETE')
		await openRowActionMenu(page, rowToDelete)
		await page.locator('[data-cy="deleteRowBtn"]').click()
		await page.locator('[data-cy="confirmDialog"]').getByRole('button', { name: 'Confirm' }).click()
		await deleteReqPromise

		await expect(page.locator('[data-cy="ncTable"] [data-cy="customTableRow"]')).toHaveCount(0, { timeout: 10000 })
	})

	test('Copy row via row action menu', async () => {
		await page.goto('/index.php/apps/tables')
		await createTable(page, 'Row copy menu table')
		await createTextLineColumn(page, 'title', '', '', true)

		await openCreateRowModal(page)
		await fillInValueTextLine(page, 'title', 'Original row')
		await page.locator('[data-cy="createRowSaveButton"]').click()
		await expect(page.locator('[data-cy="createRowModal"]')).toBeHidden()

		const originalRow = page.locator('[data-cy="ncTable"] [data-cy="customTableRow"]').filter({ hasText: 'Original row' }).first()
		await expect(originalRow).toBeVisible({ timeout: 10000 })

		// Open copy dialog via the row action menu
		await openRowActionMenu(page, originalRow)
		await page.locator('[data-cy="copyRowBtn"]').click()

		// Verify create modal opens with pre-filled value
		await expect(page.locator('[data-cy="createRowModal"]')).toBeVisible({ timeout: 10000 })
		await expect(page.locator('[data-cy="createRowModal"] input').first()).toHaveValue('Original row')

		// Save the copy
		await page.locator('[data-cy="createRowSaveButton"]').click()
		await expect(page.locator('[data-cy="createRowModal"]')).toBeHidden()

		// Both original and copy should be visible
		await expect(page.locator('[data-cy="ncTable"] [data-cy="customTableRow"]').filter({ hasText: 'Original row' })).toHaveCount(2, { timeout: 10000 })
	})

	test('Inline Edit', async () => {
		await page.goto('/index.php/apps/tables')
		// Create a test row first
		await loadTable(page, 'Welcome to Nextcloud Tables!')
		await page.locator('[data-cy="createRowBtn"]').click({ force: true })
		await page.locator('[data-cy="createRowModal"] .slot input').first().fill('Test inline editing')
		const createReqPromise = page.waitForResponse(r => r.url().includes('/rows') && r.request().method() === 'POST')
		await page.locator('[data-cy="createRowSaveButton"]').click()
		await createReqPromise

		await expect(page.locator('[data-cy="createRowModal"]')).toBeHidden()
		const row = page.locator('[data-cy="ncTable"] [data-cy="customTableRow"]').filter({ hasText: 'Test inline editing' }).first()
		await expect(row).toBeVisible()

		// Inline editing is triggered by clicking the cell content.
		await row.getByText('Test inline editing', { exact: true }).click()

		// Verify the input field appears and is focused
		const inputField = page.getByRole('textbox', { name: 'Cell input' }).first()
		await inputField.waitFor({ state: 'visible' })
		await expect(inputField).toBeVisible()
		await expect(inputField).toBeFocused()

		// Change the content
		const cellInput = page.locator('.cell-input input').first()
		await cellInput.clear()
		await cellInput.pressSequentially('Edited inline')
		await cellInput.press('Enter')

		// Verify the edit was saved
		await expect(page.locator('.icon-loading-small').first()).toBeHidden()
		await expect(page.locator('[data-cy="ncTable"] table').filter({ hasText: 'Edited inline' }).first()).toBeVisible()
		await expect(page.locator('[data-cy="ncTable"] table', { hasText: 'Test inline editing' })).toBeHidden()
	})
})
