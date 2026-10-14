/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { APIRequestContext } from '@playwright/test'
import { test, expect } from '../support/fixtures'
import { createRandomUser, ocsRequest, type TestUser } from '../support/api'
import {
	createTable,
	createTextLineColumn,
	createView,
	ensureNavigationOpen,
	getNavigationNodeId,
	loadTableListingLast,
} from '../support/commands'
import { login } from '../support/login'

async function deleteTable(request: APIRequestContext, owner: TestUser, tableId: number) {
	await ocsRequest(request, owner, {
		method: 'DELETE',
		url: `/ocs/v2.php/apps/tables/api/2/tables/${tableId}?format=json`,
	})
}

async function shareWithUser(
	request: APIRequestContext,
	owner: TestUser,
	nodeType: 'view',
	nodeId: number,
	receiver: TestUser,
) {
	await ocsRequest(request, owner, {
		method: 'POST',
		// the api/1 routes are plain app routes, not OCS ones
		url: '/index.php/apps/tables/api/1/shares',
		data: {
			nodeId,
			nodeType,
			receiver: receiver.userId,
			receiverType: 'user',
			permissionRead: true,
		},
	})
}

test.describe('Navigation with a slow table listing', () => {
	const cleanUpTasks: Array<() => Promise<unknown>> = []

	test.afterEach(async () => {
		// keep the test server reusable, the tests run against a shared instance
		// (the users stay, the fixture owns their lifecycle)
		while (cleanUpTasks.length > 0) {
			await cleanUpTasks.pop()?.()
		}
	})

	test('shows a view shared with me', async ({ userPage: { page, user: owner }, request }) => {
		const recipient = await createRandomUser(request)
		const tableTitle = 'Table of ' + owner.userId
		const viewTitle = 'Shared view'

		await page.goto('/index.php/apps/tables')
		await createTable(page, tableTitle)
		// the table actions, and with them the create view action, need a column to show up
		await createTextLineColumn(page, 'What', '', '', true)
		await createView(page, viewTitle)
		// deleting the table takes its views and shares with it
		const tableId = await getNavigationNodeId(page, 'table', tableTitle)
		cleanUpTasks.push(() => deleteTable(request, owner, tableId))
		const viewId = await getNavigationNodeId(page, 'view', viewTitle)
		await shareWithUser(request, owner, 'view', viewId, recipient)

		await page.context().clearCookies()
		await login(page, recipient)

		// the shared views answer before the table listing, which used to drop them
		const { answered: sharedViewsAnswered } = await loadTableListingLast(page, '/apps/tables/view')
		await page.goto('/index.php/apps/tables')
		await sharedViewsAnswered
		await ensureNavigationOpen(page)

		await expect(
			page.locator(`[data-cy="navigationViewItem"] a[title="${viewTitle}"]`),
		).toBeVisible()
	})

})
