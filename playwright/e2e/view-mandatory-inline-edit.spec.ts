/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test as base } from '@playwright/test'
import { test, expect } from '../support/fixtures'
import type { BrowserContext, Page } from '@playwright/test'
import { createRandomUser } from '../support/api'
import { login } from '../support/login'
import { createTable, createTextLineColumn, fillInValueTextLine, loadTable, loadView, openCreateRowModal } from '../support/commands'

const tableTitle = 'Mandatory inline edit table'
const viewTitle = 'Mandatory inline edit view'

async function createRow(page: Page, title: string, description: string) {
	await openCreateRowModal(page)
	if (title) {
		await fillInValueTextLine(page, 'title', title)
	}
	await fillInValueTextLine(page, 'description', description)
	await page.locator('[data-cy="createRowSaveButton"]').click()
	await expect(page.locator('[data-cy="createRowModal"]')).toBeHidden()
}

async function editCellInline(page: Page, row: ReturnType<Page['locator']>, currentValue: string, newValue: string) {
	await row.getByText(currentValue, { exact: true }).click()
	const cellInput = page.locator('.cell-input input').first()
	await cellInput.waitFor({ state: 'visible' })
	const updateResponse = page.waitForResponse(r => r.url().includes('/rows/') && r.request().method() === 'PUT')
	await cellInput.clear()
	if (newValue) {
		await cellInput.pressSequentially(newValue)
	}
	await cellInput.press('Enter')
	return await updateResponse
}

test.describe('Inline edit with a mandatory column in the view', () => {
	test.describe.configure({ mode: 'serial' })

	let context: BrowserContext
	let page: Page

	// @ts-expect-error - Playwright complex types mismatch in this environment
	base.beforeAll(async ({ browser, baseURL }) => {
		context = await browser.newContext({ baseURL })
		page = await context.newPage()

		const user = await createRandomUser(page.request)
		await login(page, user)

		await page.goto('/index.php/apps/tables')
		await createTable(page, tableTitle)
		await createTextLineColumn(page, 'title', '', '', true)
		await createTextLineColumn(page, 'description', '', '', false)

		// The mandatory state is set on the view only, so rows can be created without it
		await createRow(page, '', 'untouched title')
		await createRow(page, 'keep me', 'filled title')

		// Create a view that marks "title" mandatory
		await page.locator('[data-cy="customTableAction"] button').click()
		const createViewBtn = page.locator('[data-cy="dataTableCreateViewBtn"]').filter({ hasText: 'Create view' })
		await createViewBtn.waitFor({ state: 'visible' })
		await createViewBtn.click({ force: true })
		const titleInput = page.locator('[data-cy="viewSettingsDialogTitleInput"]')
		await titleInput.waitFor({ state: 'visible', timeout: 10000 })
		await titleInput.fill(viewTitle)

		const titleEntry = page.locator('.column-entry', { hasText: 'title' })
		await titleEntry.hover()
		await titleEntry.locator('[data-cy="customColumnAction"] button').click({ force: true })
		await page.getByRole('menuitemcheckbox', { name: 'Mandatory' }).click()
		await expect(page.locator('[data-cy="columnMandatoryCheckbox"] input')).toBeChecked()

		await page.locator('[data-cy="modifyViewBtn"]').click()
		await expect(page.locator('[data-cy="viewSettingsDialog"]')).toBeHidden()
	}, 120000)

	test.afterAll(async () => {
		await context?.close()
	})

	test.beforeEach(async () => {
		await page.goto('/index.php/apps/tables')
		await loadTable(page, tableTitle)
		await loadView(page, viewTitle)
	})

	test('saves another cell while the mandatory cell is empty', async () => {
		const row = page.locator('[data-cy="ncTable"] [data-cy="customTableRow"]').filter({ hasText: 'untouched title' }).first()
		await expect(row).toBeVisible()

		const response = await editCellInline(page, row, 'untouched title', 'edited anyway')
		expect(response.status()).toBe(200)
		await expect(page.locator('[data-cy="ncTable"] table').filter({ hasText: 'edited anyway' }).first()).toBeVisible()
	})

	test('still refuses to clear the mandatory cell itself', async () => {
		const row = page.locator('[data-cy="ncTable"] [data-cy="customTableRow"]').filter({ hasText: 'filled title' }).first()
		await expect(row).toBeVisible()

		const response = await editCellInline(page, row, 'keep me', '')
		expect(response.status()).toBe(400)

		await page.reload()
		await expect(page.locator('[data-cy="ncTable"] table').filter({ hasText: 'keep me' }).first()).toBeVisible()
	})
})
