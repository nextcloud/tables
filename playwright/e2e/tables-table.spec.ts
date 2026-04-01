/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test, expect } from '../support/fixtures'
import { createRandomUser } from '../support/api'
import { createTable, deleteTable, loadTable } from '../support/commands'
import { login } from '../support/login'

test.describe('Manage a table', () => {

	test('Create', async ({ userPage: { page } }) => {
		await page.goto('/index.php/apps/tables')
		await expect(page.locator('.icon-loading').first()).toBeHidden()
		await page.locator('[data-cy="navigationCreateTableIcon"]').click({ force: true })

		const createModal = page.locator('.modal__content')
		await createModal.locator('input[type="text"]').clear()
		await createModal.locator('input[type="text"]').fill('to do list')
		await page.locator('.tile').filter({ hasText: 'ToDo' }).first().click({ force: true })
		await page.locator('#description-editor .tiptap.ProseMirror').waitFor({ state: 'visible' })
		await page.locator('#description-editor .tiptap.ProseMirror').click()
		await page.locator('#description-editor .tiptap.ProseMirror').fill('to Do List description')

		await page.locator('[data-cy="createTableSubmitBtn"]').scrollIntoViewIfNeeded()
		await page.locator('[data-cy="createTableSubmitBtn"]').click()

		await expect(page.getByRole('button', { name: 'Create row' })).toBeVisible()
		await expect(page.locator('h1', { hasText: 'to do list' }).first()).toBeVisible()
		await expect(page.locator('table th', { hasText: 'Task' }).first()).toBeVisible()
		await expect(page.locator('.text-editor__content p').filter({ hasText: 'to Do List description' })).toBeVisible()
	})

	test('Update description', async ({ userPage: { page } }) => {
		await page.goto('/index.php/apps/tables')
		await createTable(page, 'to do list update desc')
		await loadTable(page, 'to do list update desc')
		await expect(page.locator('.icon-loading').first()).toBeHidden()
		const tableItem = page.locator('[data-cy="navigationTableItem"]').filter({ hasText: 'to do list update desc' }).first()
		await tableItem.getByRole('button', { name: /Actions|Open menu/i }).click({ force: true })
		await page.getByRole('menuitem', { name: 'Edit table' }).click()

		await expect(page.locator('[data-cy="editTableModal"]')).toBeVisible()
		await page.locator('.modal__content #description-editor .tiptap.ProseMirror').fill('Updated ToDo List description')
		await expect(page.locator('[data-cy="editTableSaveBtn"]')).toBeEnabled()
		await page.locator('[data-cy="editTableSaveBtn"]').click()

		await page.waitForTimeout(10)
		await expect(page.locator('.toastify.toast-success').first()).toBeVisible()
		await expect(page.locator('.text-editor__content p').filter({ hasText: 'Updated ToDo List description' })).toBeVisible()
	})

	test('Delete', async ({ userPage: { page } }) => {
		await page.goto('/index.php/apps/tables')
		await createTable(page, 'New list')
		await loadTable(page, 'New list')

		await page.locator('.button-vue__text').filter({ hasText: 'Create column' }).click({ force: true })
		await page.locator('[data-cy="columnTypeFormInput"]').clear()
		await page.locator('[data-cy="columnTypeFormInput"]').fill('text line')
		await page.locator('[data-cy="TextLineForm"] input').first().fill('test')
		await page.locator('[data-cy="TextLineForm"] input').nth(1).fill('12')
		await page.locator('.modal-container button').filter({ hasText: 'Save' }).click()
		await page.waitForTimeout(10)

		await page.locator('[data-cy="createRowBtn"]').click()
		await expect(page.locator('[data-cy="createRowModal"] input').first()).toBeVisible()
		await page.locator('[data-cy="createRowModal"] input').first().clear()
		await page.locator('[data-cy="createRowModal"] input').first().fill('hello world')
		await page.locator('[data-cy="createRowSaveButton"]').click()

		await deleteTable(page, 'New list')
	})

	test('Transfer', async ({ userPage: { page }, request }) => {
		const targetUserTransfer = await createRandomUser(request)
		await page.goto('/index.php/apps/tables')
		await expect(page.locator('.icon-loading').first()).toBeHidden()
		await page.locator('[data-cy="navigationCreateTableIcon"]').click({ force: true })

		const createModal = page.locator('[data-cy="createTableModal"]')
		await createModal.locator('input[type="text"]').clear()
		await createModal.locator('input[type="text"]').fill('test table')
		await page.locator('.tile').filter({ hasText: 'ToDo' }).click({ force: true })
		await page.locator('[data-cy="createTableSubmitBtn"]').click()

		await loadTable(page, 'test table')
		await page.locator('[data-cy="customTableAction"] button').click()
		await page.locator('[data-cy="dataTableEditTableBtn"]').click()

		await expect(page.locator('[data-cy="editTableModal"]')).toBeVisible()
		await page.locator('[data-cy="editTableModal"] button').filter({ hasText: 'Change owner' }).click()
		await expect(page.locator('[data-cy="editTableModal"]')).toBeHidden()
		await expect(page.locator('[data-cy="transferTableModal"]')).toBeVisible()

		await page.locator('[data-cy="transferTableModal"] input[type="search"]').clear()
		await page.locator('[data-cy="transferTableModal"] input[type="search"]').fill(targetUserTransfer.userId)
		await page.locator(`.vs__dropdown-menu [id="${targetUserTransfer.userId}"]`).click()

		await expect(page.locator('[data-cy="transferTableButton"]')).toBeEnabled()
		await page.locator('[data-cy="transferTableButton"]').click()

		await expect(page.locator('.toastify.toast-success').first()).toBeVisible()
		await expect(page.locator('.app-navigation__list').filter({ hasText: 'test table' })).toBeHidden()

		// login as other user
		await page.context().clearCookies()
		await login(page, targetUserTransfer)
		await page.goto('/index.php/apps/tables')
		await expect(page.locator('.app-navigation__list').filter({ hasText: 'test table' })).toBeVisible()
	})
})
