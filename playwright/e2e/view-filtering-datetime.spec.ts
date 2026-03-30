/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test, expect } from '../support/fixtures'
import type { Page } from '@playwright/test'
import { createDatetimeDateColumn, createTable, createTextLineColumn, fillInValueTextLine, loadTable } from '../support/commands'

const tableTitle = 'View datetime filtering test table'

const today = new Date()
const [tomorrow, yesterday, daysAhead30, daysAhead60, daysAgo30, daysAgo60] = [1, -1, 30, 60, -30, -60].map(days => {
	const d = new Date()
	d.setUTCDate(d.getUTCDate() + days)
	return d
})

const formatDate = (date: Date) => date.toISOString().split('T')[0]

async function setupDatetimeFilteringTable(page: Page) {
	await createTable(page, tableTitle)
	await createTextLineColumn(page, 'title', '', '', true)
	await createDatetimeDateColumn(page, 'date', false, false)

	const addRow = async (title: string, date: Date) => {
		await page.locator('[data-cy="createRowBtn"]').click()
		await fillInValueTextLine(page, 'title', title)
		const input = page.locator('.modal__content input.native-datetime-picker--input')
		await input.clear()
		await input.fill(formatDate(date))
		await page.locator('[data-cy="createRowSaveButton"]').click()
	}

	await addRow('today', today)
	await addRow('tomorrow', tomorrow)
	await addRow('yesterday', yesterday)
	await addRow('30 days ahead', daysAhead30)
	await addRow('60 days ahead', daysAhead60)
	await addRow('30 days ago', daysAgo30)
	await addRow('60 days ago', daysAgo60)
}

