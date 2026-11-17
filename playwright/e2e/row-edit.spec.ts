/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Page } from '@playwright/test'
import { test, expect } from '../support/fixtures'
import { createTable, createTextLineColumn, createView, loadTable, loadView, openRowActionMenu } from '../support/commands'

test('View row count in table overview updates after adding a row', async ({ userPage: { page } }) => {
	await page.goto('/index.php/apps/tables')
	await createTable(page, 'Overview count table')
	await loadTable(page, 'Overview count table')
	await createTextLineColumn(page, 'label', '', '', true)

	await createView(page, 'Overview count view')
	await loadTable(page, 'Overview count table')

	const viewRowsCell = page
		.locator('.table tr')
		.filter({ hasText: 'Overview count view' })
		.locator('td.number-column')
		.first()
	await expect(viewRowsCell).toHaveText('0')

	await page.locator('button').filter({ hasText: 'Create row' }).click()
	await page.locator('.modal__content input').first().fill('first row')
	await page.locator('button').filter({ hasText: 'Save' }).click()
	await expect(page.locator('.custom-table table tr td div').filter({ hasText: 'first row' }).first()).toBeVisible()

	await expect(viewRowsCell).toHaveText('1')
})

test.describe('Editing a row in a view does not reload the view', () => {
	async function setUpViewWithRow(page: Page, title: string) {
		await page.goto('/index.php/apps/tables')
		await createTable(page, title + ' table')
		await loadTable(page, title + ' table')
		await createTextLineColumn(page, 'label', '', '', true)
		await createView(page, title + ' view')
		await loadView(page, title + ' view')

		await page.locator('button').filter({ hasText: 'Create row' }).click()
		await page.locator('.modal__content input').first().fill('before')
		await page.locator('button').filter({ hasText: 'Save' }).click()
		const row = page.locator('.custom-table table tr').filter({ hasText: 'before' })
		await expect(row).toBeVisible()
		return row
	}

	function trackRowReloads(page: Page) {
		const rowReloads: string[] = []
		page.on('request', request => {
			if (request.method() === 'GET' && request.url().includes('/apps/tables/row/view/')) {
				rowReloads.push(request.url())
			}
		})
		return rowReloads
	}

	function waitForRowSave(page: Page) {
		return page.waitForResponse(r => r.url().includes('/rows/') && r.request().method() === 'PUT')
	}

	test('inline', async ({ userPage: { page } }) => {
		const row = await setUpViewWithRow(page, 'Inline no reload')
		const rowReloads = trackRowReloads(page)

		await row.getByText('before', { exact: true }).click()
		const cellInput = page.locator('.cell-input input').first()
		await cellInput.waitFor({ state: 'visible' })
		const rowSaved = waitForRowSave(page)
		await cellInput.clear()
		await cellInput.pressSequentially('after')
		await cellInput.press('Enter')
		await rowSaved

		await expect(page.locator('.custom-table table tr').filter({ hasText: 'after' })).toBeVisible()
		expect(rowReloads).toEqual([])
	})

	test('modal', async ({ userPage: { page } }) => {
		const row = await setUpViewWithRow(page, 'Modal no reload')
		await openRowActionMenu(page, row)
		await page.locator('[data-cy="editRowBtn"]').click()
		const modal = page.locator('[data-cy="editRowModal"]')
		await expect(modal).toBeVisible()
		const rowReloads = trackRowReloads(page)

		await modal.getByRole('textbox').first().fill('after')
		const rowSaved = waitForRowSave(page)
		await page.locator('[data-cy="editRowSaveButton"]').click()
		await rowSaved

		await expect(page.locator('.custom-table table tr').filter({ hasText: 'after' })).toBeVisible()
		expect(rowReloads).toEqual([])
	})
})
