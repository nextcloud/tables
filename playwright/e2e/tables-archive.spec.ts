/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test, expect } from '../support/fixtures'
import { ocsRequest } from '../support/api'

test.describe('Archive tables/views', () => {

	test('can archive tables', async ({ userPage: { page } }) => {
		await page.goto('/index.php/apps/tables')

		const tutorialTable = page.locator('[data-cy="navigationTableItem"]').first()

		await expect(tutorialTable).toContainText('Welcome to Nextcloud Tables!')
		await tutorialTable.hover()
		const menuButton = tutorialTable.locator('[aria-haspopup="menu"]').first()
		await menuButton.waitFor({ state: 'visible' })
		await menuButton.click({ force: true })

		await page.getByText('Archive table').waitFor({ state: 'visible' })
		const archiveTableReqPromise = page.waitForResponse(r => r.url().includes('/apps/tables/api/2/tables/') && r.request().method() === 'PUT')
		await page.getByText('Archive table').click({ force: true })

		const archiveRequest = await archiveTableReqPromise
		expect(archiveRequest.status()).toBe(200)
		const body = await archiveRequest.json()
		expect(body.ocs.data.archived).toBe(true)

		await expect(tutorialTable.locator('..').locator('..')).toContainText('Archived tables')
	})

	test('can unarchive tables', async ({ userPage: { page, user } }) => {
		test.setTimeout(60000)
		await page.goto('/index.php/apps/tables')

		const tutorialTable = page.locator('[data-cy="navigationTableItem"]').filter({ hasText: 'Welcome to Nextcloud Tables!' }).first()
		const tutorialHref = await tutorialTable.locator('a').first().getAttribute('href')
		const tableId = tutorialHref?.match(/\/table\/(\d+)/)?.[1]

		await expect(tutorialTable).toContainText('Welcome to Nextcloud Tables!')
		expect(tableId).toBeTruthy()

		// Archive it first so we can unarchive
		await tutorialTable.hover()
		const menuButtonArchive = tutorialTable.locator('[aria-haspopup="menu"]').first()
		await menuButtonArchive.waitFor({ state: 'visible' })
		await menuButtonArchive.click({ force: true })

		await page.getByText('Archive table').waitFor({ state: 'visible' })
		const archiveReqPromise = page.waitForResponse(r => r.url().includes('/apps/tables/api/2/tables/') && r.request().method() === 'PUT')
		await page.getByText('Archive table').click({ force: true })
		await archiveReqPromise

		// Wait for navigation to reflect the archived state.
		const archivedTablesToggle = page.getByRole('link', { name: 'Archived tables' })
		await expect(archivedTablesToggle).toBeVisible({ timeout: 10000 })
		const unarchiveResponse = await ocsRequest(page.request, user, {
			method: 'PUT',
			url: `/ocs/v2.php/apps/tables/api/2/tables/${tableId}?format=json`,
			data: { archived: false },
		})
		expect(unarchiveResponse.ok()).toBeTruthy()

		await page.goto('/index.php/apps/tables')
		await expect(page.locator('[data-cy="navigationTableItem"] a[title="Welcome to Nextcloud Tables!"]').first()).toBeVisible({ timeout: 10000 })
	})
})
