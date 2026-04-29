/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test as base } from '@playwright/test'
import { test, expect } from '../support/fixtures'
import type { BrowserContext, Page } from '@playwright/test'
import { createRandomUser } from '../support/api'
import { login } from '../support/login'
import {
	clickOnTableThreeDotMenu,
	createTable,
	createTextLineColumn,
	fillInValueTextLine,
	loadTable,
	loadView,
} from '../support/commands'

const tableTitle = 'Layout mode test table'
const defaultViewTitle = 'Layout default table view'
const tilesViewTitle = 'Layout tiles view'
const galleryViewTitle = 'Layout gallery view'

async function setupLayoutTable(page: Page) {
	await createTable(page, tableTitle)
	await createTextLineColumn(page, 'preview', '', '', true)
	await createTextLineColumn(page, 'title', '', '', false)
	await createTextLineColumn(page, 'category', '', '', false)

	const addRow = async (preview: string, title: string, category: string) => {
		await page.locator('[data-cy="createRowBtn"]').click()
		await fillInValueTextLine(page, 'preview', preview)
		await fillInValueTextLine(page, 'title', title)
		await fillInValueTextLine(page, 'category', category)
		await page.locator('[data-cy="createRowSaveButton"]').click()
	}

	await addRow('not-a-preview-url', 'First layout row', 'Catalogue')
	await addRow('', 'Second layout row', 'Inventory')
}

async function createLayoutView(page: Page, title: string, layout?: 'tiles' | 'gallery') {
	await loadTable(page, tableTitle)
	await clickOnTableThreeDotMenu(page, 'Create view')

	const titleInput = page.locator('[data-cy="viewSettingsDialogTitleInput"]')
	await titleInput.waitFor({ state: 'visible', timeout: 10000 })
	await titleInput.fill(title)

	if (layout) {
		await page.locator(`[data-cy="viewLayout${layout === 'tiles' ? 'Tiles' : 'Gallery'}"]`).click()
	}

	const createViewReqPromise = page.waitForResponse(
		response => response.url().includes('/apps/tables/view') && response.request().method() === 'POST',
	)
	await page.locator('[data-cy="modifyViewBtn"]').click()
	await createViewReqPromise

	await expect(page.locator('[data-cy="navigationViewItem"]').filter({ hasText: title }).first()).toBeVisible()
}

async function expectTableLayout(page: Page) {
	await expect(page.locator('[data-cy="customTableRow"]').filter({ hasText: 'First layout row' }).first()).toBeVisible()
	await expect(page.locator('[data-cy="tilesLayoutCard"]')).toHaveCount(0)
	await expect(page.locator('[data-cy="galleryLayoutCard"]')).toHaveCount(0)
}

async function expectTilesLayout(page: Page) {
	const cards = page.locator('[data-cy="tilesLayoutCard"]')
	await expect(cards).toHaveCount(2)
	await expect(cards.filter({ hasText: 'First layout row' }).first()).toBeVisible()
	await expect(cards.filter({ hasText: 'Second layout row' }).first()).toBeVisible()
	await expect(page.locator('[data-cy="galleryLayoutBody"]')).toHaveCount(0)
	await expect(page.locator('[data-cy="customTableRow"]')).toHaveCount(0)
}

async function expectGalleryLayout(page: Page) {
	const cards = page.locator('[data-cy="galleryLayoutCard"]')
	await expect(cards).toHaveCount(2)

	const firstCard = cards.filter({ hasText: 'First layout row' }).first()
	await expect(firstCard).toBeVisible()
	await expect(firstCard.locator('[data-cy="galleryMetadataItem"]').filter({ hasText: 'category' })).toContainText('Catalogue')
	await expect(firstCard.locator('[data-cy="galleryMetadataItem"]').filter({ hasText: 'preview' })).toContainText('not-a-preview-url')
	await expect(page.locator('[data-cy="customTableRow"]')).toHaveCount(0)
}

test.describe('View layout modes', () => {
	test.describe.configure({ mode: 'serial' })

	let context: BrowserContext
	let page: Page

	// @ts-expect-error - Playwright complex types mismatch in this environment
	base.beforeAll(async ({ browser, baseURL }) => {
		context = await browser.newContext({
			baseURL,
		})
		page = await context.newPage()

		const user = await createRandomUser(page.request)
		await login(page, user)

		await page.goto('/index.php/apps/tables')
		await setupLayoutTable(page)
		await loadTable(page, tableTitle)
	}, 120000)

	test.afterAll(async () => {
		await context?.close()
	})

	test.beforeEach(async () => {
		await page.goto('/index.php/apps/tables')
		await page.keyboard.press('Escape')
	})

	test('keeps table layout as the default for new views', async () => {
		await createLayoutView(page, defaultViewTitle)
		await loadView(page, defaultViewTitle)

		await expectTableLayout(page)
	})

	test('renders and persists the tiles layout for a view', async () => {
		await createLayoutView(page, tilesViewTitle, 'tiles')
		await loadView(page, tilesViewTitle)
		await expectTilesLayout(page)

		await page.reload({ waitUntil: 'domcontentloaded' })
		await expect(page.locator('.icon-loading').first()).toBeHidden({ timeout: 10000 })
		await expectTilesLayout(page)
	})

	test('renders and persists the gallery layout for a view', async () => {
		await createLayoutView(page, galleryViewTitle, 'gallery')
		await loadView(page, galleryViewTitle)
		await expectGalleryLayout(page)

		await loadTable(page, tableTitle)
		await loadView(page, galleryViewTitle)
		await expectGalleryLayout(page)
	})
})
