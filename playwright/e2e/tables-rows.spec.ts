/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test, expect } from '../support/fixtures'
import { createTable, createTextLineColumn, fillInValueTextLine, loadTable, openCreateRowModal } from '../support/commands'

test.describe('Rows for a table', () => {
	test.setTimeout(60000)

	test('Create row', async ({ userPage: { page } }) => {
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

	test('Edit and Delete row', async ({ userPage: { page } }) => {
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
		await page.locator('[data-cy="ncTable"] [data-cy="customTableRow"]').first().locator('[data-cy="editRowBtn"]').click()

		const deleteReqPromise = page.waitForResponse(r => r.url().includes('/apps/tables/row/') && r.request().method() === 'DELETE')
		await page.locator('[data-cy="editRowDeleteButton"]').click()
		await page.locator('[data-cy="editRowDeleteConfirmButton"]').click()
		await deleteReqPromise

		await expect(page.locator('[data-cy="editRowModal"]')).toBeHidden()
		await page.reload({ waitUntil: 'domcontentloaded' })
		await expect(page.locator('.icon-loading').first()).toBeHidden({ timeout: 10000 })
		await expect(page.locator('[data-cy="ncTable"] [data-cy="customTableRow"]')).toHaveCount(0, { timeout: 10000 })
	})

	test('Check mandatory fields error', async ({ userPage: { page } }) => {
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
		await page.locator('[data-cy="ncTable"] [data-cy="customTableRow"]:has-text("My first task")').locator('[data-cy="editRowBtn"]').click()
		await expect(page.locator('[data-cy="editRowModal"] .notecard--error')).toBeHidden()

		await page.locator('[data-cy="editRowModal"] .slot input').first().clear()
		await expect(page.locator('[data-cy="editRowModal"] .notecard--error')).toBeVisible()
		await expect(page.locator('[data-cy="editRowSaveButton"]')).toBeDisabled()
	})

	test('Inline Edit', async ({ userPage: { page } }) => {
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
