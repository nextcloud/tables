/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test, expect } from '../support/fixtures'
import type { Page } from '@playwright/test'
import {
	createSelectionCheckColumn,
	createSelectionColumn,
	createSelectionMultiColumn,
	createTable,
	createTextLineColumn,
	fillInValueSelection,
	fillInValueSelectionCheck,
	fillInValueSelectionMulti,
	fillInValueTextLine,
	loadTable,
} from '../support/commands'

const tableTitle = 'View filtering test table'

interface FilterConfig {
	column: string
	operator: string
	value: string
}

type FilterGroup = FilterConfig[]

async function setupFilteringTable(page: Page) {
	await createTable(page, tableTitle)
	await createTextLineColumn(page, 'title', '', '', true)
	await createSelectionColumn(page, 'selection', ['sel1', 'sel2', 'sel3', 'sel4'], '', false)
	await createSelectionMultiColumn(page, 'multi selection', ['A', 'B', 'C', 'D'], [], false)
	await createSelectionCheckColumn(page, 'check', false, false)

	const addRow = async (title: string, sel: string, multi: string[], check: boolean) => {
		await page.locator('[data-cy="createRowBtn"]').click()
		await fillInValueTextLine(page, 'title', title)
		if (sel) await fillInValueSelection(page, 'selection', sel)
		if (multi.length > 0) await fillInValueSelectionMulti(page, 'multi selection', multi)
		if (check) await fillInValueSelectionCheck(page, 'check')
		await page.locator('[data-cy="createRowSaveButton"]').click()
	}

	await addRow('first row', 'sel1', ['A', 'B'], true)
	await addRow('second row', 'sel2', ['B'], true)
	await addRow('third row', 'sel3', ['C', 'B', 'D'], false)
	await addRow('fourth row', '', ['A'], true)
	await addRow('fifths row', 'sel4', ['D'], true)
	await addRow('sixths row', 'sel1', ['C', 'D'], true)
	await addRow('sevenths row', 'sel2', ['A', 'C', 'B', 'D'], false)
}

async function selectFilterOption(page: Page, index: number, title: string) {
	await page.locator('.modal-container .filter-group .v-select.select').nth(index).click()
	await page.locator(`ul.vs__dropdown-menu li span[title="${title}"]`).click()
}

async function createFilteredView(page: Page, title: string, groups: FilterGroup[]) {
	await page.locator('[data-cy="customTableAction"] button').click()
	await page.locator('[data-cy="dataTableCreateViewBtn"]').filter({ hasText: 'Create view' }).waitFor({ state: 'visible' })
	await page.locator('[data-cy="dataTableCreateViewBtn"]').filter({ hasText: 'Create view' }).click({ force: true })
	const titleInput = page.locator('[data-cy="viewSettingsDialogTitleInput"]')
	await titleInput.waitFor({ state: 'visible', timeout: 10000 })
	await titleInput.fill(title)

	let selectIndex = 0
	for (let groupIndex = 0; groupIndex < groups.length; groupIndex++) {
		await page.locator('[data-cy="filterFormFilterGroupBtn"]').click()
		for (let filterIndex = 0; filterIndex < groups[groupIndex].length; filterIndex++) {
			if (filterIndex > 0) {
				await page.locator('[data-cy="filterGroupAddFilterBtn"]').click()
			}
			const filter = groups[groupIndex][filterIndex]
			await selectFilterOption(page, selectIndex++, filter.column)
			await selectFilterOption(page, selectIndex++, filter.operator)
			await selectFilterOption(page, selectIndex++, filter.value)
		}
	}

	const createViewReqPromise = page.waitForResponse(
		(response) =>
			response.url().includes('/apps/tables/view')
			&& response.request().method() === 'POST',
	)
	await page.locator('[data-cy="modifyViewBtn"]').click()
	await createViewReqPromise

	await expect(page.locator('.app-navigation-entry-link span').filter({ hasText: title })).toBeVisible()
}

async function openCurrentViewForEditing(page: Page) {
	await page.locator('[data-cy="customTableAction"] button').first().click()
	const editViewBtn = page.getByText('Edit view', { exact: true })
	await editViewBtn.waitFor({ state: 'visible' })
	await editViewBtn.click()
	await expect(page.locator('[data-cy="viewSettingsDialog"]')).toBeVisible()
}

async function expectVisibleRows(page: Page, rows: string[]) {
	for (const row of rows) {
		await expect(
			page.locator('[data-cy="customTableRow"]').filter({ hasText: row }).first(),
		).toBeVisible()
	}
}

async function expectMissingRows(page: Page, rows: string[]) {
	for (const row of rows) {
		await expect(
			page.locator('[data-cy="customTableRow"]').filter({ hasText: row }),
		).toHaveCount(0)
	}
}

