/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test, expect } from '../support/fixtures'
import * as fs from 'fs'
import { type Page } from '@playwright/test'
import { clickOnTableThreeDotMenu, getTutorialTableName, loadTable } from '../support/commands'

async function fillSearchInput(page: Page, value: string) {
	// Scope to the NcTable container to avoid matching Nextcloud header search elements
	const searchInput = page.locator('[data-cy="ncTable"]').getByRole('textbox', { name: 'Search' })
	await expect(searchInput).toBeVisible({ timeout: 10000 })
	await searchInput.fill(value)
	await page.waitForTimeout(600) // debounce in SearchForm is 500 ms
}

async function clickSelectionBarAction(page: Page, label: string) {
	await expect(page.locator('.icon-loading').first()).toBeHidden({ timeout: 10000 })
	await expect(page.locator('.selected-rows-option')).toBeVisible({ timeout: 10000 })
	// NcActionButton does not forward data-cy to the DOM; match by button text content instead.
	// With inline=2 the items render as plain <button> elements directly in the selection bar.
	const item = page.locator('.selected-rows-option button').filter({ hasText: label })
	await expect(item.first()).toBeVisible({ timeout: 5000 })
	await item.first().click()
}

async function selectFirstRow(page: Page) {
	const checkbox = page.locator('[data-cy="customTableRow"]:first-of-type input[type="checkbox"]').first()
	await expect(checkbox).toBeVisible({ timeout: 10000 })
	await checkbox.click({ force: true })
	await expect(checkbox).toBeChecked()
}

test.describe('CSV export', () => {

	test('Export all rows is always available in the three-dot menu', async ({ userPage: { page } }) => {
		await page.goto('/index.php/apps/tables')
		await loadTable(page, 'Welcome to Nextcloud Tables!')

		const tutorialName = await getTutorialTableName(page)
		const fileNamePattern = new RegExp(`^\\d{2}-\\d{2}-\\d{2}_\\d{2}-\\d{2}_${tutorialName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\.csv$`)

		const [download] = await Promise.all([
			page.waitForEvent('download'),
			clickOnTableThreeDotMenu(page, 'Export all rows'),
		])

		expect(download.suggestedFilename()).toMatch(fileNamePattern)

		const path = await download.path()
		const content = fs.readFileSync(path, 'utf8')

		expect(content).toContain('What,How to do,Ease of use,Done')
		expect(content).toContain('Open the tables app,Reachable via the Tables icon in the apps list.,5,true')
	})

	test('Export filtered rows only appears in three-dot menu when a filter is active', async ({ userPage: { page } }) => {
		await page.goto('/index.php/apps/tables')
		await loadTable(page, 'Welcome to Nextcloud Tables!')

		await fillSearchInput(page, 'Open the tables app')

		// Export filtered and verify only the matching row is in the CSV
		const [download] = await Promise.all([
			page.waitForEvent('download'),
			clickOnTableThreeDotMenu(page, 'Export filtered rows'),
		])

		const path = await download.path()
		const content = fs.readFileSync(path, 'utf8')

		expect(content).toContain('Open the tables app')
		expect(content).not.toContain('Add a new column')
	})

	test('Export selected rows appears in selection bar when rows are checked', async ({ userPage: { page } }) => {
		await page.goto('/index.php/apps/tables')
		await loadTable(page, 'Welcome to Nextcloud Tables!')

		await selectFirstRow(page)

		// Export selected — should only contain 1 data row
		const [download] = await Promise.all([
			page.waitForEvent('download'),
			clickSelectionBarAction(page, 'Export selected rows'),
		])

		const path = await download.path()
		const content = fs.readFileSync(path, 'utf8')
		const lines = content.trim().split('\n')

		// Header + exactly 1 data row
		expect(lines).toHaveLength(2)
	})

	test('Export filtered rows appears in selection bar when rows are selected and filter is active', async ({ userPage: { page } }) => {
		await page.goto('/index.php/apps/tables')
		await loadTable(page, 'Welcome to Nextcloud Tables!')

		await fillSearchInput(page, 'Open')
		await selectFirstRow(page)

		// Both export buttons must be visible in the selection bar
		await expect(page.locator('.selected-rows-option')).toBeVisible({ timeout: 10000 })
		for (const label of ['Export selected rows', 'Export filtered rows']) {
			await expect(
				page.locator('.selected-rows-option button').filter({ hasText: label }).first(),
			).toBeVisible({ timeout: 5000 })
		}
	})

	test('Export all rows includes unfiltered data even when filter is active', async ({ userPage: { page } }) => {
		await page.goto('/index.php/apps/tables')
		await loadTable(page, 'Welcome to Nextcloud Tables!')

		await fillSearchInput(page, 'Open the tables app')

		// Export ALL rows — must include rows not matching the filter
		const [download] = await Promise.all([
			page.waitForEvent('download'),
			clickOnTableThreeDotMenu(page, 'Export all rows'),
		])

		const path = await download.path()
		const content = fs.readFileSync(path, 'utf8')

		expect(content).toContain('Open the tables app')
		expect(content).toContain('Add a new column')
	})

})
