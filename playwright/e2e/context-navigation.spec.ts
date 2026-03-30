/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test, expect } from '../support/fixtures'
import { createRandomUser } from '../support/api'
import {
	createContext,
	ensureNavigationOpen,
	loadContext,
	openContextEditModal,
} from '../support/commands'
import { login } from '../support/login'

function appMenuEntry(page: import('@playwright/test').Page, contextTitle: string) {
	return page
		.locator('nav[aria-label="Applications menu"]')
		.locator(`[title="${contextTitle}"]`)
}

async function selectUserOption(page: import('@playwright/test').Page, userId: string) {
	const input = page.locator('[data-cy="contextResourceShare"] input').first()
	await input.clear()
	await input.pressSequentially(userId)
	const optionById = page.locator(`.vs__dropdown-menu [id="${userId}"]`).first()
	if (await optionById.count()) {
		await optionById.click()
		return
	}
	await page.locator('.vs__dropdown-menu li').filter({ hasText: userId }).first().click()
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

	test('Create context that shows in nav by default', async ({
		userPage: { page },
		request,
	}) => {
		const nonLocalUser = await createRandomUser(request)
		const contextTitle = 'test application shown'
		await page.goto('/index.php/apps/tables')
		await createContext(page, contextTitle, true)
		await page.reload({ waitUntil: 'domcontentloaded' })
		await ensureNavigationOpen(page)

		// Confirming that the context is shown in the navigation for the owner
		await expect(appMenuEntry(page, contextTitle)).toBeVisible()

		await loadContext(page, contextTitle)
		await expect(page.locator('[data-cy="context-title"]')).toBeVisible()

		await openContextEditModal(page, contextTitle)

		await selectUserOption(page, nonLocalUser.userId)

		await expect(
			page
				.locator('[data-cy="contextResourceShare"] .vs__selected')
				.filter({ hasText: nonLocalUser.userId })
				.first(),
		).toBeVisible()
		await page.locator('[data-cy="editContextSubmitBtn"]').click()

		// Hiding the context from nav for the current user
		await ensureNavigationOpen(page)
		const navContextItem = page
			.locator('[data-cy="navigationContextItem"]')
			.filter({ hasText: contextTitle })
			.first()
		await navContextItem.waitFor({ state: 'visible' })
		await navContextItem.scrollIntoViewIfNeeded()
		await navContextItem.hover()
		await navContextItem
			.getByRole('button', { name: /Actions|Open menu/i })
			.first()
			.click({ force: true })
		await page
			.locator('[data-cy="navigationContextShowInNavSwitch"] input')
			.waitFor({ state: 'attached' })
		await expect(
			page.locator('[data-cy="navigationContextShowInNavSwitch"] input'),
		).toBeChecked()

		await page.getByRole('menuitemcheckbox', { name: 'Show in app list' }).click()
		await expect(appMenuEntry(page, contextTitle)).toBeHidden()

		// Confirming that the context is still shown by default in the navigation for the shared user
		await page.context().clearCookies()
		await login(page, nonLocalUser)
		await page.goto('/index.php/apps/tables')

		await expect(appMenuEntry(page, contextTitle)).toBeVisible()
	})
})
