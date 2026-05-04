/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { type Page } from '@playwright/test'
import { test, expect } from '../support/fixtures'
import { ocsRequest } from '../support/api'
import { createContext, ensureNavigationOpen } from '../support/commands'

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

async function openNavItemMenu(page: Page, itemLocator: ReturnType<Page['locator']>) {
	await ensureNavigationOpen(page)
	await itemLocator.waitFor({ state: 'visible', timeout: 10000 })
	await itemLocator.scrollIntoViewIfNeeded()
	await itemLocator.hover()
	const menuButton = itemLocator.getByRole('button', { name: /Actions|Open menu/i }).first()
	await menuButton.waitFor({ state: 'visible', timeout: 5000 })
	await menuButton.click({ force: true })
}

// ---------------------------------------------------------------------------
// Table archive
// ---------------------------------------------------------------------------

test.describe('Archive tables', () => {

	test('can archive a table via the navigation menu', async ({ userPage: { page } }) => {
		await page.goto('/index.php/apps/tables')

		const tutorialTable = page.locator('[data-cy="navigationTableItem"]').first()
		await expect(tutorialTable).toContainText('Welcome to Nextcloud Tables!')

		await openNavItemMenu(page, tutorialTable)

		await page.getByText('Archive table').waitFor({ state: 'visible' })
		const archiveReqPromise = page.waitForResponse(
			r => r.url().includes('/apps/tables/api/2/tables/') && r.url().endsWith('/archive') && r.request().method() === 'POST',
		)
		await page.getByText('Archive table').click({ force: true })

		const archiveRequest = await archiveReqPromise
		expect(archiveRequest.status()).toBe(200)

		// Table must be gone from the main list and the archived section must appear
		await expect(tutorialTable).not.toBeVisible()
		await expect(page.getByText('Archived tables')).toBeVisible({ timeout: 10000 })
	})

	test('can unarchive a table via the archived section menu', async ({ userPage: { page, user } }) => {
		test.setTimeout(60000)
		await page.goto('/index.php/apps/tables')

		const tutorialTable = page.locator('[data-cy="navigationTableItem"]').filter({ hasText: 'Welcome to Nextcloud Tables!' }).first()
		const tutorialHref = await tutorialTable.locator('a').first().getAttribute('href')
		const tableId = tutorialHref?.match(/\/table\/(\d+)/)?.[1]
		expect(tableId).toBeTruthy()

		// Archive via API so we start from a clean known state
		await ocsRequest(page.request, user, {
			method: 'POST',
			url: `/ocs/v2.php/apps/tables/api/2/tables/${tableId}/archive?format=json`,
		})

		await page.reload({ waitUntil: 'domcontentloaded' })
		await ensureNavigationOpen(page)

		// Expand the archived section by clicking its collapse toggle (.icon-collapse is NcAppNavigationItem's toggle class)
		const archivedSection = page.locator('li').filter({ hasText: 'Archived tables' }).first()
		await archivedSection.waitFor({ state: 'visible', timeout: 10000 })
		await archivedSection.locator('.icon-collapse').first().click()

		// Find the archived table and unarchive it
		const archivedTable = page.locator('[data-cy="navigationTableItem"]').filter({ hasText: 'Welcome to Nextcloud Tables!' }).first()
		await archivedTable.waitFor({ state: 'visible', timeout: 10000 })
		await openNavItemMenu(page, archivedTable)

		const unarchiveReqPromise = page.waitForResponse(
			r => r.url().includes('/apps/tables/api/2/tables/') && r.url().endsWith('/archive') && r.request().method() === 'DELETE',
		)
		await page.getByText('Unarchive table').click({ force: true })

		const unarchiveRequest = await unarchiveReqPromise
		expect(unarchiveRequest.status()).toBe(200)

		// Table reappears in the main list
		await expect(
			page.locator('[data-cy="navigationTableItem"] a[title="Welcome to Nextcloud Tables!"]').first(),
		).toBeVisible({ timeout: 10000 })
	})
})

// ---------------------------------------------------------------------------
// Context (application) archive
// ---------------------------------------------------------------------------