test.describe('Filtering in a view by datetime', () => {

	test('Filter view for dates 1-30 days ahead', async ({ userPage: { page } }) => {
		await page.goto('/index.php/apps/tables')
		await setupDatetimeFilteringTable(page)
		await loadTable(page, tableTitle)

		// create view
		const title = 'Next 30 days'
		await page.locator('[data-cy="customTableAction"] button').click()
		await page.locator('[data-cy="dataTableCreateViewBtn"]').filter({ hasText: 'Create view' }).waitFor({ state: 'visible' })
		await page.locator('[data-cy="dataTableCreateViewBtn"]').filter({ hasText: 'Create view' }).click({ force: true })
		const titleInput1 = page.locator('[data-cy="viewSettingsDialogTitleInput"]')
		await titleInput1.waitFor({ state: 'visible', timeout: 10000 })
		await titleInput1.fill(title)

		// add filter for >= 1 day ahead
		await page.locator('[data-cy="filterFormFilterGroupBtn"]').click()
		await page.locator('.modal-container .filter-group .v-select.select').nth(0).click()
		await page.locator('ul.vs__dropdown-menu li span[title="date"]').click()
		await page.locator('.modal-container .filter-group .v-select.select').nth(1).click()
		await page.locator('ul.vs__dropdown-menu li span[title="Is greater than or equal"]').click()
		await page.locator('.modal-container .filter-group .v-select.select').nth(2).click()
		await page.locator('ul.vs__dropdown-menu li span[title="Number of days ahead"]').click()
		await page.locator('[data-cy="filterEntryNumber"]').nth(0).fill('1')

		// add filter for <= 30 days ahead
		await page.locator('[data-cy="filterGroupAddFilterBtn"]').click()
		await page.locator('.modal-container .filter-group .v-select.select').nth(3).click()
		await page.locator('ul.vs__dropdown-menu li span[title="date"]').click()
		await page.locator('.modal-container .filter-group .v-select.select').nth(4).click()
		await page.locator('ul.vs__dropdown-menu li span[title="Is lower than or equal"]').click()
		await page.locator('.modal-container .filter-group .v-select.select').nth(5).click()
		await page.locator('ul.vs__dropdown-menu li span[title="Number of days ahead"]').click()
		await page.locator('[data-cy="filterEntryNumber"]').nth(1).fill('30')

		// save view
		const createViewReqPromise = page.waitForResponse(response => response.url().includes('/apps/tables/view') && response.request().method() === 'POST')
		await page.locator('[data-cy="modifyViewBtn"]').click()
		await createViewReqPromise

		await expect(page.locator('.app-navigation-entry-link span').filter({ hasText: title })).toBeVisible()

		// check for existing rows
		await expect(page.locator('.custom-table table tr td div').filter({ hasText: 'tomorrow' }).first()).toBeVisible()
		await expect(page.locator('.custom-table table tr td div').filter({ hasText: '30 days ahead' }).first()).toBeVisible()

		// check for not existing rows
		await expect(page.locator('.custom-table table tr td div', { hasText: 'today' })).toBeHidden()
		await expect(page.locator('.custom-table table tr td div', { hasText: 'yesterday' })).toBeHidden()
		await expect(page.locator('.custom-table table tr td div', { hasText: '60 days ahead' })).toBeHidden()
		await expect(page.locator('.custom-table table tr td div', { hasText: '30 days ago' })).toBeHidden()
		await expect(page.locator('.custom-table table tr td div', { hasText: '60 days ago' })).toBeHidden()
	})

	test('Filter view for dates 1-30 days ago', async ({ userPage: { page } }) => {
		await page.goto('/index.php/apps/tables')
		await setupDatetimeFilteringTable(page)
		await loadTable(page, tableTitle)

		// create view
		const title = 'Last 30 days'
		await page.locator('[data-cy="customTableAction"] button').click()
		await page.locator('[data-cy="dataTableCreateViewBtn"]').filter({ hasText: 'Create view' }).waitFor({ state: 'visible' })
		await page.locator('[data-cy="dataTableCreateViewBtn"]').filter({ hasText: 'Create view' }).click({ force: true })
		const titleInput2 = page.locator('[data-cy="viewSettingsDialogTitleInput"]')
		await titleInput2.waitFor({ state: 'visible', timeout: 10000 })
		await titleInput2.fill(title)

		// add filter for <= 1 day ago
		await page.locator('[data-cy="filterFormFilterGroupBtn"]').click()
		await page.locator('.modal-container .filter-group .v-select.select').nth(0).click()
		await page.locator('ul.vs__dropdown-menu li span[title="date"]').click()
		await page.locator('.modal-container .filter-group .v-select.select').nth(1).click()
		await page.locator('ul.vs__dropdown-menu li span[title="Is lower than or equal"]').click()
		await page.locator('.modal-container .filter-group .v-select.select').nth(2).click()
		await page.locator('ul.vs__dropdown-menu li span[title="Number of days ago"]').click()
		await page.locator('[data-cy="filterEntryNumber"]').nth(0).fill('1')

		// add filter for >= 30 days ago
		await page.locator('[data-cy="filterGroupAddFilterBtn"]').click()
		await page.locator('.modal-container .filter-group .v-select.select').nth(3).click()
		await page.locator('ul.vs__dropdown-menu li span[title="date"]').click()
		await page.locator('.modal-container .filter-group .v-select.select').nth(4).click()
		await page.locator('ul.vs__dropdown-menu li span[title="Is greater than or equal"]').click()
		await page.locator('.modal-container .filter-group .v-select.select').nth(5).click()
		await page.locator('ul.vs__dropdown-menu li span[title="Number of days ago"]').click()
		await page.locator('[data-cy="filterEntryNumber"]').nth(1).fill('30')

		// save view
		const createViewReqPromise = page.waitForResponse(response => response.url().includes('/apps/tables/view') && response.request().method() === 'POST')
		await page.locator('[data-cy="modifyViewBtn"]').click()
		await createViewReqPromise

		await expect(page.locator('.app-navigation-entry-link span').filter({ hasText: title })).toBeVisible()

		// check for existing rows
		await expect(page.locator('.custom-table table tr td div').filter({ hasText: 'yesterday' }).first()).toBeVisible()
		await expect(page.locator('.custom-table table tr td div').filter({ hasText: '30 days ago' }).first()).toBeVisible()

		// check for not existing rows
		await expect(page.locator('.custom-table table tr td div', { hasText: 'today' })).toBeHidden()
		await expect(page.locator('.custom-table table tr td div', { hasText: 'tomorrow' })).toBeHidden()
		await expect(page.locator('.custom-table table tr td div', { hasText: '30 days ahead' })).toBeHidden()
		await expect(page.locator('.custom-table table tr td div', { hasText: '60 days ahead' })).toBeHidden()
		await expect(page.locator('.custom-table table tr td div', { hasText: '60 days ago' })).toBeHidden()
	})
})
