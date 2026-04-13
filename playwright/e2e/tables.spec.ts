/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test, expect } from '../support/fixtures'

test.describe('The Home Page', () => {

	test('successfully loads', async ({ userPage: { page } }) => {
		await page.goto('/index.php/apps/tables')

		await expect(page.locator('.empty-content').filter({ hasText: 'Manage data the way you need it.' })).toBeVisible()
		await expect(page.locator('.empty-content__action button').filter({ hasText: 'Create new table' })).toBeVisible()
	})
})
