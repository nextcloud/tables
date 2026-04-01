/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test, expect } from '../support/fixtures'
import { ocsRequest } from '../support/api'
import { loadTable } from '../support/commands'

function getNavigationItemActionsButton(item) {
	return item.locator(':scope > .app-navigation-entry .app-navigation-entry__actions [aria-haspopup="menu"]').first()
}

async function ensureFirstTableViewsExpanded(page) {
	const firstTable = page.locator('[data-cy="navigationTableItem"]').first()
	const firstView = page.locator('[data-cy="navigationViewItem"]').first()
	if (await firstView.isVisible().catch(() => false)) {
		return
	}

	const expandButton = firstTable.getByRole('button', { name: /Open menu|Expand menu|Collapse menu/i }).last()
	if (await expandButton.isVisible().catch(() => false)) {
		await expandButton.click({ force: true })
	}

	await firstView.waitFor({ state: 'visible', timeout: 10000 })
}

test.describe('Favorite tables/views', () => {

	test('can favorite and remove favorite tables', async ({ userPage: { page } }) => {
		await page.goto('/index.php/apps/tables')

		const tutorialTable = page.locator('[data-cy="navigationTableItem"]').filter({ hasText: 'Welcome to Nextcloud Tables!' }).first()

		await expect(tutorialTable).toContainText('Welcome to Nextcloud Tables!')

		// Add to favorites
		await tutorialTable.hover()
		const menuButton = getNavigationItemActionsButton(tutorialTable)
		await menuButton.waitFor({ state: 'visible' })
		await menuButton.click({ force: true })
		await page.getByRole('menuitem', { name: 'Add to favorites' }).waitFor({ state: 'visible' })
		const favoriteTableReqPromise = page.waitForResponse(r => r.url().includes('/ocs/v2.php/apps/tables/api/2/favorites/') && r.request().method() === 'POST')
		await page.getByRole('menuitem', { name: 'Add to favorites' }).click({ force: true })
		const req = await favoriteTableReqPromise
		expect(req.status()).toBe(200)

		await expect(tutorialTable.locator('..')).toContainText('Favorites')

		// Remove from favorites
		await tutorialTable.hover()
		const menuButtonRemove = getNavigationItemActionsButton(tutorialTable)
		await menuButtonRemove.waitFor({ state: 'visible' })
		await menuButtonRemove.click({ force: true })
		await page.getByRole('menuitem', { name: 'Remove from favorites' }).waitFor({ state: 'visible' })
		const unfavoriteTableReqPromise = page.waitForResponse(r => r.url().includes('/ocs/v2.php/apps/tables/api/2/favorites/') && r.request().method() === 'DELETE')
		await page.getByRole('menuitem', { name: 'Remove from favorites' }).click({ force: true })
		const req2 = await unfavoriteTableReqPromise
		expect(req2.status()).toBe(200)

		await expect(tutorialTable.locator('..')).toContainText('Tables')
	})

	test('can favorite and unfavorite views', async ({ userPage: { page } }) => {
		await page.goto('/index.php/apps/tables')
		await loadTable(page, 'Welcome to Nextcloud Tables!')
		await ensureFirstTableViewsExpanded(page)

		const testView = page.locator('[data-cy="navigationViewItem"]').filter({ hasText: 'Check yourself!' }).first()

		await expect(testView.locator('..').locator('..').locator('..')).toContainText('Welcome to ')

		// favorite view
		await testView.hover()
		const viewMenuButton = getNavigationItemActionsButton(testView)
		await viewMenuButton.waitFor({ state: 'visible' })
		await viewMenuButton.click({ force: true })
		await page.getByRole('menuitem', { name: 'Add to favorites' }).waitFor({ state: 'visible' })
		const favoriteViewReqPromise = page.waitForResponse(r => r.url().includes('/ocs/v2.php/apps/tables/api/2/favorites/') && r.request().method() === 'POST')
		await page.getByRole('menuitem', { name: 'Add to favorites' }).click({ force: true })
		const req = await favoriteViewReqPromise
		expect(req.status()).toBe(200)

		await expect(testView.locator('..')).toContainText('Favorites')

		// unfavorite view
		await testView.hover()
		const viewMenuButtonUnfav = getNavigationItemActionsButton(testView)
		await viewMenuButtonUnfav.waitFor({ state: 'visible' })
		await viewMenuButtonUnfav.click({ force: true })
		await page.getByRole('menuitem', { name: 'Remove from favorites' }).waitFor({ state: 'visible' })
		const unfavoriteViewReqPromise = page.waitForResponse(r => r.url().includes('/ocs/v2.php/apps/tables/api/2/favorites/') && r.request().method() === 'DELETE')
		await page.getByRole('menuitem', { name: 'Remove from favorites' }).click({ force: true })
		const req2 = await unfavoriteViewReqPromise
		expect(req2.status()).toBe(200)

		await expect(testView.locator('..').locator('..').locator('..')).toContainText('Welcome to ')
	})

	test('can (un)favorite views with favorited parent tables', async ({ userPage: { page, user } }) => {
		await page.goto('/index.php/apps/tables')
		await loadTable(page, 'Welcome to Nextcloud Tables!')
		await ensureFirstTableViewsExpanded(page)

		const testView = page.locator('[data-cy="navigationViewItem"]').filter({ hasText: 'Check yourself!' }).first()
		const tutorialTable = page.locator('[data-cy="navigationTableItem"]').filter({ hasText: 'Welcome to Nextcloud Tables!' }).first()

		await expect(testView.locator('..').locator('..').locator('..')).toContainText('Welcome to ')
		await testView.hover()
		const nestedViewMenu = getNavigationItemActionsButton(testView)
		await nestedViewMenu.waitFor({ state: 'visible' })
		await nestedViewMenu.click({ force: true })
		await page.getByRole('menuitem', { name: 'Add to favorites' }).waitFor({ state: 'visible' })
		const favoriteViewReqPromise = page.waitForResponse(r => r.url().includes('/ocs/v2.php/apps/tables/api/2/favorites/') && r.request().method() === 'POST')
		await page.getByRole('menuitem', { name: 'Add to favorites' }).click({ force: true })
		const favoriteViewReq = await favoriteViewReqPromise
		expect(favoriteViewReq.status()).toBe(200)

		await expect(testView.locator('..')).toContainText('Favorites')

		const tutorialTableHref = await tutorialTable.locator('a').first().getAttribute('href')
		const tutorialTableId = tutorialTableHref?.match(/\/table\/(\d+)/)?.[1]
		expect(tutorialTableId).toBeTruthy()

		const favoriteTableResponse = await ocsRequest(page.request, user, {
			method: 'POST',
			url: `/ocs/v2.php/apps/tables/api/2/favorites/0/${tutorialTableId}?format=json`,
		})
		expect(favoriteTableResponse.ok()).toBeTruthy()

		await page.goto('/index.php/apps/tables')
		await loadTable(page, 'Welcome to Nextcloud Tables!')
		await ensureFirstTableViewsExpanded(page)

		const favoritedTutorialTable = page.locator('[data-cy="navigationTableItem"]').filter({ hasText: 'Welcome to Nextcloud Tables!' }).first()
		const favoritedTestView = page.locator('[data-cy="navigationViewItem"]').filter({ hasText: 'Check yourself!' }).first()

		await expect(favoritedTutorialTable.locator('..')).toContainText('Tables')
		await expect(favoritedTestView.locator('..')).not.toContainText('Favorites')
	})
})
