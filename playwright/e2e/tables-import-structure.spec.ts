/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Page } from '@playwright/test'
import { test, expect } from '../support/fixtures'
import { ensureNavigationOpen } from '../support/commands'

interface StructureScheme {
	title: string
	columns: Array<{ id: number; title: string; type: string }>
	views: Array<{ title: string; filter?: unknown[][]; sort?: unknown[]; columns?: number[]; columnSettings?: unknown[] }>
	tablesVersion?: string
}

/**
 * Create a fresh table via the API and return its ID and title.
 */
async function createTableViaApi(page: Page): Promise<{ id: number; title: string }> {
	const title = `StructureImportTest-${Date.now()}`
	const response = await page.request.post('/index.php/apps/tables/api/2/tables', {
		data: { title, emoji: '🗂' },
	})
	expect(response.ok()).toBeTruthy()
	const data = await response.json() as { ocs: { data: { id: number; title: string } } }
	return data.ocs.data
}

/**
 * Fetch the current scheme of a table via API.
 */
async function getTableSchemeViaApi(page: Page, tableId: number): Promise<StructureScheme> {
	const response = await page.request.get(
		`/ocs/v2.php/apps/tables/api/2/tables/${tableId}/scheme`,
		{ headers: { 'OCS-APIREQUEST': 'true' } },
	)
	expect(response.ok()).toBeTruthy()
	const json = await response.json() as { ocs: { data: StructureScheme } }
	return json.ocs.data
}

/**
 * Add a column via API.
 */
async function addColumnViaApi(page: Page, tableId: number, title: string, type: string): Promise<{ id: number }> {
	const response = await page.request.post(
		`/index.php/apps/tables/api/2/tables/${tableId}/columns`,
		{ data: { title, type } },
	)
	expect(response.ok()).toBeTruthy()
	const json = await response.json() as { ocs?: { data: { id: number } }; id?: number }
	return json.ocs?.data ?? json as unknown as { id: number }
}

/**
 * Build a minimal scheme JSON with one column and one view (referring to that column).
 */
function buildScheme(baseScheme: StructureScheme, extraColumn: boolean = false): StructureScheme {
	const newColumnId = 99999
	const columns = [...baseScheme.columns]
	const views = [...baseScheme.views]

	if (extraColumn) {
		columns.push({ id: newColumnId, title: 'Imported Column', type: 'text' })
		views.push({
			title: 'Imported View',
			filter: [],
			sort: [],
			columns: [newColumnId],
			columnSettings: [],
		})
	}

	return { ...baseScheme, columns, views }
}

/**
 * Open the "Import structure" action for a table in the navigation.
 * Uploads the given scheme file and waits for the modal to open.
 */
async function openImportStructureModal(page: Page, tableTitle: string, scheme: StructureScheme): Promise<void> {
	await ensureNavigationOpen(page)

	const tableItem = page
		.locator('[data-cy="navigationTableItem"]')
		.filter({ hasText: tableTitle })

	await tableItem.hover()
	const menuButton = tableItem.locator('[aria-haspopup="menu"]').first()
	await menuButton.waitFor({ state: 'visible' })
	await menuButton.click({ force: true })

	const [fileChooser] = await Promise.all([
		page.waitForEvent('filechooser'),
		page.getByRole('menuitem', { name: /Import structure/i }).click(),
	])

	await fileChooser.setFiles({
		name: 'structure.json',
		mimeType: 'application/json',
		buffer: Buffer.from(JSON.stringify(scheme)),
	})
}

