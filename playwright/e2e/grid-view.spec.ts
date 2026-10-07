/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { type Page } from '@playwright/test'
import { test, expect } from '../support/fixtures'
import { createContext, loadContext } from '../support/commands'

async function pickOption(page: Page, inputSelector: string, optionText: string) {
	await page.locator(inputSelector).first().click()
	// the select splits the option text for highlighting, so match on the list item, not the accessible name
	const option = page.locator('.vs__dropdown-menu li').filter({ hasText: optionText }).first()
	await option.waitFor({ state: 'visible' })
	await option.click()
}

test.describe('Grid views inside an application', () => {
	test.describe.configure({ mode: 'serial' })
	// the demo path crosses two dialogs and a reload, more than the default budget of one test
	test.setTimeout(180000)

	test('Create an application, add a menu item, design a grid view and keep it after a reload', async ({ userPage: { page } }) => {
		const contextTitle = 'grid demo application'

		await page.goto('/index.php/apps/tables')
		await createContext(page, contextTitle)
		await loadContext(page, contextTitle)

		// The slug through the application dialog, an external menu item through the inline menu editor
		await page.locator('[data-cy="context-edit-application"]').click()
		await expect(page.locator('[data-cy="editContextModal"]').first()).toBeVisible()
		await page.locator('[data-cy="editContextSlug"]').fill('grid-demo')
		await page.locator('[data-cy="editContextSubmitBtn"]').click()
		await expect(page.locator('[data-cy="context-address"]')).toContainText('/apps/tables/app/grid-demo')

		await page.locator('[data-cy="context-menu-edit"]').click()
		await page.locator('[data-cy="menuItemAdd"]').click()
		await page.locator('[data-cy="menuItemLabel"]').first().fill('Documentation')
		await page.locator('[data-cy="menuItemRow"] input[placeholder="https://"]').first().fill('https://docs.nextcloud.com')
		await page.locator('[data-cy="context-menu-save"]').click()
		await expect(page.locator('[data-cy="context-menu-item"]').filter({ hasText: 'Documentation' })).toBeVisible()
		await expect(page.locator('[data-cy="page-card"]').filter({ hasText: 'Documentation' })).toBeVisible()

		// A grid view created from the application gets its own menu item
		await page.locator('[data-cy="context-add-grid-view"]').click()
		await page.locator('[data-cy="gridViewTitle"]').fill('Intake home')
		await page.locator('[data-cy="gridViewDescription"]').fill('Start here every morning')
		await page.locator('[data-cy="gridViewSlug"]').fill('home')
		await page.locator('[data-cy="gridViewSubmit"]').click()
		await expect(page).toHaveURL(/\/menu\/home$/)
		await expect(page.locator('[data-cy="grid-view-title"]')).toHaveText(/Intake home/)
		await expect(page.locator('[data-cy="context-menu-item"]').filter({ hasText: 'Intake home' })).toBeVisible()

		// Design it: a header and a description
		await page.locator('[data-cy="grid-view-edit"]').click()
		await page.locator('[data-cy="grid-view-add-widget"]').click()
		await page.locator('[data-cy="widgetField-title"] input').fill('Welcome, intake team')
		await page.locator('[data-cy="addWidgetSubmit"]').click()
		await expect(page.locator('[data-cy="grid-item"]')).toHaveCount(1)

		await page.locator('[data-cy="grid-view-add-widget"]').click()
		await pickOption(page, '[data-cy="addWidgetType"] input', 'Description')
		await page.locator('[data-cy="addWidgetTitle"]').fill('How this works')
		await page.locator('[data-cy="widgetField-text"] textarea').fill('Every new request lands in the welcome table.')
		await page.locator('[data-cy="addWidgetSubmit"]').click()
		await expect(page.locator('[data-cy="grid-item"]')).toHaveCount(2)

		const saveResponse = page.waitForResponse(response => response.url().includes('/apps/tables/view/') && response.request().method() === 'PUT')
		await page.locator('[data-cy="grid-view-save"]').click()
		expect((await saveResponse).status()).toBe(200)
		await expect(page.locator('[data-cy="grid-view-edit"]')).toBeVisible()

		await page.reload()
		await expect(page.locator('[data-cy="grid-item"]')).toHaveCount(2)
		await expect(page.locator('[data-cy="grid-widget"]').filter({ hasText: 'Welcome, intake team' })).toBeVisible()
		await expect(page.locator('[data-cy="grid-widget"]').filter({ hasText: 'How this works' })).toBeVisible()
		const header = page.locator('[data-cy="grid-item"]').first()
		await expect(header).toHaveAttribute('gs-w', '12')
	})

	test('An application opened on its own shows its menu and its first page', async ({ userPage: { page } }) => {
		const contextTitle = 'standalone demo application'
		await page.goto('/index.php/apps/tables')
		await createContext(page, contextTitle)
		await loadContext(page, contextTitle)
		await page.locator('[data-cy="context-edit-application"]').click()
		await page.locator('[data-cy="editContextSlug"]').fill('standalone-demo')
		await page.locator('[data-cy="editContextSubmitBtn"]').click()
		await expect(page.locator('[data-cy="context-address"]')).toContainText('/apps/tables/app/standalone-demo')

		await page.locator('[data-cy="context-add-page"]').click()
		await page.locator('[data-cy="gridViewTitle"]').fill('Front page')
		await page.locator('[data-cy="gridViewSubmit"]').click()
		await expect(page).toHaveURL(/\/menu\/front-page$/)

		await page.goto('/index.php/apps/tables/app/standalone-demo')
		await expect(page.locator('[data-cy="application-nav-header"]')).toContainText(contextTitle)
		await expect(page.locator('[data-cy="application-nav-item"]').filter({ hasText: 'Front page' })).toBeVisible()
		await expect(page).toHaveURL(/\/menu\/front-page$/)
		await expect(page.locator('[data-cy="grid-view-title"]')).toHaveText(/Front page/)
		await expect(page.locator('[data-cy="navigationCreateTableIcon"]')).toHaveCount(0)
	})

	test('A grid view is listed under Views and opens on its own page', async ({ userPage: { page } }) => {
		await page.goto('/index.php/apps/tables')
		await page.locator('[data-cy="navigationCreateGridViewIcon"]').click()
		await page.locator('[data-cy="gridViewTitle"]').fill('Standalone board')
		await page.locator('[data-cy="gridViewSubmit"]').click()
		await expect(page).toHaveURL(/\/view\/\d+$/)
		await expect(page.locator('[data-cy="grid-view-title"]')).toHaveText(/Standalone board/)
		await expect(page.locator('[data-cy="navigationViewItem"]').filter({ hasText: 'Standalone board' })).toBeVisible()
	})
})
