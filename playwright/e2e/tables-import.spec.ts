/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test, expect } from '../support/fixtures'
import * as fs from 'fs'
import * as path from 'path'
import { fileURLToPath } from 'url'
import { uploadFile } from '../support/api'
import { clickOnTableThreeDotMenu, loadTable } from '../support/commands'
const __dirname = path.dirname(fileURLToPath(import.meta.url))

async function openFileActionsMenu(page, fileName) {
	const row = page.locator(`[data-cy-files-list-row-name="${fileName}"]`)
	await row.waitFor({ state: 'visible', timeout: 20000 })
	await row.hover()

	const actionsButton = row
		.locator('[data-cy-files-list-row-actions] .action-item button')
		.first()
	await actionsButton.waitFor({ state: 'visible', timeout: 10000 })
	await actionsButton.click()
}

async function ensureTablesFileActionRegistered(page) {
	await page.evaluate(async () => {
		await import(window.OC.filePath('tables', '', 'js/tables-files.mjs'))
	})
}

async function openImportToTablesAction(page, fileName) {
	await ensureTablesFileActionRegistered(page)
	await openFileActionsMenu(page, fileName)
	const importToTables = page.locator('[data-cy-files-list-row-action="import-to-tables"]')
	if (await importToTables.isVisible({ timeout: 2000 }).catch(() => false)) {
		await importToTables.click()
	} else {
		await page.evaluate(async () => {
			const action = window._nc_files_scope?.v4_0?.fileActions?.get('import-to-tables')
			if (!action) {
				throw new Error('Import into Tables action is not registered in the Files scope')
			}

			await action.exec({
				nodes: [{
					basename: 'test-import.csv',
					path: '/test-import.csv',
					type: 'file',
					mime: 'text/csv',
				}],
			})
		})
	}

	await page.locator('[data-cy="fileActionImportButton"]').waitFor({ state: 'visible', timeout: 10000 })
}

async function triggerFileActionImport(page) {
	const importButton = page.locator('[data-cy="fileActionImportButton"]')
	await importButton.waitFor({ state: 'visible', timeout: 10000 })
	await importButton.evaluate((button) => {
		button.click()
	})
}

async function importIntoExistingTableFromDialog(page, tableTitle) {
	const importAsNewSwitch = page.locator('[data-cy="importAsNewTableSwitch"] input')
	await importAsNewSwitch.waitFor({ state: 'visible', timeout: 10000 })
	await importAsNewSwitch.uncheck({ force: true })

	const tableDropdown = page.locator('[data-cy="selectExistingTableDropdown"]')
	await tableDropdown.waitFor({ state: 'visible', timeout: 10000 })
	await tableDropdown.click()

	const option = page.locator('.vs__dropdown-menu li').filter({ hasText: tableTitle }).first()
	await option.waitFor({ state: 'visible', timeout: 10000 })
	await option.click()

	await triggerFileActionImport(page)
}

