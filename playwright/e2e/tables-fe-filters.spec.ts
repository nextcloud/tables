/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test, expect } from '../support/fixtures'
import { createTable, createTextLineColumn, createView, ensureNavigationOpen, loadTable, loadView, sortTableColumn } from '../support/commands'

test.describe('FE sorting and filtering', () => {

	test('FE Search in table', async ({ userPage: { page } }) => {
		await page.goto('/index.php/apps/tables')
		await loadTable(page, 'Welcome to Nextcloud Tables!')

		// test case-sensitive
		await expect(page.locator('text=Edit a row').first()).toBeVisible()
		await page.locator('.searchAndFilter input').fill('tables')
		await expect(page.locator('text=Edit a row').first()).toBeHidden()
		await expect(page.locator('text=Read the docs').first()).toBeVisible()

		// test not case-sensitive
		await page.locator('.searchAndFilter input').clear()
		await page.locator('.searchAndFilter input').fill('TABLES')
		await expect(page.locator('text=Edit a row').first()).toBeHidden()
		await expect(page.locator('text=Read the docs').first()).toBeVisible()

		// test search for number regarding a check field
		await page.locator('.searchAndFilter input').clear()
		await page.locator('.searchAndFilter input').fill('3')
		await expect(page.locator('text=Edit a row').first()).toBeHidden()
		await expect(page.locator('text=Read the docs').first()).toBeVisible()
	})

	test('Reset FE filter on table or view change', async ({ userPage: { page } }) => {
		await page.goto('/index.php/apps/tables')
		// create a table and view, so we can change the active table and view later on
		await createTable(page, 'first table')
		await createTextLineColumn(page, 'colA', '', '', true)

		await createTable(page, 'second table')
		await createTextLineColumn(page, 'col1', '', '', true)

		// change between tables
		await loadTable(page, 'first table')
		await sortTableColumn(page, 'colA')
		await expect(page.locator('.info').filter({ hasText: 'Reset local adjustments' })).toBeVisible()
		await loadTable(page, 'second table')
		await expect(page.locator('.info').filter({ hasText: 'Reset local adjustments' })).toBeHidden()

		// change from view to table
		await createView(page, 'view for second table')
		await sortTableColumn(page, 'col1')
		await expect(page.locator('.info').filter({ hasText: 'Reset local adjustments' })).toBeVisible()
		await loadTable(page, 'second table')
		await expect(page.locator('.info').filter({ hasText: 'Reset local adjustments' })).toBeHidden()

		// change from table to view
		await loadTable(page, 'first table')
		await sortTableColumn(page, 'colA')
		await expect(page.locator('.info').filter({ hasText: 'Reset local adjustments' })).toBeVisible()
		await loadView(page, 'view for second table')
		await expect(page.locator('.info').filter({ hasText: 'Reset local adjustments' })).toBeHidden()
	})

	test('Navigation filtering', async ({ userPage: { page } }) => {
		await page.goto('/index.php/apps/tables')
		await page.setViewportSize({ width: 1440, height: 900 }) // macbook-15
		await createTable(page, 'first table navigation')
		await createTable(page, 'second table navigation')
		await createTable(page, 'third table 🙇 navigation')
		await createTextLineColumn(page, 'col1', '', '', true)
		await createView(page, 'view for third tab')

		// ensure navigation is open and visible
		await ensureNavigationOpen(page)

		// all tables and views should be visible
		await expect(page.locator('[data-cy="navigationTableItem"]').filter({ hasText: 'first table navigation' })).toBeVisible({ timeout: 10000 })
		await expect(page.locator('[data-cy="navigationTableItem"]').filter({ hasText: 'second table navigation' })).toBeVisible()
		await expect(page.locator('[data-cy="navigationTableItem"]').filter({ hasText: 'third table 🙇 navigation' })).toBeVisible()
		await expect(page.locator('[data-cy="navigationTableItem"]').filter({ hasText: 'view for third tab' })).toBeVisible()

		// only tables should be visible
		await page.locator('.filter-box input').clear()
		await page.locator('.filter-box input').fill('table navigation')
		await expect(page.locator('[data-cy="navigationTableItem"]').filter({ hasText: 'first table navigation' })).toBeVisible()
		await expect(page.locator('[data-cy="navigationTableItem"]').filter({ hasText: 'second table navigation' })).toBeVisible()
		await expect(page.locator('[data-cy="navigationTableItem"]').filter({ hasText: 'third table 🙇 navigation' })).toBeHidden()
		await expect(page.locator('[data-cy="navigationTableItem"]').filter({ hasText: 'view for third tab' })).toBeHidden()

		// only the second table should be visible
		await page.locator('.filter-box input').clear()
		await page.locator('.filter-box input').fill('second table navigation')
		await expect(page.locator('[data-cy="navigationTableItem"]').filter({ hasText: 'first table navigation' })).toBeHidden()
		await expect(page.locator('[data-cy="navigationTableItem"]').filter({ hasText: 'second table navigation' })).toBeVisible()
		await expect(page.locator('[data-cy="navigationTableItem"]').filter({ hasText: 'third table 🙇 navigation' })).toBeHidden()
		await expect(page.locator('[data-cy="navigationTableItem"]').filter({ hasText: 'view for third tab' })).toBeHidden()

		// only the third table and it's view should be visible
		await page.locator('.filter-box input').clear()
		await page.locator('.filter-box input').fill('view for third')
		await expect(page.locator('[data-cy="navigationTableItem"]').filter({ hasText: 'first table navigation' })).toBeHidden()
		await expect(page.locator('[data-cy="navigationTableItem"]').filter({ hasText: 'second table navigation' })).toBeHidden()
		await expect(page.locator('[data-cy="navigationTableItem"]').filter({ hasText: 'third table 🙇 navigation' })).toBeVisible()
		await expect(page.locator('[data-cy="navigationTableItem"]').filter({ hasText: 'view for third tab' })).toBeVisible()
	})
})
