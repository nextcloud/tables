/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { type APIRequestContext, type Page } from '@playwright/test'
import { test, expect } from '../support/fixtures'
import { createRandomUser, ocsRequest, type TestUser } from '../support/api'
import { login } from '../support/login'

// The app navigation takes width from the canvas; the grid only arranges at 12 columns
test.use({ viewport: { width: 1600, height: 1000 } })

const API = '/ocs/v2.php/apps/tables/api/2'

async function createTableViaApi(request: APIRequestContext, user: TestUser, title: string): Promise<number> {
	const res = await ocsRequest(request, user, { method: 'POST', url: '/index.php/apps/tables/api/1/tables', data: { title } })
	return (await res.json()).id
}

async function createApplicationViaApi(request: APIRequestContext, user: TestUser, name: string, tableIds: number[]): Promise<number> {
	const res = await ocsRequest(request, user, {
		method: 'POST',
		url: `${API}/contexts?format=json`,
		data: { name, iconName: 'equalizer', description: '', nodes: tableIds.map(id => ({ id, type: 0, permissions: 1 })) },
	})
	return (await res.json()).ocs.data.id
}

async function getApplication(request: APIRequestContext, user: TestUser, contextId: number) {
	const res = await ocsRequest(request, user, { method: 'GET', url: `${API}/contexts/${contextId}?format=json` })
	return (await res.json()).ocs.data
}

async function createPageViaApi(request: APIRequestContext, user: TestUser, contextId: number, title: string): Promise<number> {
	const res = await ocsRequest(request, user, { method: 'POST', url: `${API}/contexts/${contextId}/pages?format=json`, data: { title } })
	return (await res.json()).ocs.data.id
}

async function openApplication(page: Page, contextId: number, path = '') {
	await page.goto(`/index.php/apps/tables/#/application/${contextId}${path}`)
	await expect(page.locator('[data-cy="contextPageBar"]')).toBeVisible({ timeout: 30000 })
}

async function pickOption(page: Page, selectCy: string, option: string) {
	await page.locator(`[data-cy="${selectCy}"] input`).click()
	await page.locator('.vs__dropdown-menu li').filter({ hasText: option }).click()
}

