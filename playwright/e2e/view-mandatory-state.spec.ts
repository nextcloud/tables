/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test as base } from '@playwright/test'
import { test, expect } from '../support/fixtures'
import type { BrowserContext, Page } from '@playwright/test'
import { createRandomUser } from '../support/api'
import { login } from '../support/login'
import { createTable, createTextLineColumn, fillInValueTextLine, loadTable, openRowActionMenu } from '../support/commands'

const tableTitle = 'Mandatory test table'

async function setupMandatoryTable(page: Page) {
	await createTable(page, tableTitle)
	await createTextLineColumn(page, 'title', '', '', true)
	await createTextLineColumn(page, 'description', '', '', false)

	// create one row
	await page.locator('[data-cy="createRowBtn"]').click()
	await fillInValueTextLine(page, 'title', 'first row')
	await fillInValueTextLine(page, 'description', 'desc 1')
	await page.locator('[data-cy="createRowSaveButton"]').click()
}

test.describe('Mandatory Column Functionality', () => {
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
		await setupMandatoryTable(page)
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

	test('SelectedViewColumns - Mandatory Checkbox display', async () => {
		await loadTable(page, tableTitle)

		// create a new view
		await page.locator('[data-cy="customTableAction"] button').click()
		await page.locator('[data-cy="dataTableCreateViewBtn"]').filter({ hasText: 'Create view' }).waitFor({ state: 'visible' })
		await page.locator('[data-cy="dataTableCreateViewBtn"]').filter({ hasText: 'Create view' }).click({ force: true })
		const titleInput1 = page.locator('[data-cy="viewSettingsDialogTitleInput"]')
		await titleInput1.waitFor({ state: 'visible', timeout: 10000 })
		await titleInput1.fill('Mandatory test view')
		await expect(page.locator('[data-cy="viewSettingsDialog"]')).toBeVisible()

		const openColumnMenu = async (colTitle: string) => {
			const entry = page.locator('.column-entry', { hasText: colTitle })
			await entry.hover()
			await entry.locator('[data-cy="customColumnAction"] button').click({ force: true })
		}

		await openColumnMenu('title')
		await expect(page.locator('[data-cy="columnMandatoryCheckbox"]').filter({ hasText: 'Mandatory' }).first()).toBeVisible()
	})

	test('SelectedViewColumns - Checkboxes mutual exclusivity', async () => {
		await loadTable(page, tableTitle)

		// create a new view
		await page.locator('[data-cy="customTableAction"] button').click()
		await page.locator('[data-cy="dataTableCreateViewBtn"]').filter({ hasText: 'Create view' }).waitFor({ state: 'visible' })
		await page.locator('[data-cy="dataTableCreateViewBtn"]').filter({ hasText: 'Create view' }).click({ force: true })
		const titleInput2 = page.locator('[data-cy="viewSettingsDialogTitleInput"]')
		await titleInput2.waitFor({ state: 'visible', timeout: 10000 })
		await titleInput2.fill('Mandatory test view')

		const openColumnMenu = async (colTitle: string) => {
			const entry = page.locator('.column-entry', { hasText: colTitle })
			await entry.hover()
			await entry.locator('[data-cy="customColumnAction"] button').click({ force: true })
		}

		// should disable mandatory checkbox when readonly is enabled
		await openColumnMenu('title')
		await page.getByRole('menuitemcheckbox', { name: 'Read only' }).click()
		await expect(page.locator('[data-cy="columnReadonlyCheckbox"] input')).toBeChecked()
		await expect(page.locator('[data-cy="columnMandatoryCheckbox"] input')).toBeDisabled()

		// Uncheck readonly
		await page.getByRole('menuitemcheckbox', { name: 'Read only' }).click()

		// should disable readonly checkbox when mandatory is enabled
		await page.getByRole('menuitemcheckbox', { name: 'Mandatory' }).click()
		await expect(page.locator('[data-cy="columnMandatoryCheckbox"] input')).toBeChecked()
		await expect(page.locator('[data-cy="columnReadonlyCheckbox"] input')).toBeDisabled()
	})

	test('EditRow - Mandatory Field Validation', async () => {
		await loadTable(page, tableTitle)

		// Create a view with mandatory settings first
		await page.locator('[data-cy="customTableAction"] button').click()
		await page.locator('[data-cy="dataTableCreateViewBtn"]').filter({ hasText: 'Create view' }).waitFor({ state: 'visible' })
		await page.locator('[data-cy="dataTableCreateViewBtn"]').filter({ hasText: 'Create view' }).click({ force: true })
		const titleInput3 = page.locator('[data-cy="viewSettingsDialogTitleInput"]')
		await titleInput3.waitFor({ state: 'visible', timeout: 10000 })
		await titleInput3.fill('Mandatory validation test view')

		// Set title column as mandatory in the view
		await page.locator('.column-entry', { hasText: 'title' }).hover()
		await page.locator('.column-entry', { hasText: 'title' }).locator('[data-cy="customColumnAction"] button').click({ force: true })
		await page.getByRole('menuitemcheckbox', { name: 'Mandatory' }).click()

		// Save the view
		await page.locator('[data-cy="modifyViewBtn"]').click()
		await expect(page.locator('.icon-loading').first()).toBeHidden()

		// Now open edit row dialog
		const firstRow = page.locator('[data-cy="ncTable"] [data-cy="customTableRow"]').first()
		await openRowActionMenu(page, firstRow)
		await page.locator('[data-cy="editRowBtn"]').click()
		await expect(page.locator('[data-cy="editRowModal"]')).toBeVisible()

		// should show error when mandatory field is empty
		const input = page.locator('[data-cy="editRowModal"] input').first()
		await input.fill('')
		await input.blur()

		await expect(page.locator('[data-cy="editRowModal"]').locator('.notecard--error, .note-card--error, .error, [type="error"]').first()).toBeVisible({ timeout: 15000 })

		// Check that save button is disabled
		const saveBtn = page.locator('[data-cy="editRowSaveButton"]')
		await expect(saveBtn).toBeDisabled({ timeout: 5000 })

		// should enable save button when mandatory field is filled
		await input.fill('filled value')
		await expect(saveBtn).toBeEnabled({ timeout: 5000 })
	})
})
