/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test, expect } from '../support/fixtures'
import {
	createTable,
	createTextLineColumn,
	loadTable,
	openCreateColumnModal,
	openCreateRowModal,
	fillInValueTextLine,
	selectFromVueDropdown,
} from '../support/commands'

const sourceTableTitle = 'Test relation lookup source'
const targetTableTitle = 'Test relation lookup target'
const nameColumnTitle = 'Name'
const cityColumnTitle = 'City'
const relationColumnTitle = 'Refers to'
const lookupColumnTitle = 'City of person'

test.describe('Test column relation lookup', () => {
	test.setTimeout(60000)

	test('Create relation lookup column, row, and verify looked-up value in table', async ({ userPage: { page } }) => {
		await page.goto('/index.php/apps/tables')

		// source table with a name and a city column
		await createTable(page, sourceTableTitle)
		await loadTable(page, sourceTableTitle)
		await createTextLineColumn(page, nameColumnTitle, '', '', true)
		await createTextLineColumn(page, cityColumnTitle, '', '', false)

		await openCreateRowModal(page)
		await fillInValueTextLine(page, nameColumnTitle, 'Alice')
		await fillInValueTextLine(page, cityColumnTitle, 'Berlin')
		await page.locator('[data-cy="createRowSaveButton"]').click()
		await expect(page.locator('[data-cy="ncTable"] [data-cy="customTableRow"]').filter({ hasText: 'Berlin' })).toBeVisible()

		// target table with relation column to source
		await createTable(page, targetTableTitle)
		await loadTable(page, targetTableTitle)

		await openCreateColumnModal(page, true)
		await page.locator('[data-cy="columnTypeFormInput"]').clear()
		await page.locator('[data-cy="columnTypeFormInput"]').fill(relationColumnTitle)
		await page.locator('.columnTypeSelection .vs__open-indicator').click()
		await page.locator('.vs__dropdown-menu .multiSelectOptionLabel').getByText('Relation', { exact: true }).click()

		const targetColumnsResponse = page.waitForResponse(
			r => r.url().includes('/apps/tables/api/1/tables/')
				&& r.url().includes('/columns')
				&& r.request().method() === 'GET',
		)
		await selectFromVueDropdown(page, 'Select target', sourceTableTitle)
		await targetColumnsResponse
		await selectFromVueDropdown(page, 'Select label for relation selection', nameColumnTitle)
		await page.locator('[data-cy="createColumnSaveBtn"]').click()
		await expect(
			page.locator('[data-cy="ncTable"] table tr th').filter({ hasText: relationColumnTitle }),
		).toBeVisible()

		// relation lookup column reading 'City' through the relation column
		await openCreateColumnModal(page, false)
		await page.locator('[data-cy="columnTypeFormInput"]').clear()
		await page.locator('[data-cy="columnTypeFormInput"]').fill(lookupColumnTitle)
		await page.locator('.columnTypeSelection .vs__open-indicator').click()
		await page.locator('.vs__dropdown-menu .multiSelectOptionLabel').getByText('Relation lookup', { exact: true }).click()

		const lookupTargetColumnsResponse = page.waitForResponse(
			r => r.url().includes('/apps/tables/api/1/tables/')
				&& r.url().includes('/columns')
				&& r.request().method() === 'GET',
		)
		await selectFromVueDropdown(page, 'Select relation column', relationColumnTitle)
		await lookupTargetColumnsResponse
		await selectFromVueDropdown(page, 'Select target column', cityColumnTitle)

		await page.locator('[data-cy="createColumnSaveBtn"]').click()
		await expect(
			page.locator('[data-cy="ncTable"] table tr th').filter({ hasText: lookupColumnTitle }),
		).toBeVisible()

		// create a row and pick the source row as relation
		const relationOptionsResponse = page.waitForResponse(
			r => r.url().includes('/apps/tables/api/1/')
				&& r.url().includes('/relations')
				&& r.request().method() === 'GET',
		)
		await openCreateRowModal(page)
		await relationOptionsResponse
		await selectFromVueDropdown(page, 'Select relation value', 'Alice')

		// the lookup field previews the looked-up value inside the modal
		await expect(page.locator('[data-cy="createRowModal"]')).toContainText('Berlin')

		await page.locator('[data-cy="createRowSaveButton"]').click()

		const row = page.locator('[data-cy="ncTable"] [data-cy="customTableRow"]').filter({ hasText: 'Alice' })
		await expect(row).toBeVisible()
		await expect(row).toContainText('Berlin')
	})
})
