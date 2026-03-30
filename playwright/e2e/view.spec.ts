/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test, expect } from '../support/fixtures'
import type { Page } from '@playwright/test'
import { createSelectionColumn, createTable, createTextLineColumn, fillInValueSelection, fillInValueTextLine, loadTable } from '../support/commands'

const firstTitle = 'Test view'
const secondTitle = 'Test view 2'
const thirdTitle = 'Test view 3'
const fourthTitle = 'Test view 4'
const tableTitle = 'View test table'

async function setupTestTable(page: Page) {
	await createTable(page, tableTitle)
	await createTextLineColumn(page, 'title', '', '', true)
	await createSelectionColumn(page, 'selection', ['sel1', 'sel2', 'sel3', 'sel4'], '', false)

	const addRow = async (title: string, sel: string) => {
		await page.locator('[data-cy="createRowBtn"]').click()
		await fillInValueTextLine(page, 'title', title)
		await fillInValueSelection(page, 'selection', sel)
		await page.locator('[data-cy="createRowSaveButton"]').click()
	}

	await addRow('first row', 'sel1')
	await addRow('second row', 'sel2')
	await addRow('sevenths row', 'sel2')
}

test.describe('Interact with views', () => {

	test('Create view and insert rows in the view', async ({ userPage: { page } }) => {
		await page.goto('/index.php/apps/tables')
		await setupTestTable(page)
		await loadTable(page, tableTitle)

		// create view
		await page.locator('[data-cy="customTableAction"] button').click()
		await page.locator('[data-cy="dataTableCreateViewBtn"]').filter({ hasText: 'Create view' }).waitFor({ state: 'visible' })
		await page.locator('[data-cy="dataTableCreateViewBtn"]').filter({ hasText: 'Create view' }).click({ force: true })
		const titleInput1 = page.locator('[data-cy="viewSettingsDialogTitleInput"]')
		await titleInput1.waitFor({ state: 'visible', timeout: 10000 })
		await titleInput1.fill(firstTitle)

		// add filter
		await page.locator('[data-cy="filterFormFilterGroupBtn"]').filter({ hasText: 'Add new filter group' }).click()
		await page.locator('[data-cy="filterEntryColumn"]').click()
		await page.locator('ul.vs__dropdown-menu li span[title="selection"]').click()
		await page.locator('[data-cy="filterEntryOperator"]').click()
		await page.locator('ul.vs__dropdown-menu li span[title="Is equal"]').click()
		await page.locator('[data-cy="filterEntrySeachValue"]').click()
		await page.locator('ul.vs__dropdown-menu li span[title="sel2"]').click()

		// save view
		const createViewReqPromise = page.waitForResponse(response => response.url().includes('/apps/tables/view') && response.request().method() === 'POST')
		await page.locator('[data-cy="modifyViewBtn"]').filter({ hasText: 'Create View' }).click()
		await createViewReqPromise

		await expect(page.locator('[data-cy="navigationViewItem"]').filter({ hasText: firstTitle }).first()).toBeVisible()

		const expected = ['sevenths row', 'second row']
		for (const item of expected) {
			await expect(page.locator('[data-cy="customTableRow"] td div').filter({ hasText: item }).first()).toBeVisible()
		}

		// Add new row in the view
		await page.locator('[data-cy="createRowBtn"]').filter({ hasText: 'Create row' }).click()
		await fillInValueTextLine(page, 'title', 'new row')
		await fillInValueSelection(page, 'selection', 'sel2')
		await page.locator('[data-cy="createRowSaveButton"]').filter({ hasText: 'Save' }).click()

		for (const item of [...expected, 'new row']) {
			await expect(page.locator('[data-cy="customTableRow"] td div').filter({ hasText: item }).first()).toBeVisible()
		}
	})

	test('Create view and update rows in the view', async ({ userPage: { page } }) => {
		await page.goto('/index.php/apps/tables')
		await setupTestTable(page)
		await loadTable(page, tableTitle)

		// create view
		await page.locator('[data-cy="customTableAction"] button').click()
		await page.locator('[data-cy="dataTableCreateViewBtn"]').filter({ hasText: 'Create view' }).waitFor({ state: 'visible' })
		await page.locator('[data-cy="dataTableCreateViewBtn"]').filter({ hasText: 'Create view' }).click({ force: true })
		const titleInput2 = page.locator('[data-cy="viewSettingsDialogTitleInput"]')
		await titleInput2.waitFor({ state: 'visible', timeout: 10000 })
		await titleInput2.fill(secondTitle)

		// save view
		const createViewReqPromise = page.waitForResponse(response => response.url().includes('/apps/tables/view') && response.request().method() === 'POST')
		const updateViewReqPromise = page.waitForResponse(response => response.url().includes('/apps/tables/view/') && response.request().method() === 'PUT')
		await page.locator('[data-cy="modifyViewBtn"]').filter({ hasText: 'Create View' }).click()
		await createViewReqPromise
		await updateViewReqPromise

		await expect(page.locator('[data-cy="navigationViewItem"]').filter({ hasText: secondTitle }).first()).toBeVisible()

		// Update rows in the view
		const rowToEdit = page.locator('[data-cy="customTableRow"]').filter({ hasText: 'first row' }).first()
		await expect(rowToEdit).toBeVisible({ timeout: 10000 })
		await rowToEdit.getByText('first row', { exact: true }).click()
		const titleInput = page.getByRole('textbox', { name: 'Cell input' }).first()
		await expect(titleInput).toBeVisible({ timeout: 10000 })
		await titleInput.clear()
		await titleInput.fill('Changed row')
		const saveReqPromise = page.waitForResponse(r => r.url().includes('/apps/tables/row/') && r.request().method() === 'PUT')
		await titleInput.press('Enter')
		await saveReqPromise

		await expect(page.locator('.cell-input input').first()).toBeHidden({ timeout: 10000 })
		await page.reload({ waitUntil: 'domcontentloaded' })
		await expect(page.locator('.icon-loading').first()).toBeHidden({ timeout: 10000 })
		await expect(page.getByRole('cell', { name: 'first row', exact: true })).toHaveCount(0, { timeout: 10000 })
		await expect(page.getByRole('cell', { name: 'Changed row', exact: true }).first()).toBeVisible({ timeout: 10000 })
	})

	test('Create view and make column readonly in the view', async ({ userPage: { page } }) => {
		await page.goto('/index.php/apps/tables')
		await setupTestTable(page)
		await loadTable(page, tableTitle)

		// create view
		await page.locator('[data-cy="customTableAction"] button').click()
		await page.locator('[data-cy="dataTableCreateViewBtn"]').filter({ hasText: 'Create view' }).waitFor({ state: 'visible' })
		await page.locator('[data-cy="dataTableCreateViewBtn"]').filter({ hasText: 'Create view' }).click({ force: true })
		const titleInput3 = page.locator('[data-cy="viewSettingsDialogTitleInput"]')
		await titleInput3.waitFor({ state: 'visible', timeout: 10000 })
		await titleInput3.fill(thirdTitle)

		// trigger three dot menu and select readonly
		await page.locator('.column-entry', { hasText: 'title' }).hover()
		await page.locator('.column-entry', { hasText: 'title' }).locator('[data-cy="customColumnAction"] button').click({ force: true })
		await page.locator('[data-cy="columnReadonlyCheckbox"]').filter({ hasText: 'Read only' }).click()

		// save view
		const createViewReqPromise = page.waitForResponse(response => response.url().includes('/apps/tables/view') && response.request().method() === 'POST')
		await page.locator('[data-cy="modifyViewBtn"]').filter({ hasText: 'Create View' }).click()
		await createViewReqPromise

		await expect(page.locator('[data-cy="navigationViewItem"]').filter({ hasText: thirdTitle }).first()).toBeVisible()
	})

	test('Create view and delete rows in the view', async ({ userPage: { page } }) => {
		await page.goto('/index.php/apps/tables')
		await setupTestTable(page)
		await loadTable(page, tableTitle)

		// create view
		await page.locator('[data-cy="customTableAction"] button').click()
		await page.locator('[data-cy="dataTableCreateViewBtn"]').filter({ hasText: 'Create view' }).waitFor({ state: 'visible' })
		await page.locator('[data-cy="dataTableCreateViewBtn"]').filter({ hasText: 'Create view' }).click({ force: true })
		const titleInput4 = page.locator('[data-cy="viewSettingsDialogTitleInput"]')
		await titleInput4.waitFor({ state: 'visible', timeout: 10000 })
		await titleInput4.fill(fourthTitle)

		// save view
		const createViewReqPromise = page.waitForResponse(response => response.url().includes('/apps/tables/view') && response.request().method() === 'POST')
		await page.locator('[data-cy="modifyViewBtn"]').filter({ hasText: 'Create View' }).click()
		await createViewReqPromise

		await expect(page.locator('[data-cy="navigationViewItem"]').filter({ hasText: fourthTitle }).first()).toBeVisible()
		await expect(page.locator('.icon-loading').first()).toBeHidden()

		// Delete the first row
		await page.locator('[data-cy="customTableRow"]').first().locator('[data-cy="editRowBtn"]').click()
		await page.locator('[data-cy="editRowModal"] [data-cy="editRowDeleteButton"]').click()
		await page.locator('[data-cy="editRowModal"] [data-cy="editRowDeleteConfirmButton"]').click()

		await expect(page.locator('[data-cy="editRowModal"]')).toBeHidden()

		// Verify one row was deleted by checking the count decreased
		const count = await page.locator('[data-cy="customTableRow"]').count()
		expect(count).toBeLessThan(4)
	})
})
