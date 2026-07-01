/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test, expect } from '../support/fixtures'
import { createTable, createTextLineColumn, createView, ensureNavigationOpen, loadTable } from '../support/commands'

test.describe('Row counter freshness', () => {

	test('Navigation counters update for table and sibling view after adding a row', async ({ userPage: { page } }) => {
		await page.goto('/index.php/apps/tables')
		await createTable(page, 'Counter sync table')
		await loadTable(page, 'Counter sync table')
		await createTextLineColumn(page, 'label', '', '', true)

		await createView(page, 'Counter sync view')
		await loadTable(page, 'Counter sync table')
		await ensureNavigationOpen(page)

		const tableCounter = page
			.locator('[data-cy="navigationTableItem"]')
			.filter({ hasText: 'Counter sync table' })
			.locator('.counter-bubble__counter')
			.first()
		const viewCounter = page
			.locator('[data-cy="navigationViewItem"]')
			.filter({ hasText: 'Counter sync view' })
			.locator('.counter-bubble__counter')
			.first()

		await page.locator('button').filter({ hasText: 'Create row' }).click()
		const input = page.locator('.modal__content input').first()
		await input.fill('first row')
		await page.locator('button').filter({ hasText: 'Save' }).click()
		await expect(page.locator('.custom-table table tr td div').filter({ hasText: 'first row' }).first()).toBeVisible()

		await expect(tableCounter).toHaveText('1')
		await expect(viewCounter).toHaveText('1')
	})
})
