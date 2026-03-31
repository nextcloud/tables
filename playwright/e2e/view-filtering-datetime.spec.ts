/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test as base, expect, type BrowserContext, type Page } from '@playwright/test'
import { test } from '../support/fixtures'
import { createDatetimeDateColumn, createTable, createTextLineColumn, fillInValueTextLine, loadTable } from '../support/commands'
import { createRandomUser } from '../support/api'
import { login } from '../support/login'

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
	// Run tests serially, sharing a single page/user/table to avoid
	// overwhelming the CI server with repeated heavy setup.
	test.describe.configure({ mode: 'serial' })

	let context: BrowserContext
	let page: Page

	// @ts-expect-error - Playwright complex types mismatch in this environment
	base.beforeAll(async ({ browser, baseURL }) => {
		context = await browser.newContext({
			baseURL,
		})
		page = await context.newPage()

		const user = await createRandomUser(page.request)
		await login(page, user)

		await page.goto('/index.php/apps/tables')
		await setupDatetimeFilteringTable(page)
		await loadTable(page, tableTitle)
	}, 120000)

	test.afterAll(async () => {
		await context?.close()
	})

	test.beforeEach(async () => {
		await page.goto('/index.php/apps/tables')
		await loadTable(page, tableTitle)
		// Ensure no modals are left open from previous tests in serial mode
		await page.keyboard.press('Escape')
	})

	test('Filter view for dates 1-30 days ahead', async () => {
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
		await expect(page.locator('[data-cy="customTableRow"]').filter({ hasText: /tomorrow/i }).first()).toBeVisible({ timeout: 10000 })
		await expect(page.locator('[data-cy="customTableRow"]').filter({ hasText: /30 days ahead/i }).first()).toBeVisible({ timeout: 10000 })

		// check for not existing rows
		await expect(page.locator('[data-cy="customTableRow"]').filter({ hasText: /^today$/i })).toBeHidden()
		await expect(page.locator('[data-cy="customTableRow"]').filter({ hasText: /^yesterday$/i })).toBeHidden()
		await expect(page.locator('[data-cy="customTableRow"]').filter({ hasText: /^60 days ahead$/i })).toBeHidden()
		await expect(page.locator('[data-cy="customTableRow"]').filter({ hasText: /^30 days ago$/i })).toBeHidden()
		await expect(page.locator('[data-cy="customTableRow"]').filter({ hasText: /^60 days ago$/i })).toBeHidden()
	})

	test('Filter view for dates 1-30 days ago', async () => {
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
		await expect(page.locator('[data-cy="customTableRow"]').filter({ hasText: /yesterday/i }).first()).toBeVisible({ timeout: 10000 })
		await expect(page.locator('[data-cy="customTableRow"]').filter({ hasText: /30 days ago/i }).first()).toBeVisible({ timeout: 10000 })

		// check for not existing rows
		await expect(page.locator('[data-cy="customTableRow"]').filter({ hasText: /^today$/i })).toBeHidden()
		await expect(page.locator('[data-cy="customTableRow"]').filter({ hasText: /^tomorrow$/i })).toBeHidden()
		await expect(page.locator('[data-cy="customTableRow"]').filter({ hasText: /^30 days ahead$/i })).toBeHidden()
		await expect(page.locator('[data-cy="customTableRow"]').filter({ hasText: /^60 days ahead$/i })).toBeHidden()
		await expect(page.locator('[data-cy="customTableRow"]').filter({ hasText: /^60 days ago$/i })).toBeHidden()
	})
})
