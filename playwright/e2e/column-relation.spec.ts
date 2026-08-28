/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { type Page } from '@playwright/test'
import { test, expect } from '../support/fixtures'
import {
	createContext,
	createTable,
	createTextLineColumn,
	loadContext,
	loadTable,
	openContextEditModal,
	openCreateColumnModal,
	openCreateRowModal,
	fillInValueTextLine,
} from '../support/commands'

const sourceTableTitle = 'Test relation source'
const targetTableTitle = 'Test relation target'
const sourceColumnTitle = 'Name'
const relationColumnTitle = 'Refers to'

async function selectFromVueDropdown(page: Page, label: string, value: string) {
	const input = page.locator(`[aria-label="${label}"] input, [aria-label="${label}"]`).first()
	await input.click()
	await input.clear()
	await input.fill(value)

	// NcSelect options may not expose a clean accessible name, so try several selectors
	const optionByRole = page.getByRole('option', { name: new RegExp(value, 'i') }).first()
	if (await optionByRole.isVisible({ timeout: 3000 }).catch(() => false)) {
		await optionByRole.click()
		return
	}

	const optionByText = page.locator('ul.vs__dropdown-menu li').filter({ hasText: new RegExp(value, 'i') }).first()
	await optionByText.waitFor({ state: 'visible', timeout: 5000 })
	await optionByText.click()
}

test.describe('Test column relation', () => {
	test.setTimeout(60000)

	test('Create relation column, row, and verify label in table', async ({ userPage: { page } }) => {
		await page.goto('/index.php/apps/tables')

		// source table with one row
		await createTable(page, sourceTableTitle)
		await loadTable(page, sourceTableTitle)
		await createTextLineColumn(page, sourceColumnTitle, '', '', true)

		await openCreateRowModal(page)
		await fillInValueTextLine(page, sourceColumnTitle, 'Alice')
		await page.locator('[data-cy="createRowSaveButton"]').click()
		await expect(page.locator('[data-cy="ncTable"] [data-cy="customTableRow"]').filter({ hasText: 'Alice' })).toBeVisible()

		// target table with relation column to source
		await createTable(page, targetTableTitle)
		await loadTable(page, targetTableTitle)

		await openCreateColumnModal(page, true)
		await page.locator('[data-cy="columnTypeFormInput"]').clear()
		await page.locator('[data-cy="columnTypeFormInput"]').fill(relationColumnTitle)
		await page.locator('.columnTypeSelection .vs__open-indicator').click()
		await page.locator('.vs__dropdown-menu .multiSelectOptionLabel').filter({ hasText: 'Relation' }).click()

		// configure the relation to point at the source table and use 'Name' as label
		await selectFromVueDropdown(page, 'Select target', sourceTableTitle)
		await page.waitForResponse(
			r => r.url().includes('/apps/tables/api/1/tables/')
				&& r.url().includes('/columns')
				&& r.request().method() === 'GET',
		)
		await selectFromVueDropdown(page, 'Select label for relation selection', sourceColumnTitle)

		await page.locator('[data-cy="createColumnSaveBtn"]').click()
		await expect(
			page.locator('[data-cy="ncTable"] table tr th').filter({ hasText: relationColumnTitle }),
		).toBeVisible()

		// create a row in the target and select the source row as relation
		const relationOptionsResponse = page.waitForResponse(
			r => r.url().includes('/apps/tables/api/1/')
				&& r.url().includes('/relations')
				&& r.request().method() === 'GET',
		)
		await openCreateRowModal(page)
		await relationOptionsResponse
		await selectFromVueDropdown(page, 'Select relation value', 'Alice')
		await page.locator('[data-cy="createRowSaveButton"]').click()
		await expect(
			page.locator('[data-cy="ncTable"] [data-cy="customTableRow"]').filter({ hasText: 'Alice' }),
		).toBeVisible()
	})

	test('Relation labels render inside an application context', async ({ userPage: { page } }) => {
		await page.goto('/index.php/apps/tables')

		const contextSourceTitle = 'Test relation context source'
		const contextTargetTitle = 'Test relation context target'
		const contextTitle = 'Test relation context'

		// source table with one row
		await createTable(page, contextSourceTitle)
		await loadTable(page, contextSourceTitle)
		await createTextLineColumn(page, sourceColumnTitle, '', '', true)

		await openCreateRowModal(page)
		await fillInValueTextLine(page, sourceColumnTitle, 'Bob')
		await page.locator('[data-cy="createRowSaveButton"]').click()

		// target table with relation column to source
		await createTable(page, contextTargetTitle)
		await loadTable(page, contextTargetTitle)

		await openCreateColumnModal(page, true)
		await page.locator('[data-cy="columnTypeFormInput"]').clear()
		await page.locator('[data-cy="columnTypeFormInput"]').fill(relationColumnTitle)
		await page.locator('.columnTypeSelection .vs__open-indicator').click()
		await page.locator('.vs__dropdown-menu .multiSelectOptionLabel').filter({ hasText: 'Relation' }).click()

		await selectFromVueDropdown(page, 'Select target', contextSourceTitle)
		await page.waitForResponse(
			r => r.url().includes('/apps/tables/api/1/tables/')
				&& r.url().includes('/columns')
				&& r.request().method() === 'GET',
		)
		await selectFromVueDropdown(page, 'Select label for relation selection', sourceColumnTitle)

		await page.locator('[data-cy="createColumnSaveBtn"]').click()
		await expect(
			page.locator('[data-cy="ncTable"] table tr th').filter({ hasText: relationColumnTitle }),
		).toBeVisible()

		// create a row in the target and select the source row as relation
		const relationOptionsResponse = page.waitForResponse(
			r => r.url().includes('/apps/tables/api/1/')
				&& r.url().includes('/relations')
				&& r.request().method() === 'GET',
		)
		await openCreateRowModal(page)
		await relationOptionsResponse
		await selectFromVueDropdown(page, 'Select relation value', 'Bob')
		await page.locator('[data-cy="createRowSaveButton"]').click()

		// add the target table to an application context
		await createContext(page, contextTitle)
		await openContextEditModal(page, contextTitle)

		const resourceInput = page.locator('[data-cy="contextResourceForm"] input').first()
		await resourceInput.click()
		await resourceInput.fill(contextTargetTitle)
		await page.getByRole('option', { name: new RegExp(contextTargetTitle, 'i') }).first().click()

		await page.locator('[data-cy="editContextSubmitBtn"]').click()

		// load the context and verify the relation label is rendered without crashing
		await loadContext(page, contextTitle)
		await expect(
			page.locator('[data-cy="ncTable"] [data-cy="customTableRow"]').filter({ hasText: 'Bob' }),
		).toBeVisible()
	})
})
