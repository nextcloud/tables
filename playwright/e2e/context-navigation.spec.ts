/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test, expect } from '../support/fixtures'
import type { Page } from '@playwright/test'
import { createContext, ensureNavigationOpen } from '../support/commands'

// Returns the locator for an app menu entry, opening the NC34 waffle popover first if needed.
async function getAppMenuEntry(page: Page, contextTitle: string) {
	const waffleBtn = page.locator('button.app-menu__waffle')
	if (await waffleBtn.isVisible().catch(() => false)) {
		// NC34+: app entries live inside a popover opened by the waffle button
		const isExpanded = await waffleBtn.getAttribute('aria-expanded').catch(() => null)
		if (isExpanded !== 'true') {
			await waffleBtn.click()
			await page.waitForTimeout(300)
		}
		return page.locator(`.app-menu__grid .app-item[title="${contextTitle}"]`)
	}
	// NC33: entries are always visible inline in the header nav
	return page.locator('nav[aria-label="Applications menu"]').locator(`[title="${contextTitle}"]`)
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
		await expect(await getAppMenuEntry(page, contextTitle)).toBeHidden()

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

		await expect(await getAppMenuEntry(page, contextTitle)).toBeVisible()
	})
})
