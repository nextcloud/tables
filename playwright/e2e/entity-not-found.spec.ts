/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test, expect } from '../support/fixtures'

test.describe('Entity not found error handling', () => {

	test('Shows error message when table is not found', async ({ userPage: { page } }) => {
		await page.route('**/tables/999', route => route.fulfill({ status: 404 }))
		await page.goto('/index.php/apps/tables/#/table/999')

		await expect(page.locator('.error-container')).toContainText('This table could not be found', { timeout: 10000 })
	})

	test('Shows error message when view is not found', async ({ userPage: { page } }) => {
		await page.route('**/views/999', route => route.fulfill({ status: 404 }))
		await page.goto('/index.php/apps/tables/#/view/999')

		await expect(page.locator('.error-container')).toContainText('This view could not be found', { timeout: 10000 })
	})

	test('Shows error message when application is not found', async ({ userPage: { page } }) => {
		await page.route('**/contexts/999*', route => route.fulfill({ status: 404 }))
		await page.goto('/index.php/apps/tables/#/application/999')

		await expect(page.locator('.error-container')).toContainText('This application could not be found', { timeout: 10000 })
	})
})
