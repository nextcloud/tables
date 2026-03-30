/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { type Page } from '@playwright/test'
import { test, expect } from '../support/fixtures'
import { createRandomUser } from '../support/api'
import {
	createContext,
	createTable,
	createTextLineColumn,
	createView,
	fillInValueTextLine,
	loadContext,
	loadTable,
	openContextEditModal,
} from '../support/commands'
import { login } from '../support/login'

const viewTitle = 'test view'

async function setupContextAndNavigate(page: Page, contextTitle: string) {
	await page.goto('/index.php/apps/tables')
	await createContext(page, contextTitle)
	await loadContext(page, contextTitle)
}

async function selectFromVueDropdown(page: Page, inputSelector: string, value: string) {
	const input = page.locator(inputSelector).first()
	await input.click()
	await input.clear()
	await input.fill(value)

	const optionByRole = page.getByRole('option', { name: new RegExp(value, 'i') }).first()
	if (await optionByRole.isVisible({ timeout: 5000 }).catch(() => false)) {
		await optionByRole.click()
		return
	}

	const optionById = page.locator(`.vs__dropdown-menu [id="${value}"]`).first()
	if (await optionById.isVisible({ timeout: 3000 }).catch(() => false)) {
		await optionById.click()
		return
	}

	const optionByText = page.locator('.vs__dropdown-menu li').filter({ hasText: value }).first()
	await optionByText.waitFor({ state: 'visible', timeout: 5000 })
	await optionByText.click()
}

async function expectSelectedShare(page: Page, userId: string) {
	await expect(
		page.locator('[data-cy="contextResourceShare"] .vs__selected').filter({ hasText: userId }).first(),
	).toBeVisible()
}

