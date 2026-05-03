/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test, expect } from '../support/fixtures'
import { createSelectionMultiColumn, createTable, loadTable, openRowActionMenu, removeColumn } from '../support/commands'

const columnTitle = 'multi selection'
const tableTitle = 'Test number column'

test.describe('Test column ' + columnTitle, () => {

	test('Insert and test rows', async ({ userPage: { page } }) => {
		await page.goto('/index.php/apps/tables')
		await createTable(page, tableTitle)
		await loadTable(page, tableTitle)
		await createSelectionMultiColumn(page, columnTitle, ['first option', 'second option', '👋 third option', '🤷🏻 fifths'], ['second option', 'first option'], true)

		// check if default value is set on row creation
		await page.locator('button').filter({ hasText: 'Create row' }).click()
		await expect(page.locator('.modal-container__content h2').filter({ hasText: 'Create row' }).first()).toBeVisible()
		await expect(page.locator('.modal__content .title').filter({ hasText: columnTitle }).first()).toBeVisible()
		await expect(page.locator('.modal__content span[title="first option"]').first()).toBeVisible()
		await expect(page.locator('.modal__content span[title="second option"]').first()).toBeVisible()
		await page.locator('.modal__content .title').first().click() // focus out of the multiselect
		await page.locator('button').filter({ hasText: 'Save' }).click()

		await expect(page.locator('.custom-table table tr td .cell-multi-selection').filter({ hasText: 'first option' }).first()).toBeVisible()
		await expect(page.locator('.custom-table table tr td .cell-multi-selection').filter({ hasText: 'second option' }).first()).toBeVisible()

		// create a row and select non default value
		await page.locator('button').filter({ hasText: 'Create row' }).click()
		await page.locator('.vs--multiple .vs__selected button').first().click()
		await page.locator('.vs--multiple .vs__selected button').first().click() // removing default entries

		await page.locator('.modal__content .slot input').first().click()
		await page.locator('ul.vs__dropdown-menu li span[title="👋 third option"]').first().click()
		await page.locator('.modal__content .title').first().click() // focus out
		await page.locator('button').filter({ hasText: 'Save' }).click()

		await expect(page.locator('.custom-table table tr td .cell-multi-selection').filter({ hasText: 'third option' }).first()).toBeVisible()

		// delete first row
		await openRowActionMenu(page, page.locator('[data-cy="customTableRow"]').first())
		await page.locator('[data-cy="deleteRowBtn"]').click()
		await page.locator('[data-cy="confirmDialog"]').getByRole('button', { name: 'Confirm' }).click()
		await expect(page.locator('[data-cy="customTableRow"]')).toHaveCount(1, { timeout: 10000 })

		await expect(page.locator('.custom-table table tr td .cell-multi-selection', { hasText: 'first option' })).toBeHidden()
		await expect(page.locator('.custom-table table tr td .cell-multi-selection', { hasText: 'second option' })).toBeHidden()

		// edit second row (which is now first row)
		await openRowActionMenu(page, page.locator('[data-cy="customTableRow"]').first())
		await page.locator('[data-cy="editRowBtn"]').click()
		await page.locator('.modal__content .slot input').first().click()
		await page.locator('ul.vs__dropdown-menu li span[title="first option"]').first().click()
		await page.locator('.modal__content .title').first().click()
		await page.locator('button').filter({ hasText: 'Save' }).click()

		await expect(page.locator('.custom-table table tr td .cell-multi-selection').filter({ hasText: 'first option' }).first()).toBeVisible()
		await expect(page.locator('.custom-table table tr td .cell-multi-selection').filter({ hasText: 'third option' }).first()).toBeVisible()

		// delete first row
		await openRowActionMenu(page, page.locator('[data-cy="customTableRow"]').first())
		await page.locator('[data-cy="deleteRowBtn"]').click()
		await page.locator('[data-cy="confirmDialog"]').getByRole('button', { name: 'Confirm' }).click()
		await expect(page.locator('[data-cy="customTableRow"]')).toHaveCount(0, { timeout: 10000 })

		await removeColumn(page, columnTitle)
	})

	test('Test empty selection', async ({ userPage: { page } }) => {
		await page.goto('/index.php/apps/tables')
		await createTable(page, tableTitle + ' second')
		await loadTable(page, tableTitle + ' second')
		await createSelectionMultiColumn(page, columnTitle, ['first option', 'second option', '👋 third option', '🤷🏻 fifths'], [], true)

		// check if default value is set on row creation
		await page.locator('button').filter({ hasText: 'Create row' }).click()
		await expect(page.locator('.modal-container__content h2').filter({ hasText: 'Create row' }).first()).toBeVisible()
		await page.locator('button').filter({ hasText: 'Save' }).click()

		await expect(page.locator('.custom-table table tr td .cell-multi-selection').first()).toBeVisible()
		await expect(page.locator('[data-cy="customTableRow"]').first()).toBeVisible()
	})
})