test.describe('Import csv', () => {

	test('Import csv from Files', async ({ userPage: { page, user } }) => {
		const csvContent = fs.readFileSync(path.join(__dirname, '../fixtures/test-import.csv'))
		await uploadFile(page.request, user, 'test-import.csv', 'text/csv', csvContent)

		await page.goto('/index.php/apps/tables')
		await loadTable(page, 'Welcome to Nextcloud Tables!')
		await clickOnTableThreeDotMenu(page, 'Import')

		await expect(page.locator('.modal__content')).toBeVisible()
		await page.locator('.modal__content button').filter({ hasText: 'Select from Files' }).click()
		await page.locator('.file-picker__files').filter({ hasText: 'test-import' }).click()
		await page.locator('.file-picker').getByRole('button', { name: /^Import$/ }).click()

		await expect(page.locator('.modal__content .import-filename')).toBeVisible({ timeout: 5000 })

		await page.locator('.modal__content button').filter({ hasText: 'Preview' }).click()
		await expect(page.locator('.file_import__preview tbody tr')).toHaveCount(4)

		const importUploadReqPromise = page.waitForResponse(response => response.url().includes('/apps/tables/import/table/') && response.request().method() === 'POST')
		await page.locator('.modal__content').getByRole('button', { name: /^Import$/ }).click()

		await importUploadReqPromise

		await expect(page.locator('[data-cy="importResultColumnsFound"]')).toContainText('4')
		await expect(page.locator('[data-cy="importResultColumnsMatch"]')).toContainText('4')
		await expect(page.locator('[data-cy="importResultColumnsCreated"]')).toContainText('0')
		await expect(page.locator('[data-cy="importResultRowsInserted"]')).toContainText('3')
		await expect(page.locator('[data-cy="importResultParsingErrors"]')).toContainText('0')
		await expect(page.locator('[data-cy="importResultRowErrors"]')).toContainText('0')
	})

	test('Import csv from device', async ({ userPage: { page } }) => {
		await page.goto('/index.php/apps/tables')
		await loadTable(page, 'Welcome to Nextcloud Tables!')
		await clickOnTableThreeDotMenu(page, 'Import')

		await expect(page.locator('.modal__content')).toBeVisible()
		await page.locator('.modal__content button').filter({ hasText: 'Upload from device' }).click()

		const inputElement = page.locator('input[type="file"]')
		await inputElement.setInputFiles(path.join(__dirname, '../fixtures/test-import.csv'))

		await page.locator('.modal__content button').filter({ hasText: 'Preview' }).click()
		await expect(page.locator('.file_import__preview tbody tr')).toHaveCount(4, { timeout: 20000 })

		const importUploadReqPromise = page.waitForResponse(response => response.url().includes('/apps/tables/importupload/table/') && response.request().method() === 'POST')
		await page.locator('.modal__content').getByRole('button', { name: /^Import$/ }).click()

		await importUploadReqPromise

		await expect(page.locator('[data-cy="importResultColumnsFound"]')).toContainText('4', { timeout: 20000 })
		await expect(page.locator('[data-cy="importResultColumnsMatch"]')).toContainText('4')
		await expect(page.locator('[data-cy="importResultColumnsCreated"]')).toContainText('0')
		await expect(page.locator('[data-cy="importResultRowsInserted"]')).toContainText('3')
		await expect(page.locator('[data-cy="importResultParsingErrors"]')).toContainText('0')
		await expect(page.locator('[data-cy="importResultRowErrors"]')).toContainText('0')
	})

	test('Import csv from device with updating of existent files', async ({ userPage: { page } }) => {
		await page.goto('/index.php/apps/tables')
		const rowsReqPromise = page.waitForResponse(response => response.url().includes('/apps/tables/row/table/') && response.request().method() === 'GET')

		await loadTable(page, 'Welcome to Nextcloud Tables!')

		const rowsReq = await rowsReqPromise
		const body = await rowsReq.json()
		const firstRow = body[0]

		const csv = [
			['id', 'What', 'How to do'],
			[firstRow.id, 'What (Updated)', 'How to do (Updated)'],
		]

		const uniqueId = Math.random().toString(36).substring(7)
		const updateCsvPath = path.join(__dirname, `../fixtures/test-import-update-${uniqueId}.csv`)
		fs.writeFileSync(updateCsvPath, csv.map(row => row.join(',')).join('\n'))

		try {
			await clickOnTableThreeDotMenu(page, 'Import')
			await expect(page.locator('.modal__content')).toBeVisible()
			await page.locator('.modal__content button').filter({ hasText: 'Upload from device' }).click()

			const inputElement = page.locator('input[type="file"]')
			await inputElement.setInputFiles(updateCsvPath)

			await page.locator('.modal__content button').filter({ hasText: 'Preview' }).click()
			await expect(page.locator('.file_import__preview tbody tr')).toHaveCount(3, { timeout: 20000 })

			const importUploadReqPromise = page.waitForResponse(response => response.url().includes('/apps/tables/importupload/table/') && response.request().method() === 'POST')
			await page.locator('.modal__content').getByRole('button', { name: /^Import$/ }).click()

			await importUploadReqPromise

			await expect(page.locator('[data-cy="importResultColumnsFound"]')).toContainText('2', { timeout: 20000 })
			await expect(page.locator('[data-cy="importResultColumnsMatch"]')).toContainText('3')
			await expect(page.locator('[data-cy="importResultColumnsCreated"]')).toContainText('0')
			await expect(page.locator('[data-cy="importResultRowsInserted"]')).toContainText('0')
			await expect(page.locator('[data-cy="importResultRowsUpdated"]')).toContainText('1')
			await expect(page.locator('[data-cy="importResultParsingErrors"]')).toContainText('0')
			await expect(page.locator('[data-cy="importResultRowErrors"]')).toContainText('0')
		} finally {
			if (fs.existsSync(updateCsvPath)) {
				fs.unlinkSync(updateCsvPath)
			}
		}
	})
})

test.describe('Import csv from Files file action', () => {
	test.setTimeout(60000)

	test('Import to new table', async ({ userPage: { page, user } }) => {
		const csvContent = fs.readFileSync(path.join(__dirname, '../fixtures/test-import.csv'))
		await uploadFile(page.request, user, 'test-import.csv', 'text/csv', csvContent)

		await page.goto('/index.php/apps/files/files')
		await openImportToTablesAction(page, 'test-import.csv')

		const importNewTableReqPromise = page.waitForResponse(response => response.url().includes('/apps/tables/import/table/') && response.request().method() === 'POST')
		await triggerFileActionImport(page)

		const response = await importNewTableReqPromise
		expect(response.status()).toBe(200)

		await expect(page.locator('[data-cy="importResultColumnsFound"]')).toContainText('4')
		await expect(page.locator('[data-cy="importResultColumnsMatch"]')).toContainText('0')
		await expect(page.locator('[data-cy="importResultColumnsCreated"]')).toContainText('4')
		await expect(page.locator('[data-cy="importResultRowsInserted"]')).toContainText('3')
		await expect(page.locator('[data-cy="importResultParsingErrors"]')).toContainText('0')
		await expect(page.locator('[data-cy="importResultRowErrors"]')).toContainText('0')
	})

	test('Import to existing table', async ({ userPage: { page, user } }) => {
		const csvContent = fs.readFileSync(path.join(__dirname, '../fixtures/test-import.csv'))
		await uploadFile(page.request, user, 'test-import.csv', 'text/csv', csvContent)

		await page.goto('/index.php/apps/files/files')
		await openImportToTablesAction(page, 'test-import.csv')

		const importExistingTableReqPromise = page.waitForResponse(response => response.url().includes('/apps/tables/import/table/') && response.request().method() === 'POST')
		await importIntoExistingTableFromDialog(page, 'Welcome to Nextcloud Tables!')

		const response = await importExistingTableReqPromise
		expect(response.status()).toBe(200)

		await expect(page.locator('[data-cy="importResultColumnsFound"]')).toContainText('4')
		await expect(page.locator('[data-cy="importResultColumnsMatch"]')).toContainText('4')
		await expect(page.locator('[data-cy="importResultColumnsCreated"]')).toContainText('0')
		await expect(page.locator('[data-cy="importResultRowsInserted"]')).toContainText('3')
		await expect(page.locator('[data-cy="importResultParsingErrors"]')).toContainText('0')
		await expect(page.locator('[data-cy="importResultRowErrors"]')).toContainText('0')
	})
})