test.describe('Manage a context', () => {

	test('Update and add resources', async ({ userPage: { page } }) => {
		const contextTitle = 'test application update'
		const tableTitle = 'test table update'

		await setupContextAndNavigate(page, contextTitle)
		await createTable(page, tableTitle)
		await loadTable(page, tableTitle)
		await createTextLineColumn(page, 'title', '', '', true)
		await page.getByRole('button', { name: 'Create row' }).click()
		await fillInValueTextLine(page, 'title', 'first row')
		await page.locator('[data-cy="createRowSaveButton"]').click()
		await createView(page, viewTitle)

		await openContextEditModal(page, contextTitle)
		await page.locator('[data-cy="editContextTitle"]').clear()
		await page.locator('[data-cy="editContextTitle"]').fill(`updated ${contextTitle}`)

		await selectFromVueDropdown(page, '[data-cy="contextResourceForm"] input', tableTitle)

		await expect(page.locator('[data-cy="contextResourceList"]')).toContainText(tableTitle)
		await expect(page.locator('[data-cy="contextResourcePerms"]')).toContainText(tableTitle)

		await selectFromVueDropdown(page, '[data-cy="contextResourceForm"] input', viewTitle)

		await expect(page.locator('[data-cy="contextResourceList"]')).toContainText(viewTitle)
		await expect(page.locator('[data-cy="contextResourcePerms"]')).toContainText(viewTitle)

		const updateContextReq = page.waitForResponse(r => r.url().includes('/apps/tables/') && r.request().method() === 'PUT')
		await page.locator('[data-cy="editContextSubmitBtn"]').click()
		await updateContextReq
		await expect(page.getByRole('dialog', { name: 'Edit application' })).toBeHidden({ timeout: 10000 })

		await expect(page.locator('[data-cy="navigationContextItem"]').filter({ hasText: `updated ${contextTitle}` })).toBeVisible()
		await loadContext(page, `updated ${contextTitle}`)

		await expect(page.locator('h1', { hasText: `updated ${contextTitle}` })).toBeVisible()
		await expect(page.locator('h1', { hasText: tableTitle })).toBeVisible()
		await expect(page.locator('h1', { hasText: viewTitle })).toBeVisible()
	})

	test('Share context with resources', async ({ userPage: { page }, request }) => {
		const nonLocalUser = await createRandomUser(request)
		const contextTitle = 'test application share'
		const tableTitle = 'test table share'

		await setupContextAndNavigate(page, contextTitle)
		await createTable(page, tableTitle)
		await openContextEditModal(page, contextTitle)

		await selectFromVueDropdown(page, '[data-cy="contextResourceForm"] input', tableTitle)

		await selectFromVueDropdown(page, '[data-cy="contextResourceShare"] input', nonLocalUser.userId)

		await expectSelectedShare(page, nonLocalUser.userId)

		const updateResponse = page.waitForResponse(r => r.url().includes('/apps/tables/') && r.request().method() === 'PUT')
		await page.locator('[data-cy="editContextSubmitBtn"]').click()
		await updateResponse
		await expect(page.locator('.toastify.toast-success').first()).toBeVisible({ timeout: 10000 })

		await loadContext(page, contextTitle)
		await expect(page.locator('h1', { hasText: contextTitle })).toBeVisible({ timeout: 10000 })
		await expect(page.locator('h1', { hasText: tableTitle })).toBeVisible({ timeout: 10000 })

		// verify context was shared properly
		await page.context().clearCookies()
		await login(page, nonLocalUser)
		await page.goto('/index.php/apps/tables')

		await loadContext(page, contextTitle)
		await expect(page.locator('h1', { hasText: contextTitle })).toBeVisible()
		await expect(page.locator('h1', { hasText: tableTitle })).toBeVisible()
	})

	test('Transfer context', async ({ userPage: { page }, request }) => {
		const nonLocalUser = await createRandomUser(request)
		const contextTitle = 'test application transfer'
		const tableTitle = 'test table transfer'

		await setupContextAndNavigate(page, contextTitle)
		await createTable(page, tableTitle)
		await openContextEditModal(page, contextTitle)

		await page.locator('[data-cy="transferContextSubmitBtn"]').click()
		await expect(page.locator('[data-cy="transferContextModal"]')).toBeVisible()

		await selectFromVueDropdown(page, '[data-cy="transferContextModal"] input', nonLocalUser.userId)
		await page.locator('[data-cy="transferContextButton"]').click()

		// verify that context was properly transferred
		await page.context().clearCookies()
		await login(page, nonLocalUser)
		await page.goto('/index.php/apps/tables')

		await loadContext(page, contextTitle)
		await expect(page.locator('h1', { hasText: contextTitle })).toBeVisible()
	})

	test('Delete context with shares', async ({ userPage: { page }, request }) => {
		const nonLocalUser = await createRandomUser(request)
		const contextTitle = 'test application delete'

		await setupContextAndNavigate(page, contextTitle)
		await openContextEditModal(page, contextTitle)

		await selectFromVueDropdown(page, '[data-cy="contextResourceShare"] input', nonLocalUser.userId)

		await expectSelectedShare(page, nonLocalUser.userId)

		const updateDeleteResponse = page.waitForResponse(r => r.url().includes('/apps/tables/') && r.request().method() === 'PUT')
		await page.locator('[data-cy="editContextSubmitBtn"]').click()
		await updateDeleteResponse
		await expect(page.locator('.toastify.toast-success').first()).toBeVisible({ timeout: 10000 })

		// verify that context was deleted from current user
		const contextNavItem = page.locator('[data-cy="navigationContextItem"]').filter({ hasText: contextTitle }).first()
		await contextNavItem.hover()
		await contextNavItem.getByRole('button', { name: /Actions|Open menu/i }).first().click({ force: true })
		await page.locator('[data-cy="navigationContextDeleteBtn"]').filter({ hasText: 'Delete application' }).waitFor({ state: 'visible', timeout: 5000 })

		await page.locator('[data-cy="navigationContextDeleteBtn"]').filter({ hasText: 'Delete application' }).click({ force: true })
		await expect(page.locator('[data-cy="deleteContextModal"]')).toBeVisible()

		const deleteResponse = page.waitForResponse(r => r.url().includes('/apps/tables/') && r.request().method() === 'DELETE')
		await page.locator('[data-cy="deleteContextModal"] button').filter({ hasText: 'Delete' }).click()
		await deleteResponse

		await expect(page.locator('li', { hasText: contextTitle })).toBeHidden({ timeout: 10000 })
		await expect(page.locator('h1', { hasText: contextTitle })).toBeHidden()

		// verify that context was deleted from shared user
		await page.context().clearCookies()
		await login(page, nonLocalUser)
		await page.goto('/index.php/apps/tables')
		await expect(page.locator('li', { hasText: contextTitle })).toBeHidden()
	})

	test('Remove context resource', async ({ userPage: { page } }) => {
		const contextTitle = 'test application resource'
		const tableTitle = 'test table resource'

		await setupContextAndNavigate(page, contextTitle)
		await createTable(page, tableTitle)
		await loadContext(page, contextTitle)
		await openContextEditModal(page, contextTitle)

		await selectFromVueDropdown(page, '[data-cy="contextResourceForm"] input', tableTitle)

		await expect(page.locator('[data-cy="contextResourceList"]')).toContainText(tableTitle)
		await expect(page.locator('[data-cy="contextResourcePerms"]')).toContainText(tableTitle)
		await page.locator('[data-cy="editContextSubmitBtn"]').click()

		await expect(page.locator('h1', { hasText: contextTitle })).toBeVisible()
		await expect(page.locator('h1', { hasText: tableTitle })).toBeVisible()

		await openContextEditModal(page, contextTitle)
		await page.locator('[data-cy="contextResourceList"] button').filter({ hasText: 'Delete' }).click({ force: true })

		await expect(page.locator('[data-cy="contextResourceList"]', { hasText: tableTitle })).toBeHidden()
		await page.locator('[data-cy="editContextSubmitBtn"]').click()

		await expect(page.locator('h1', { hasText: contextTitle })).toBeVisible()
		await expect(page.locator('h1', { hasText: tableTitle })).toBeHidden()
	})

	test('Modify resource rows and columns from context', async ({ userPage: { page } }) => {
		const contextTitle = 'test application modify'
		const tableTitle = 'test table modify'

		await setupContextAndNavigate(page, contextTitle)
		await createTable(page, tableTitle)
		await loadContext(page, contextTitle)
		await expect(page.locator('h1', { hasText: contextTitle })).toBeVisible()

		await openContextEditModal(page, contextTitle)
		await selectFromVueDropdown(page, '[data-cy="contextResourceForm"] input', tableTitle)

		await expect(page.locator('[data-cy="contextResourceList"]')).toContainText(tableTitle)
		await expect(page.locator('[data-cy="contextResourcePerms"]')).toContainText(tableTitle)
		await page.locator('[data-cy="editContextSubmitBtn"]').click()

		await createTextLineColumn(page, 'title', '', '', true)
		await page.locator('button').filter({ hasText: 'Create row' }).click()
		await fillInValueTextLine(page, 'title', 'first row')
		await page.locator('[data-cy="createRowSaveButton"]').click()

		await expect(page.locator('[data-cy="ncTable"] table').filter({ hasText: 'first row' })).toBeVisible()
	})

	test('Modify context resources and their permissions', async ({ userPage: { page }, request }) => {
		const nonLocalUser = await createRandomUser(request)
		const contextTitle = 'test application perms'
		const tableTitle = 'test table perms'

		await setupContextAndNavigate(page, contextTitle)
		await createTable(page, tableTitle)
		await loadTable(page, tableTitle)
		await createTextLineColumn(page, 'title', '', '', true)

		await loadContext(page, contextTitle)
		await expect(page.locator('h1', { hasText: contextTitle })).toBeVisible()

		await openContextEditModal(page, contextTitle)
		await selectFromVueDropdown(page, '[data-cy="contextResourceForm"] input', tableTitle)

		await expect(page.locator('[data-cy="contextResourceList"]')).toContainText(tableTitle)
		await expect(page.locator('[data-cy="contextResourcePerms"]')).toContainText(tableTitle)

		// give delete permission for resource
		await page.locator('[data-cy="resourceSharePermsActions"] button').click()
		await page.locator('li .action-checkbox').filter({ hasText: 'Delete resource' }).click()
		await expect(page.locator('li [aria-checked="true"]').filter({ hasText: 'Delete resource' })).toBeVisible()
		// close the permissions popover before interacting with the share input
		await page.keyboard.press('Escape')
		await page.waitForTimeout(250)

		await selectFromVueDropdown(page, '[data-cy="contextResourceShare"] input', nonLocalUser.userId)

		await expectSelectedShare(page, nonLocalUser.userId)

		const updatePermsResponse = page.waitForResponse(r => r.url().includes('/apps/tables/') && r.request().method() === 'PUT')
		await page.locator('[data-cy="editContextSubmitBtn"]').click()
		await updatePermsResponse

		await expect(page.locator('h1', { hasText: contextTitle })).toBeVisible({ timeout: 10000 })
		await expect(page.locator('h1', { hasText: tableTitle })).toBeVisible({ timeout: 10000 })

		// verify that shared user can modify and delete data in the resource
		await page.context().clearCookies()
		await login(page, nonLocalUser)
		await page.goto('/index.php/apps/tables')

		await loadContext(page, contextTitle)
		await expect(page.locator('h1', { hasText: contextTitle })).toBeVisible()
		await expect(page.locator('h1', { hasText: tableTitle })).toBeVisible()

		await page.locator('button').filter({ hasText: 'Create row' }).click()
		await fillInValueTextLine(page, 'title', 'first row')
		await page.locator('[data-cy="createRowSaveButton"]').click()

		await expect(page.locator('[data-cy="ncTable"] table').filter({ hasText: 'first row' })).toBeVisible()

		await page.locator('[data-cy="ncTable"] [data-cy="customTableRow"]').filter({ hasText: 'first row' }).locator('[data-cy="editRowBtn"]').click()
		await page.locator('[data-cy="editRowDeleteButton"]').click()
		await page.locator('[data-cy="editRowDeleteConfirmButton"]').click()

		await expect(page.locator('[data-cy="ncTable"] table', { hasText: 'first row' })).toBeHidden()
	})
})
