/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test, expect } from '../support/fixtures'
import type { Page } from '@playwright/test'
import {
	createContext,
	createTable,
	createTextLineColumn,
	createView,
	loadContext,
	loadTable,
	openCreateRowModal,
} from '../support/commands'

const tableName = 'fruit stock'
const viewOneTitle = 'apple view'
const viewTwoTitle = 'banana view'
const contextTitle = 'test application search scope'

// Builds a table with two rows, two views of it and an application containing both views.
// Every test runs with its own random user, so the fixture is independent per test.
async function createSearchFixture(page: Page) {
	await page.goto('/index.php/apps/tables')
	await createTable(page, tableName)
	await createTextLineColumn(page, 'Name', '', '', true)

	await openCreateRowModal(page)
	await page.locator('[data-cy="createRowModal"] .slot input').first().fill('apple row')
	await page.locator('[data-cy="createRowSaveButton"]').click()
	await expect(page.locator('[data-cy="createRowModal"]')).toBeHidden()

	await openCreateRowModal(page)
	await page.locator('[data-cy="createRowModal"] .slot input').first().fill('banana row')
	await page.locator('[data-cy="createRowSaveButton"]').click()
	await expect(page.locator('[data-cy="createRowModal"]')).toBeHidden()

	await createView(page, viewOneTitle)
	await loadTable(page, tableName)
	await createView(page, viewTwoTitle)

	await createContext(page, contextTitle, false, [viewOneTitle, viewTwoTitle])
}

// Returns the locator for the data row containing the given text inside a view's table.
function row(view: ReturnType<Page['locator']>, text: string) {
	return view.locator('tbody tr').filter({ hasText: text })
}

test.describe('Search inside an application', () => {
	test('Search in one view must not filter the other views', async ({
		userPage: { page },
	}) => {
		await createSearchFixture(page)
		await loadContext(page, contextTitle)

		const viewOne = page
			.locator('[data-cy="contextViewNode"]')
			.filter({ hasText: viewOneTitle })
		const viewTwo = page
			.locator('[data-cy="contextViewNode"]')
			.filter({ hasText: viewTwoTitle })
		await expect(viewOne).toHaveCount(1)
		await expect(viewTwo).toHaveCount(1)

		// type into the first view's search input only
		await viewOne
			.locator('[data-cy="elementSearchInput"] input')
			.fill('apple')

		// synchronize on the debounced filter applying before asserting states
		await expect(row(viewOne, 'banana row')).toHaveCount(0)

		// the searched view is filtered
		await expect(row(viewOne, 'apple row')).toBeVisible()

		// the other view keeps being uninterrupted
		await expect(row(viewTwo, 'apple row')).toBeVisible()
		await expect(row(viewTwo, 'banana row')).toBeVisible()

		// clearing the search un-filters the searched view
		await viewOne
			.locator('[data-cy="elementSearchInput"] input')
			.fill('')
		await expect(row(viewOne, 'banana row')).toBeVisible()
	})
})
