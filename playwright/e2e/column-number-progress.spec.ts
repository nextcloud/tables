/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test, expect } from '../support/fixtures'
import { createNumberProgressColumn, createTable, loadTable } from '../support/commands'

const columnTitle = 'progress'
const tableTitle = 'Test number progress column'

test.describe('Test column number progress', () => {

	test('Manage progress column', async ({ userPage: { page } }) => {
		await page.goto('/index.php/apps/tables')
		await createTable(page, tableTitle)
		await loadTable(page, tableTitle)

		await createNumberProgressColumn(page, columnTitle, '80', true)

		await page.locator('button').filter({ hasText: 'Create row' }).click()

		// check if default value is set on row creation
		await expect(page.locator('.modal-container__content h2').filter({ hasText: 'Create row' }).first()).toBeVisible()
		await expect(page.locator('.modal__content .title').filter({ hasText: columnTitle }).first()).toBeVisible()
		await expect(page.locator('.modal__content input').first()).toHaveValue('80')

		await page.locator('button').filter({ hasText: 'Save' }).click()

		await expect(page.getByRole('progressbar').first()).toBeVisible()
		await expect(page.getByRole('cell', { name: '80' }).first()).toBeVisible()
	})
})