test.describe('Grid pages of an application', () => {
	test('The start page lists the tables as before, with the page bar under the description', async ({ userPage: { page, user } }) => {
		const first = await createTableViaApi(page.request, user, 'grid first table')
		const second = await createTableViaApi(page.request, user, 'grid second table')
		const contextId = await createApplicationViaApi(page.request, user, 'grid existing app', [first, second])

		await openApplication(page, contextId)

		const firstHeading = page.locator('h1', { hasText: 'grid first table' })
		const secondHeading = page.locator('h1', { hasText: 'grid second table' })
		await expect(firstHeading).toBeVisible()
		await expect(secondHeading).toBeVisible()
		expect((await firstHeading.boundingBox())!.y).toBeLessThan((await secondHeading.boundingBox())!.y)
		await expect(page.locator('[data-cy="contextStartPageLink"]')).toHaveAttribute('aria-selected', 'true')
		await expect(page.locator('[data-cy="contextPageAdd"]')).toBeVisible()
		await expect(page.locator('[data-cy="gridPage"]')).toHaveCount(0)
	})

	test('A new page holds a header and a table of the application after a reload', async ({ userPage: { page, user } }) => {
		const inApp = await createTableViaApi(page.request, user, 'grid table in app')
		await createTableViaApi(page.request, user, 'grid table outside app')
		const contextId = await createApplicationViaApi(page.request, user, 'grid new page app', [inApp])
		await openApplication(page, contextId)

		await page.locator('[data-cy="contextPageAdd"]').click()
		const pageForm = page.locator('[data-cy="contextPageForm"]')
		await pageForm.getByRole('textbox', { name: 'Title' }).fill('Overview')
		await pageForm.getByRole('textbox', { name: 'Technical name' }).fill('page_3')
		await expect(page.locator('[data-cy="contextPageSubmit"]')).toBeDisabled()
		await pageForm.getByRole('textbox', { name: 'Technical name' }).fill('overview')
		await pageForm.locator('[data-cy="iconFieldPick"]').click()
		await page.locator('.icon-picker__icons button').first().click()
		await pageForm.getByRole('textbox', { name: 'Description' }).fill('Open work at a glance')
		await page.locator('[data-cy="contextPageSubmit"]').click()
		await expect(page).toHaveURL(/\/application\/\d+\/page\/\d+/)
		await expect(page.locator('[data-cy="contextPageLink"]', { hasText: 'Overview' })).toHaveAttribute('aria-selected', 'true')
		const created = Object.values((await getApplication(page.request, user, contextId)).pages as Record<string, { title: string, technical_name: string, icon: string | null }>).find(entry => entry.title === 'Overview')
		expect(created!.technical_name).toBe('overview')
		expect(created!.icon).not.toBeNull()
		await expect(page.locator('[data-cy="gridPageTitle"]')).toHaveText('Overview')
		await expect(page.getByText('Open work at a glance')).toBeVisible()

		await page.locator('[data-cy="gridPageEdit"]').click()
		await page.locator('[data-cy="gridPageAddWidget"]').click()
		const dialog = page.locator('[data-cy="addWidgetModal"]')
		await dialog.locator('[data-cy="widgetFieldHeading"] input').fill('Welcome to the overview')
		await page.locator('[data-cy="addWidgetSubmit"]').click()
		await expect(dialog).toBeHidden()

		await page.locator('[data-cy="gridPageAddWidget"]').click()
		await pickOption(page, 'addWidgetType', 'Table or view')
		await dialog.locator('[data-cy="addWidgetNode"] input').click()
		await expect(page.locator('.vs__dropdown-menu li').filter({ hasText: 'grid table outside app' })).toHaveCount(0)
		await page.locator('.vs__dropdown-menu li').filter({ hasText: 'grid table in app' }).click()
		await dialog.getByRole('textbox', { name: 'Title' }).fill('Tasks')
		await page.locator('[data-cy="addWidgetSubmit"]').click()
		await expect(dialog).toBeHidden()

		const saved = page.waitForResponse(r => /\/pages\/\d+/.test(r.url()) && r.request().method() === 'PATCH')
		await page.locator('[data-cy="gridPageSave"]').click()
		expect((await saved).ok()).toBe(true)
		await expect(page.locator('[data-cy="gridPageEdit"]')).toBeVisible()

		await page.reload()
		await expect(page.locator('[data-cy="gridPage"]')).toBeVisible({ timeout: 30000 })
		await expect(page.getByText('Welcome to the overview')).toBeVisible()
		const tasks = page.locator('[data-cy="gridWidget"]', { has: page.locator('h3', { hasText: 'Tasks' }) })
		await expect(tasks.getByText('No columns')).toBeVisible({ timeout: 30000 })
		// The widget has a title of its own, so the table does not repeat its name
		await expect(page.getByRole('heading', { name: 'grid table in app' })).toHaveCount(0)

		// The start page still lists its table and nothing else
		const application = await getApplication(page.request, user, contextId)
		const startPage = Object.values(application.pages as Record<string, { page_type: string, content: object }>).find(entry => entry.page_type === 'startpage')
		expect(Object.keys(startPage!.content)).toHaveLength(1)
		await page.locator('[data-cy="contextStartPageLink"]').click()
		await expect(page.locator('h1', { hasText: 'grid table in app' })).toBeVisible({ timeout: 30000 })
		await expect(page.locator('[data-cy="gridPage"]')).toHaveCount(0)
	})

	test('Cancel drops every change made in edit mode', async ({ userPage: { page, user } }) => {
		const table = await createTableViaApi(page.request, user, 'grid cancel table')
		const contextId = await createApplicationViaApi(page.request, user, 'grid cancel app', [table])
		const pageId = await createPageViaApi(page.request, user, contextId, 'Drafts')
		await openApplication(page, contextId, `/page/${pageId}`)

		await page.locator('[data-cy="gridPageEdit"]').click()
		await page.locator('[data-cy="gridPageAddWidget"]').click()
		await page.locator('[data-cy="widgetFieldHeading"] input').fill('Never saved')
		await page.locator('[data-cy="addWidgetSubmit"]').click()
		await expect(page.locator('[data-cy="gridItem"]')).toHaveCount(1)

		let patched = false
		page.on('request', request => {
			if (request.method() === 'PATCH') {
				patched = true
			}
		})
		await page.locator('[data-cy="gridPageCancel"]').click()
		await expect(page.locator('[data-cy="gridItem"]')).toHaveCount(0)
		expect(patched).toBe(false)
	})

	test('A sharee who cannot manage the application sees its pages read-only with live data', async ({ userPage: { page, user }, browser, baseURL, request }) => {
		const sharee = await createRandomUser(request)
		const table = await createTableViaApi(page.request, user, 'grid shared table')
		const contextId = await createApplicationViaApi(page.request, user, 'grid shared app', [table])
		const pageId = await createPageViaApi(page.request, user, contextId, 'Shared overview')
		const nodeRelId = Number(Object.keys((await getApplication(page.request, user, contextId)).nodes)[0])
		await ocsRequest(page.request, user, {
			method: 'PATCH',
			url: `${API}/contexts/${contextId}/pages/${pageId}?format=json`,
			data: { widgets: [{ type: 'data', title: 'Shared tasks', gridX: 0, gridY: 0, gridWidth: 12, gridHeight: 5, source: { nodeRelId } }] },
		})
		await ocsRequest(page.request, user, {
			method: 'POST',
			url: '/index.php/apps/tables/api/1/shares',
			data: { nodeId: contextId, nodeType: 'context', receiver: sharee.userId, receiverType: 'user', permissionRead: true, displayMode: 2 },
		})

		const shareeContext = await browser.newContext({ baseURL, viewport: { width: 1600, height: 1000 } })
		const shareePage = await shareeContext.newPage()
		await login(shareePage, sharee)
		await openApplication(shareePage, contextId)
		await expect(shareePage.locator('[data-cy="contextPageAdd"]')).toHaveCount(0)
		await shareePage.locator('[data-cy="contextPageLink"]', { hasText: 'Shared overview' }).click()

		const sharedTasks = shareePage.locator('[data-cy="gridWidget"]', { has: shareePage.locator('h3', { hasText: 'Shared tasks' }) })
		await expect(sharedTasks.getByText('No columns')).toBeVisible({ timeout: 30000 })
		await expect(shareePage.locator('[data-cy="gridPageEdit"]')).toHaveCount(0)
		await expect(shareePage.locator('[data-cy="gridPageActions"]')).toHaveCount(0)

		const forbidden = await shareePage.request.fetch(`${API}/contexts/${contextId}/pages/${pageId}?format=json`, {
			method: 'PATCH',
			headers: { 'OCS-APIRequest': 'true', 'Content-Type': 'application/json', Authorization: 'Basic ' + Buffer.from(`${sharee.userId}:${sharee.password}`).toString('base64') },
			data: { widgets: [] },
		})
		expect(forbidden.status()).toBe(403)

		await shareeContext.close()
	})

	test('A table removed from the application leaves its widget asking for a new one', async ({ userPage: { page, user } }) => {
		const table = await createTableViaApi(page.request, user, 'grid removed table')
		const contextId = await createApplicationViaApi(page.request, user, 'grid removal app', [table])
		const pageId = await createPageViaApi(page.request, user, contextId, 'Board')
		const nodeRelId = Number(Object.keys((await getApplication(page.request, user, contextId)).nodes)[0])
		await ocsRequest(page.request, user, {
			method: 'PATCH',
			url: `${API}/contexts/${contextId}/pages/${pageId}?format=json`,
			data: { widgets: [{ type: 'data', title: 'Gone soon', gridX: 0, gridY: 0, gridWidth: 12, gridHeight: 5, source: { nodeRelId } }] },
		})
		await ocsRequest(page.request, user, {
			method: 'PUT',
			url: `${API}/contexts/${contextId}?format=json`,
			data: { nodes: [] },
		})

		await openApplication(page, contextId, `/page/${pageId}`)
		await expect(page.locator('[data-cy="gridWidget"] h3', { hasText: 'Gone soon' })).toBeVisible({ timeout: 30000 })
		await expect(page.locator('[data-cy="dataWidgetNoSource"]')).toBeVisible()

		await page.locator('[data-cy="gridPageEdit"]').click()
		await expect(page.locator('[data-cy="dataWidgetPickSource"]')).toBeVisible()
	})

	test('A widget can be moved and resized from its menu', async ({ userPage: { page, user } }) => {
		const table = await createTableViaApi(page.request, user, 'grid menu move table')
		const contextId = await createApplicationViaApi(page.request, user, 'grid menu move app', [table])
		const pageId = await createPageViaApi(page.request, user, contextId, 'Arranged')
		await ocsRequest(page.request, user, {
			method: 'PATCH',
			url: `${API}/contexts/${contextId}/pages/${pageId}?format=json`,
			data: { widgets: [{ type: 'text', title: 'Notes', gridX: 0, gridY: 0, gridWidth: 6, gridHeight: 2, settings: { text: 'Hello' } }] },
		})
		await openApplication(page, contextId, `/page/${pageId}`)

		await page.locator('[data-cy="gridPageEdit"]').click()
		await page.getByRole('button', { name: 'Widget actions' }).click()
		// The steps keep the menu open, so a widget can be moved several steps in a row
		await page.locator('[data-cy="widgetMoveRight"] button').click()
		await page.locator('[data-cy="widgetWider"] button').click()
		await page.keyboard.press('Escape')

		const saved = page.waitForResponse(r => /\/pages\/\d+/.test(r.url()) && r.request().method() === 'PATCH')
		await page.locator('[data-cy="gridPageSave"]').click()
		const widget = (await (await saved).json()).ocs.data.widgets[0]
		expect(widget).toMatchObject({ gridX: 1, gridWidth: 7 })
	})
})