test.describe('Structure import', () => {
	test('Upload valid scheme — diff loads and modal opens', async ({ userPage: { page } }) => {
		const table = await createTableViaApi(page)
		await addColumnViaApi(page, table.id, 'Name', 'text')
		const scheme = await getTableSchemeViaApi(page, table.id)
		const schemeWithExtra = buildScheme(scheme, true)

		await page.goto('/index.php/apps/tables')
		await expect(page.locator('.icon-loading').first()).toBeHidden()

		await openImportStructureModal(page, table.title, schemeWithExtra)

		const modal = page.getByRole('dialog', { name: /Import structure preview/i })
		await expect(modal).toBeVisible({ timeout: 15000 })
	})

	test('"Create view" rows start checked, all others unchecked', async ({ userPage: { page } }) => {
		const table = await createTableViaApi(page)
		await addColumnViaApi(page, table.id, 'Name', 'text')
		const scheme = await getTableSchemeViaApi(page, table.id)
		const schemeWithExtra = buildScheme(scheme, true)

		await page.goto('/index.php/apps/tables')
		await expect(page.locator('.icon-loading').first()).toBeHidden()

		await openImportStructureModal(page, table.title, schemeWithExtra)

		const modal = page.getByRole('dialog', { name: /Import structure preview/i })
		await expect(modal).toBeVisible({ timeout: 15000 })

		// The "Create views" checkbox for "Imported View" should be checked by default
		const viewCheckbox = modal.locator('.checkbox-radio-switch').filter({ hasText: 'Imported View' }).first()
		await expect(viewCheckbox.locator('input[type="checkbox"]')).toBeChecked()
	})

	test('Submit disabled when nothing checked', async ({ userPage: { page } }) => {
		const table = await createTableViaApi(page)
		await addColumnViaApi(page, table.id, 'Name', 'text')
		const scheme = await getTableSchemeViaApi(page, table.id)
		// Add only a new column (no view) so views-add won't be auto-checked
		const schemeWithCol = { ...scheme, columns: [...scheme.columns, { id: 88888, title: 'Extra', type: 'text' }] }

		await page.goto('/index.php/apps/tables')
		await expect(page.locator('.icon-loading').first()).toBeHidden()

		await openImportStructureModal(page, table.title, schemeWithCol)

		const modal = page.getByRole('dialog', { name: /Import structure preview/i })
		await expect(modal).toBeVisible({ timeout: 15000 })

		const submitBtn = modal.getByRole('button', { name: /Apply selected changes/i })
		await expect(submitBtn).toBeDisabled()
	})

	test('Checking any item enables submit', async ({ userPage: { page } }) => {
		const table = await createTableViaApi(page)
		await addColumnViaApi(page, table.id, 'Name', 'text')
		const scheme = await getTableSchemeViaApi(page, table.id)
		const schemeWithCol = { ...scheme, columns: [...scheme.columns, { id: 88888, title: 'Extra', type: 'text' }] }

		await page.goto('/index.php/apps/tables')
		await expect(page.locator('.icon-loading').first()).toBeHidden()

		await openImportStructureModal(page, table.title, schemeWithCol)

		const modal = page.getByRole('dialog', { name: /Import structure preview/i })
		await expect(modal).toBeVisible({ timeout: 15000 })

		const submitBtn = modal.getByRole('button', { name: /Apply selected changes/i })
		await expect(submitBtn).toBeDisabled()

		// Check the "Extra" column add item
		const addCheckbox = modal.locator('.checkbox-radio-switch').filter({ hasText: 'Extra' }).first()
		await addCheckbox.locator('input[type="checkbox"]').check()
		await expect(submitBtn).toBeEnabled()
	})

	test('Unchecking the only checked item disables submit again', async ({ userPage: { page } }) => {
		const table = await createTableViaApi(page)
		const scheme = await getTableSchemeViaApi(page, table.id)
		const schemeWithExtra = buildScheme(scheme, true)

		await page.goto('/index.php/apps/tables')
		await expect(page.locator('.icon-loading').first()).toBeHidden()

		await openImportStructureModal(page, table.title, schemeWithExtra)

		const modal = page.getByRole('dialog', { name: /Import structure preview/i })
		await expect(modal).toBeVisible({ timeout: 15000 })

		const submitBtn = modal.getByRole('button', { name: /Apply selected changes/i })
		await expect(submitBtn).toBeEnabled()

		// Uncheck the view
		const viewCheckbox = modal.locator('.checkbox-radio-switch').filter({ hasText: 'Imported View' }).first()
		await viewCheckbox.locator('input[type="checkbox"]').uncheck()
		await expect(submitBtn).toBeDisabled()
	})

	test('"Delete column" section is collapsed by default', async ({ userPage: { page } }) => {
		const table = await createTableViaApi(page)
		await addColumnViaApi(page, table.id, 'ToDelete', 'text')
		const scheme = await getTableSchemeViaApi(page, table.id)
		// Remove the column from the scheme so it appears as "delete" in the diff
		const schemeWithoutCol = { ...scheme, columns: [] }

		await page.goto('/index.php/apps/tables')
		await expect(page.locator('.icon-loading').first()).toBeHidden()

		await openImportStructureModal(page, table.title, schemeWithoutCol)

		const modal = page.getByRole('dialog', { name: /Import structure preview/i })
		await expect(modal).toBeVisible({ timeout: 15000 })

		// The collapsible delete section button should be visible but its content hidden
		const sectionBtn = modal.locator('button').filter({ hasText: /Delete column/i })
		await expect(sectionBtn).toBeVisible()
		await expect(sectionBtn).toHaveAttribute('aria-expanded', 'false')
	})

	test('Successful apply → table reloads with new column', async ({ userPage: { page } }) => {
		const table = await createTableViaApi(page)
		const scheme = await getTableSchemeViaApi(page, table.id)
		const schemeWithExtra = { ...scheme, columns: [...scheme.columns, { id: 77777, title: 'NewCol', type: 'text' }] }

		await page.goto('/index.php/apps/tables')
		await expect(page.locator('.icon-loading').first()).toBeHidden()

		await openImportStructureModal(page, table.title, schemeWithExtra)

		const modal = page.getByRole('dialog', { name: /Import structure preview/i })
		await expect(modal).toBeVisible({ timeout: 15000 })

		// Check "NewCol"
		const addCheckbox = modal.locator('.checkbox-radio-switch').filter({ hasText: 'NewCol' }).first()
		await addCheckbox.locator('input[type="checkbox"]').check()

		const applyReqPromise = page.waitForResponse(
			(response) =>
				response.url().includes('/scheme')
				&& response.request().method() === 'PUT',
		)

		await modal.getByRole('button', { name: /Apply selected changes/i }).click()
		await applyReqPromise

		// Modal should close
		await expect(modal).toBeHidden({ timeout: 10000 })

		// Navigate to table and verify new column header is visible
		const tableItem = page.locator('[data-cy="navigationTableItem"]').filter({ hasText: table.title })
		await tableItem.locator('a').first().click()
		await expect(page.locator('th').filter({ hasText: 'NewCol' })).toBeVisible({ timeout: 10000 })
	})

	test('Cancel → modal closes, no apply call made', async ({ userPage: { page } }) => {
		const table = await createTableViaApi(page)
		const scheme = await getTableSchemeViaApi(page, table.id)
		const schemeWithExtra = buildScheme(scheme, true)

		await page.goto('/index.php/apps/tables')
		await expect(page.locator('.icon-loading').first()).toBeHidden()

		await openImportStructureModal(page, table.title, schemeWithExtra)

		const modal = page.getByRole('dialog', { name: /Import structure preview/i })
		await expect(modal).toBeVisible({ timeout: 15000 })

		let applyCalled = false
		page.on('request', (req) => {
			if (req.url().includes('/scheme') && req.method() === 'PUT') {
				applyCalled = true
			}
		})

		await modal.getByRole('button', { name: /Cancel/i }).click()
		await expect(modal).toBeHidden({ timeout: 5000 })
		expect(applyCalled).toBe(false)
	})
})