test.describe('Filtering in a view by selection columns', () => {
	test.beforeEach(async ({ userPage: { page } }) => {
		await page.goto('/index.php/apps/tables')
		await setupFilteringTable(page)
		await loadTable(page, tableTitle)
	})

	test('Filter view for single selection', async ({ userPage: { page } }) => {
		await createFilteredView(page, 'Filter for single selection', [[
			{ column: 'selection', operator: 'Is equal', value: 'sel2' },
		]])

		await expectVisibleRows(page, ['sevenths row', 'second row'])
		await expectMissingRows(page, ['first row', 'third row', 'fourth row', 'fifths row', 'sixths row'])

		await openCurrentViewForEditing(page)
		await page.locator('.modal-container .filter-group .v-select.select').nth(2).click()
		await page.locator('ul.vs__dropdown-menu li span').first().click()

		const updateViewReqPromise = page.waitForResponse(
			(response) =>
				response.url().includes('/apps/tables/view/')
				&& response.request().method() === 'PUT',
		)
		await page.locator('[data-cy="modifyViewBtn"]').click()
		await updateViewReqPromise

		await expectVisibleRows(page, ['first row', 'sixths row'])
		await expectMissingRows(page, ['second row', 'third row', 'fourth row', 'fifths row', 'sevenths row'])
	})

	test('Filter view for multi selection - equals', async ({ userPage: { page } }) => {
		await createFilteredView(page, 'Filter for multi selection 1', [[
			{ column: 'multi selection', operator: 'Is equal', value: 'A' },
		]])

		await expectVisibleRows(page, ['fourth row'])
		await expectMissingRows(page, ['first row', 'second row', 'third row', 'fifths row', 'sixths row', 'sevenths row'])
	})

	test('Filter view for single selection - is not equal', async ({ userPage: { page } }) => {
		await createFilteredView(page, 'Filter for single selection - is not equal', [[
			{ column: 'selection', operator: 'Is not equal', value: 'sel2' },
		]])

		await expectVisibleRows(page, ['first row', 'third row', 'fifths row', 'sixths row'])
		await expectMissingRows(page, ['second row', 'fourth row', 'sevenths row'])
	})

	test('Filter view for multi selection - contains', async ({ userPage: { page } }) => {
		await createFilteredView(page, 'Filter for multi selection 2', [[
			{ column: 'multi selection', operator: 'Contains', value: 'A' },
		]])

		await expectVisibleRows(page, ['first row', 'fourth row', 'sevenths row'])
		await expectMissingRows(page, ['second row', 'third row', 'fifths row', 'sixths row'])
	})

	test('Filter view for multi selection - multiple contains', async ({ userPage: { page } }) => {
		await createFilteredView(page, 'Filter for multi selection 3', [[
			{ column: 'multi selection', operator: 'Contains', value: 'A' },
			{ column: 'multi selection', operator: 'Contains', value: 'B' },
		]])

		await expectVisibleRows(page, ['first row', 'sevenths row'])
		await expectMissingRows(page, ['second row', 'third row', 'fourth row', 'fifths row', 'sixths row'])
	})

	test('Filter view for single selection - does not contain', async ({ userPage: { page } }) => {
		await createFilteredView(page, 'Filter does not contain sel2', [[
			{ column: 'selection', operator: 'Does not contain', value: 'sel2' },
		]])

		await expectVisibleRows(page, ['first row', 'third row', 'fourth row', 'fifths row', 'sixths row'])
		await expectMissingRows(page, ['second row', 'sevenths row'])
	})

	test('Filter view for multi selection - does not contain', async ({ userPage: { page } }) => {
		await createFilteredView(page, 'Filter multi selection does not contain A', [[
			{ column: 'multi selection', operator: 'Does not contain', value: 'A' },
		]])

		await expectVisibleRows(page, ['third row', 'fifths row', 'sixths row'])
		await expectMissingRows(page, ['first row', 'fourth row', 'sevenths row'])
	})

	test('Filter view for multi selection - multiple filter groups', async ({ userPage: { page } }) => {
		await createFilteredView(page, 'Filter for multi selection 4', [
			[
				{ column: 'multi selection', operator: 'Contains', value: 'A' },
				{ column: 'multi selection', operator: 'Contains', value: 'B' },
			],
			[
				{ column: 'multi selection', operator: 'Contains', value: 'D' },
			],
		])

		await expectVisibleRows(page, ['first row', 'third row', 'fifths row', 'sixths row', 'sevenths row'])
		await expectMissingRows(page, ['second row', 'fourth row'])
	})

	test('Filter view for selection check', async ({ userPage: { page } }) => {
		await createFilteredView(page, 'Filter for check selection', [[
			{ column: 'check', operator: 'Is equal', value: 'Checked' },
		]])

		await expectVisibleRows(page, ['first row', 'second row', 'fourth row', 'fifths row', 'sixths row'])
		await expectMissingRows(page, ['third row', 'sevenths row'])
	})
})
