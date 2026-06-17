/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test, expect } from '../support/fixtures'
import * as fs from 'fs'
import * as path from 'path'
import { fileURLToPath } from 'url'
import { uploadFile } from '../support/api'
import { createTable, createTextLinkColumn, loadTable, openRowActionMenu } from '../support/commands'
const __dirname = path.dirname(fileURLToPath(import.meta.url))

test.describe('Test column text-link', () => {

	test('Manage text-link column', async ({ userPage: { page, user } }) => {
		test.setTimeout(60000)
		const photoContent = fs.readFileSync(path.join(__dirname, '../fixtures/photo-test-1.jpeg'))
		await uploadFile(page.request, user, 'photo-test-1.jpeg', 'image/jpeg', photoContent)

		const pdfContent = fs.readFileSync(path.join(__dirname, '../fixtures/NC_server_test.pdf'))
		await uploadFile(page.request, user, 'NC_server_test.pdf', 'application/pdf', pdfContent)

		await page.goto('/index.php/apps/tables')
		await createTable(page, 'Test text-link')

		await loadTable(page, 'Test text-link')

		await createTextLinkColumn(page, 'Test plain url', ['Url'], true)
		await createTextLinkColumn(page, 'Test files', ['Files'], false)

		await page.getByRole('button', { name: 'Create row' }).click()
		await page.locator('[data-cy="createRowModal"]').waitFor({ state: 'visible' })
		await page.locator('[data-cy="createRowModal"] .slot input').first().fill('https://nextcloud.com')

		const filesResultsReqPromise = page.waitForResponse(r => r.url().includes('/search/providers/files/') && r.request().method() === 'GET')
		await page.locator('[data-cy="createRowModal"] .slot input').nth(1).pressSequentially('pdf')
		await filesResultsReqPromise

		await page.locator('[data-cy*="NC_server_test"]').click()

		await page.locator('[data-cy="createRowSaveButton"]').click()

		await expect(page.locator('tr td a').filter({ hasText: 'nextcloud' }).first()).toBeVisible()
		await expect(page.locator('tr td a').filter({ hasText: 'NC_server_test' }).first()).toBeVisible()

		const saveEditRow = async () => {
			const editRowReqPromise = page.waitForResponse(r => r.url().includes('/apps/tables/row/') && r.request().method() === 'PUT')
			await page.locator('[data-cy="editRowSaveButton"]').click()
			const editRowResponse = await editRowReqPromise
			expect(editRowResponse.ok()).toBeTruthy()
			await expect(page.locator('[data-cy="editRowModal"]')).toBeHidden()
		}

		const firstRow = page.locator('[data-cy="ncTable"] [data-cy="customTableRow"]').first()
		await openRowActionMenu(page, firstRow)
		await page.locator('[data-cy="editRowBtn"]').click()
		let editDialog = page.getByRole('dialog', { name: 'Edit row' })
		await editDialog.waitFor({ state: 'visible' })

		const urlInput = editDialog
			.locator('.row.space-T', { hasText: 'Test plain url' })
			.locator('input[placeholder="URL"]')
			.first()
		await urlInput.fill('https://github.com')
		await expect(urlInput).toHaveValue('https://github.com')

		await saveEditRow()
		await expect(page.locator('tr td a').filter({ hasText: 'github' }).first()).toBeVisible()

		const editedRow = page.locator('[data-cy="ncTable"] [data-cy="customTableRow"]').first()
		await openRowActionMenu(page, editedRow)
		await page.locator('[data-cy="editRowBtn"]').click()
		editDialog = page.getByRole('dialog', { name: 'Edit row' })
		await editDialog.waitFor({ state: 'visible' })

		const editFilesResultsReqPromise = page.waitForResponse(r => r.url().includes('/search/providers/files/') && r.request().method() === 'GET')
		const fileCombobox = editDialog.getByRole('combobox', { name: 'Link providers' })
		await fileCombobox.click()
		await fileCombobox.pressSequentially('photo-test')
		await editFilesResultsReqPromise
		await page.getByRole('option', { name: /photo-test/i }).first().click()

		await saveEditRow()

		await expect(page.locator('tr td a').filter({ hasText: 'github' }).first()).toBeVisible()
		await expect(page.locator('tr td a').filter({ hasText: 'photo' }).first()).toBeVisible()
	})
})
