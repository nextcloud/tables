/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test, expect } from '../support/fixtures'
import { createContext, ensureNavigationOpen } from '../support/commands'

function appMenuEntry(page: import('@playwright/test').Page, contextTitle: string) {
	return page
		.locator('nav[aria-label="Applications menu"]')
		.locator(`[title="${contextTitle}"]`)
}

test.describe('Test context navigation', () => {
	test('Create context that is hidden in nav by default', async ({
		userPage: { page },
	}) => {
		const contextTitle = 'test application hidden'
		await page.goto('/index.php/apps/tables')
		await createContext(page, contextTitle, false)
		await page.reload({ waitUntil: 'domcontentloaded' })
		await ensureNavigationOpen(page)
		await expect(appMenuEntry(page, contextTitle)).toBeHidden()

		const contextButton = page
			.locator('[data-cy="navigationContextItem"]')
			.filter({ hasText: contextTitle })
			.first()
			.getByRole('button', { name: /Actions|Open menu/i })
			.first()
		await contextButton.waitFor({ state: 'visible', timeout: 5000 })
		await contextButton.scrollIntoViewIfNeeded()
		await page
			.locator('[data-cy="navigationContextItem"]')
			.filter({ hasText: contextTitle })
			.first()
			.hover()
		await contextButton.click({ force: true })
		await page
			.locator('[data-cy="navigationContextShowInNavSwitch"] input')
			.waitFor({ state: 'attached' })
		await expect(
			page.locator('[data-cy="navigationContextShowInNavSwitch"] input'),
		).not.toBeChecked()

		await page.getByRole('menuitemcheckbox', { name: 'Show in app list' }).click()
		await expect(
			page.locator('[data-cy="navigationContextShowInNavSwitch"] input'),
		).toBeChecked()

		await expect(appMenuEntry(page, contextTitle)).toBeVisible()
	})
})