test.describe('Archive applications', () => {

	test('can archive an application via the navigation menu', async ({ userPage: { page } }) => {
		await page.goto('/index.php/apps/tables')
		const contextTitle = 'archive-test-app'
		await createContext(page, contextTitle)

		const contextItem = page.locator('[data-cy="navigationContextItem"]').filter({ hasText: contextTitle }).first()
		await openNavItemMenu(page, contextItem)

		await page.getByText('Archive application').waitFor({ state: 'visible' })
		const archiveReqPromise = page.waitForResponse(
			r => r.url().includes('/apps/tables/api/2/contexts/') && r.url().endsWith('/archive') && r.request().method() === 'POST',
		)
		await page.getByText('Archive application').click({ force: true })

		const archiveRequest = await archiveReqPromise
		expect(archiveRequest.status()).toBe(200)

		// "Archived applications" section must appear
		await expect(
			page.locator('li').filter({ hasText: 'Archived applications' }).first(),
		).toBeVisible({ timeout: 10000 })
		// Item must appear exactly once (inside the archived section) — if still in the active list the count would be 2
		await expect(
			page.locator('[data-cy="navigationContextItem"]').filter({ hasText: contextTitle }),
		).toHaveCount(1, { timeout: 5000 })
	})

	test('can unarchive an application via the archived section', async ({ userPage: { page, user } }) => {
		await page.goto('/index.php/apps/tables')
		const contextTitle = 'unarchive-test-app'
		await createContext(page, contextTitle)

		// Read context ID from the navigation link
		const contextItem = page.locator('[data-cy="navigationContextItem"]').filter({ hasText: contextTitle }).first()
		const contextHref = await contextItem.locator('a').first().getAttribute('href')
		const contextId = contextHref?.match(/\/application\/(\d+)/)?.[1]
		expect(contextId).toBeTruthy()

		// Archive via API for a clean starting state
		await ocsRequest(page.request, user, {
			method: 'POST',
			url: `/ocs/v2.php/apps/tables/api/2/contexts/${contextId}/archive?format=json`,
		})

		await page.reload({ waitUntil: 'domcontentloaded' })
		await ensureNavigationOpen(page)

		// Expand the archived applications section by clicking its collapse toggle
		const archivedSection = page.locator('li').filter({ hasText: 'Archived applications' }).first()
		await archivedSection.waitFor({ state: 'visible', timeout: 10000 })
		await archivedSection.locator('.icon-collapse').first().click()

		// Find the archived context item and unarchive it
		const archivedContextItem = page.locator('[data-cy="navigationContextItem"]').filter({ hasText: contextTitle }).first()
		await archivedContextItem.waitFor({ state: 'visible', timeout: 10000 })
		await openNavItemMenu(page, archivedContextItem)

		const unarchiveReqPromise = page.waitForResponse(
			r => r.url().includes('/apps/tables/api/2/contexts/') && r.url().endsWith('/archive') && r.request().method() === 'DELETE',
		)
		await page.getByText('Unarchive application').click({ force: true })

		const unarchiveRequest = await unarchiveReqPromise
		expect(unarchiveRequest.status()).toBe(200)

		// Application reappears in the main (active) list
		const activeContextItem = page.locator('[data-cy="navigationContextItem"]').filter({ hasText: contextTitle }).first()
		await expect(activeContextItem).toBeVisible({ timeout: 10000 })
		await expect(page.locator('li').filter({ hasText: 'Archived applications' })).toHaveCount(0)
	})

	test('archived application appears in the sidebar archived section and not in the main list', async ({ userPage: { page, user } }) => {
		await page.goto('/index.php/apps/tables')
		const contextTitle = 'sidebar-section-test-app'
		await createContext(page, contextTitle)

		const contextItem = page.locator('[data-cy="navigationContextItem"]').filter({ hasText: contextTitle }).first()
		const contextHref = await contextItem.locator('a').first().getAttribute('href')
		const contextId = contextHref?.match(/\/application\/(\d+)/)?.[1]
		expect(contextId).toBeTruthy()

		await ocsRequest(page.request, user, {
			method: 'POST',
			url: `/ocs/v2.php/apps/tables/api/2/contexts/${contextId}/archive?format=json`,
		})

		await page.reload({ waitUntil: 'domcontentloaded' })
		await ensureNavigationOpen(page)

		// The "Archived applications" collapsible section must be present
		const archivedSection = page.locator('li').filter({ hasText: 'Archived applications' }).first()
		await expect(archivedSection).toBeVisible({ timeout: 10000 })

		// Expand it by clicking the collapse toggle
		await archivedSection.locator('.icon-collapse').first().click()
		const archivedItem = page.locator('[data-cy="navigationContextItem"]').filter({ hasText: contextTitle }).first()
		await expect(archivedItem).toBeVisible({ timeout: 10000 })

		// The item must NOT appear in the active Applications section above
		// (count inside the collapsed archived NcAppNavigationItem should be exactly 1,
		//  and the active list should have 0 matching items — verified by checking
		//  there's no second occurrence)
		await expect(
			page.locator('[data-cy="navigationContextItem"]').filter({ hasText: contextTitle }),
		).toHaveCount(1)
	})
})
