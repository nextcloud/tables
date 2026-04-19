/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test, expect } from '../support/fixtures'
import { createNumberColumn, createTable, loadTable, openRowActionMenu, removeColumn } from '../support/commands'

const columnTitle = 'num1'
const tableTitle = 'Test number column'
const unsetNumberValue = null

test.describe('Test column number', () => {

	test('Insert and test rows - default values', async ({ userPage: { page } }) => {
		await page.goto('/index.php/apps/tables')
		await createTable(page, tableTitle)
		await loadTable(page, tableTitle)
		await createNumberColumn(page, columnTitle, '', unsetNumberValue, unsetNumberValue, unsetNumberValue, '', '', true)

		// insert row with int value
		await page.locator('button').filter({ hasText: 'Create row' }).click()
		await page.locator('.modal__content input').first().clear()
		await page.locator('.modal__content input').first().fill('21')
		await page.locator('button').filter({ hasText: 'Save' }).click()
		await expect(page.locator('.custom-table table tr td div').filter({ hasText: '21.00' }).first()).toBeVisible()

		// delete row
		await openRowActionMenu(page, page.locator('[data-cy="customTableRow"]').first())
		await page.locator('[data-cy="deleteRowBtn"]').click()
		await page.locator('[data-cy="confirmDialog"]').getByRole('button', { name: 'Confirm' }).click()
		await expect(page.locator('[data-cy="customTableRow"]')).toHaveCount(0, { timeout: 10000 })

		// insert row with float value
		await page.locator('button').filter({ hasText: 'Create row' }).click()
		await page.locator('.modal__content input').first().clear()
		await page.locator('.modal__content input').first().fill('21.305')
		await page.locator('button').filter({ hasText: 'Save' }).click()
		await expect(page.locator('.custom-table table tr td div').filter({ hasText: '21.30' }).first()).toBeVisible()

		// delete row
		await openRowActionMenu(page, page.locator('[data-cy="customTableRow"]').first())
		await page.locator('[data-cy="deleteRowBtn"]').click()
		await page.locator('[data-cy="confirmDialog"]').getByRole('button', { name: 'Confirm' }).click()
		await expect(page.locator('[data-cy="customTableRow"]')).toHaveCount(0, { timeout: 10000 })

		await removeColumn(page, columnTitle)
	})

	test('Insert and test rows - individual column settings', async ({ userPage: { page } }) => {
		await page.goto('/index.php/apps/tables')
		await createTable(page, tableTitle + ' second')
		await loadTable(page, tableTitle + ' second')
		await createNumberColumn(page, columnTitle, '3.5', 1, 2, 20, 'PRE', 'SUF', true)

		// insert row with default values
		await page.locator('button').filter({ hasText: 'Create row' }).click()
		await expect(page.locator('.modal__content input').first()).toHaveValue(/3.5/)
		await page.locator('button').filter({ hasText: 'Save' }).click()
		await expect(page.locator('.custom-table table tr td div').filter({ hasText: 'PRE3.5SUF' }).first()).toBeVisible()

		// insert row with too high number
		await page.locator('button').filter({ hasText: 'Create row' }).click()
		await page.locator('.modal__content input').first().clear()
		await page.locator('.modal__content input').first().fill('100')
		await page.locator('button').filter({ hasText: 'Save' }).click()
		await expect(page.locator('.custom-table table tr td div').filter({ hasText: 'PRE20.0SUF' }).first()).toBeVisible()

		// insert row with too low number
		await page.locator('button').filter({ hasText: 'Create row' }).click()
		await page.locator('.modal__content input').first().clear()
		await page.locator('.modal__content input').first().fill('-1')
		await page.locator('button').filter({ hasText: 'Save' }).click()
		await expect(page.locator('.custom-table table tr td div').filter({ hasText: 'PRE2.0SUF' }).first()).toBeVisible()
	})
})
