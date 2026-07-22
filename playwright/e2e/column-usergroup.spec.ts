/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test, expect } from '../support/fixtures'
import { createRandomUser } from '../support/api'
import { createTable, createUsergroupColumn, loadTable, openRowActionMenu } from '../support/commands'

const columnTitle = 'usergroup'
const tableTitlePrefix = 'Test usergroup'

const saveEditRow = async (page) => {
	const editRowReqPromise = page.waitForResponse(r => r.url().includes('/apps/tables/row/') && r.request().method() === 'PUT')
	await page.locator('[data-cy="editRowSaveButton"]').click()
	const editRowResponse = await editRowReqPromise
	expect(editRowResponse.ok()).toBeTruthy()
	await expect(page.locator('[data-cy="editRowModal"]')).toBeHidden()
}

test.describe('Test column ' + columnTitle, () => {

	test('Create column and rows with default values', async ({ userPage: { page, user }, request }) => {
		const nonLocalUser = await createRandomUser(request)
		const tableTitle = `${tableTitlePrefix} default`

		await page.goto('/index.php/apps/tables')
		await createTable(page, tableTitle)
		await loadTable(page, tableTitle)
		await createUsergroupColumn(page, columnTitle, true, true, true, true, [user.userId, nonLocalUser.userId], true)

		await page.locator('button').filter({ hasText: 'Create row' }).click()
		await page.locator('[data-cy="createRowSaveButton"]').waitFor({ state: 'visible' })
		await page.locator('[data-cy="createRowSaveButton"]').click()

		await expect(page.locator('[data-cy="ncTable"] table tr td .user-bubble__name').filter({ hasText: user.userId }).first()).toBeVisible()
		await expect(page.locator('[data-cy="ncTable"] table tr td .user-bubble__name').filter({ hasText: nonLocalUser.userId }).first()).toBeVisible()
	})

	test('Create column and rows without default values', async ({ userPage: { page }, request }) => {
		const nonLocalUser = await createRandomUser(request)
		const tableTitle = `${tableTitlePrefix} nodefault`

		await page.goto('/index.php/apps/tables')
		await createTable(page, tableTitle)
		await loadTable(page, tableTitle)
		await createUsergroupColumn(page, columnTitle, true, false, false, false, [], true)

		await page.locator('button').filter({ hasText: 'Create row' }).click()
		await page.locator('[data-cy="usergroupRowSelect"] input').pressSequentially(nonLocalUser.userId)
		await page.locator('.vs__dropdown-menu li').filter({ hasText: nonLocalUser.userId }).first().click()
		await page.locator('[data-cy="createRowSaveButton"]').click()

		await expect(page.locator('[data-cy="ncTable"] table tr td .user-bubble__name').filter({ hasText: nonLocalUser.userId }).first()).toBeVisible()
	})

	test('Create and edit rows', async ({ userPage: { page, user }, request }) => {
		const nonLocalUser = await createRandomUser(request)
		const tableTitle = `${tableTitlePrefix} edit`

		await page.goto('/index.php/apps/tables')
		await createTable(page, tableTitle)
		await loadTable(page, tableTitle)
		await createUsergroupColumn(page, columnTitle, true, true, true, true, [user.userId], true)

		await page.locator('button').filter({ hasText: 'Create row' }).click()
		await page.locator('[data-cy="createRowSaveButton"]').click()
		await expect(page.locator('[data-cy="ncTable"] table tr td .user-bubble__name').filter({ hasText: user.userId }).first()).toBeVisible()

		const firstRow = page.locator('[data-cy="ncTable"] [data-cy="customTableRow"]').first()
		await openRowActionMenu(page, firstRow)
		await page.locator('[data-cy="editRowBtn"]').click()
		await expect(page.locator('[data-cy="editRowModal"]')).toBeVisible()

		const usergroupSelect = page.locator('[data-cy="editRowModal"] [data-cy="usergroupRowSelect"]')
		await expect(usergroupSelect.locator('.vs__selected').filter({ hasText: user.userId }).first()).toBeVisible()

		await usergroupSelect.locator('input').pressSequentially(nonLocalUser.userId)
		await page.locator('.vs__dropdown-menu li').filter({ hasText: nonLocalUser.userId }).first().click()
		await expect(usergroupSelect.locator('.vs__selected').filter({ hasText: nonLocalUser.userId }).first()).toBeVisible()

		const localUserSelection = usergroupSelect.locator('.vs__selected').filter({ hasText: user.userId }).first()
		await localUserSelection.locator('.vs__deselect').click({ force: true })
		await expect(usergroupSelect.locator('.vs__selected').filter({ hasText: user.userId })).toBeHidden()

		await saveEditRow(page)

		await expect(page.locator('[data-cy="ncTable"] table tr td .user-bubble__name', { hasText: user.userId })).toBeHidden()
		await expect(page.locator('[data-cy="ncTable"] table tr td .user-bubble__name').filter({ hasText: nonLocalUser.userId }).first()).toBeVisible()
	})
})
