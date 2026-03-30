/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test, expect } from '../support/fixtures'
import { createNumberStarsColumn, createTable, loadTable } from '../support/commands'

const columnTitle = 'stars'
const tableTitle = 'Test number stars column'

test.describe('Test column number stars', () => {

	test('Manage stars column', async ({ userPage: { page } }) => {
		await page.goto('/index.php/apps/tables')
		await createTable(page, tableTitle)
		await loadTable(page, tableTitle)

		await createNumberStarsColumn(page, columnTitle, 3, true)

		await page.locator('button').filter({ hasText: 'Create row' }).click()

		// check if default value is set on row creation
		await expect(page.locator('.modal-container__content h2').filter({ hasText: 'Create row' }).first()).toBeVisible()
		await expect(page.locator('.modal__content .title').filter({ hasText: columnTitle }).first()).toBeVisible()

		await page.locator('button').filter({ hasText: 'Save' }).click()

		await expect(page.locator('.custom-table table tr td .interactive-stars').first()).toBeVisible()
		// Check that the rating matches what we expect
		await expect(page.locator('.custom-table table tr td .interactive-stars .star').nth(2)).toHaveClass(/filled/)
		await expect(page.locator('.custom-table table tr td .interactive-stars .star').nth(3)).not.toHaveClass(/filled/)
	})
})
