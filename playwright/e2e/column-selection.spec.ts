/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test, expect } from '../support/fixtures'
import { createSelectionColumn, createTable, deleteTable, loadTable } from '../support/commands'

const columnTitle = 'single selection'
const tableTitle = 'Test number column'

test.describe('Test column ' + columnTitle, () => {
	test.setTimeout(60000)

	test('Insert and test rows', async ({ userPage: { page } }) => {
		await page.goto('/index.php/apps/tables')
		await createTable(page, tableTitle + ' 1')
		await loadTable(page, tableTitle + ' 1')
		await createSelectionColumn(page, columnTitle, ['first option', 'second option', '👋 third option', '🤷🏻 fifths'], 'second option', true)

		// check if default value is set on row creation
		await page.locator('[data-cy="createRowBtn"]').click()
		await expect(page.locator('[data-cy="createRowModal"]')).toBeVisible()
		await expect(page.locator('[data-cy="createRowModal"] .title').first()).toBeVisible()
		await page.locator('[data-cy="createRowModal"] .title').first().click()
		await expect(page.locator('.vs__dropdown-toggle .vs__selected span[title="second option"]')).toBeVisible()
		await page.locator('[data-cy="createRowSaveButton"]').click()

		await expect(page.locator('[data-cy="ncTable"] tr td div').filter({ hasText: 'second option' }).first()).toBeVisible()

		// create a row and select non default value
		await page.locator('button').filter({ hasText: 'Create row' }).click()
		await page.locator('[data-cy="createRowModal"] .slot input').first().click()
		await page.locator('ul.vs__dropdown-menu li span[title="👋 third option"]').first().click()
		await page.locator('[data-cy="createRowSaveButton"]').click()

		await expect(page.locator('[data-cy="ncTable"] tr td div').filter({ hasText: 'third option' }).first()).toBeVisible()

		// edit the explicitly created row
		await page.locator('[data-cy="ncTable"] [data-cy="customTableRow"]:has-text("👋 third option")').locator('[data-cy="editRowBtn"]').click()
		await page.locator('[data-cy="editRowModal"] .slot input').first().click()
		await page.locator('ul.vs__dropdown-menu li span[title="first option"]').first().click()
		await page.locator('[data-cy="editRowSaveButton"]').click()

		await expect(page.locator('[data-cy="ncTable"] tr td div').filter({ hasText: 'first option' }).first()).toBeVisible()

		await deleteTable(page, tableTitle + ' 1')
	})

	test('Test empty selection', async ({ userPage: { page } }) => {
		await page.goto('/index.php/apps/tables')
		await createTable(page, tableTitle + ' 2')
		await loadTable(page, tableTitle + ' 2')
		await createSelectionColumn(page, columnTitle, ['first option', 'second option', '👋 third option', '🤷🏻 fifths'], '', true)

		// check if default value is set on row creation
		await page.locator('[data-cy="createRowBtn"]').click()
		await expect(page.locator('[data-cy="createRowModal"]')).toBeVisible()
		await page.locator('[data-cy="createRowSaveButton"]').click()

		await expect(page.locator('[data-cy="ncTable"] tr td div').first()).toBeVisible()
		await expect(page.locator('[data-cy="ncTable"] [data-cy="editRowBtn"]').first()).toBeVisible()
	})
})
