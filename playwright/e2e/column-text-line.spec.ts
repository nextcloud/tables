/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test, expect } from '../support/fixtures'
import { createTable, createTextLineColumn, loadTable } from '../support/commands'

test.describe('Test column text line', () => {

	test('Manage text line column', async ({ userPage: { page } }) => {
		await page.goto('/index.php/apps/tables')
		await createTable(page, 'Test text line column')
		await loadTable(page, 'Test text line column')

		await createTextLineColumn(page, 'text line', 'test', '12', true)

		// check if default value is set on row creation
		await page.locator('button').filter({ hasText: 'Create row' }).click()
		await expect(page.locator('.modal-container__content h2').filter({ hasText: 'Create row' }).first()).toBeVisible()
		await expect(page.locator('.modal__content .title').filter({ hasText: 'text line' }).first()).toBeVisible()

		const input = page.locator('.modal__content input').first()
		await expect(input).toBeVisible()
		await input.clear()
		await input.fill('hello world')
		await page.locator('button').filter({ hasText: 'Save' }).click()
		await expect(page.locator('.custom-table table tr td div').filter({ hasText: 'hello world' }).first()).toBeVisible()

		// check if max length is respected
		await page.locator('button').filter({ hasText: 'Create row' }).click()
		const input2 = page.locator('.modal__content input').first()
		await input2.clear()
		// In HTML max length should truncate it, let's type and let the browser truncate if it's an attribute
		await input2.pressSequentially('hello world is a typical first phrase to insert')
		await page.locator('button').filter({ hasText: 'Save' }).click()

		await expect(page.locator('.custom-table table tr td div').filter({ hasText: 'hello world' }).first()).toBeVisible()
		await expect(page.locator('.custom-table table tr td div', { hasText: 'phrase' })).toBeHidden()
	})
})
