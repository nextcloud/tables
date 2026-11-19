/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { type APIRequestContext, type Page } from '@playwright/test'
import { test, expect } from '../support/fixtures'
import { createRandomUser, ocsRequest, type TestUser } from '../support/api'
import { login } from '../support/login'
import { openContextEditModal } from '../support/commands'

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

async function createRowViaApi(request: APIRequestContext, user: TestUser, tableId: number, value: string): Promise<number> {
	const column = await ocsRequest(request, user, {
		method: 'POST',
		url: `/index.php/apps/tables/api/1/tables/${tableId}/columns`,
		data: { title: 'Name', type: 'text', subtype: 'line', mandatory: false },
	})
	const columnId = (await column.json()).id
	const row = await ocsRequest(request, user, {
		method: 'POST',
		url: `/index.php/apps/tables/api/1/tables/${tableId}/rows`,
		data: { data: { [columnId]: value } },
	})
	return (await row.json()).id
}

async function openApplication(page: Page, contextId: number, path = '') {
	await page.goto(`/index.php/apps/tables/#/application/${contextId}${path}`)
	await expect(page.locator('[data-cy="contextPageBar"]')).toBeVisible({ timeout: 30000 })
}

async function pickOption(page: Page, selectCy: string, option: string) {
	await page.locator(`[data-cy="${selectCy}"] input`).click()
	await page.getByRole('option', { name: option, exact: true }).click()
}

test.describe('Grid pages of an application', () => {
	test('The start page of a new application shows its tables in one widget', async ({ userPage: { page, user } }) => {
		const first = await createTableViaApi(page.request, user, 'grid first table')
		const second = await createTableViaApi(page.request, user, 'grid second table')
		const contextId = await createApplicationViaApi(page.request, user, 'grid existing app', [first, second])

		await openApplication(page, contextId)

		const startPage = page.locator('[data-cy="startPageGrid"]')
		await expect(startPage).toBeVisible()
		const tablesWidget = startPage.locator('[data-cy="tablesWidget"]')
		const firstHeading = tablesWidget.getByRole('heading', { name: 'grid first table' })
		const secondHeading = tablesWidget.getByRole('heading', { name: 'grid second table' })
		await expect(firstHeading).toBeVisible({ timeout: 30000 })
		await expect(secondHeading).toBeVisible({ timeout: 30000 })
		expect((await firstHeading.boundingBox())!.y).toBeLessThan((await secondHeading.boundingBox())!.y)
		await expect(page.locator('[data-cy="gridWidget"]')).toHaveCount(1)
		await expect(startPage.locator('.header-widget')).toHaveCount(0)
		await expect(page.locator('[data-cy="contextStartPageLink"]')).toHaveAttribute('aria-selected', 'true')
		await expect(page.locator('[data-cy="contextPageAdd"]')).toBeVisible()
		await expect(page.locator('.resources')).toHaveCount(0)
	})

	test('The start page can be edited like a grid page, but not deleted', async ({ userPage: { page, user } }) => {
		const table = await createTableViaApi(page.request, user, 'grid start edit table')
		const contextId = await createApplicationViaApi(page.request, user, 'grid start edit app', [table])
		await openApplication(page, contextId)

		await page.locator('[data-cy="gridPageEdit"]').click()
		await page.locator('[data-cy="gridPageAddWidget"]').click()
		await pickOption(page, 'addWidgetType', 'Description')
		await page.locator('[data-cy="widgetFieldText"] textarea').fill('Read this first')
		await page.locator('[data-cy="addWidgetSubmit"]').click()
		const saved = page.waitForResponse(r => /\/pages\/\d+/.test(r.url()) && r.request().method() === 'PATCH')
		await page.locator('[data-cy="gridPageSave"]').click()
		expect((await saved).ok()).toBe(true)

		await page.reload()
		await expect(page.getByText('Read this first')).toBeVisible({ timeout: 30000 })
		// With only one action left the menu shows it as a button of its own
		await expect(page.getByRole('button', { name: 'Edit page details' })).toBeVisible()
		await expect(page.getByRole('button', { name: 'Page actions' })).toHaveCount(0)
	})

	test('A new page holds a header and a table of the application after a reload', async ({ userPage: { page, user } }) => {
		const inApp = await createTableViaApi(page.request, user, 'grid table in app')
		await createTableViaApi(page.request, user, 'grid table outside app')
		const contextId = await createApplicationViaApi(page.request, user, 'grid new page app', [inApp])
		await openApplication(page, contextId)

		await page.locator('[data-cy="contextPageAdd"]').click()
		const pageForm = page.locator('[data-cy="contextPageForm"]')
		await pageForm.getByRole('textbox', { name: 'Title' }).fill('Overview')
		await pageForm.getByRole('textbox', { name: 'Slug' }).fill('page_3')
		await expect(page.locator('[data-cy="contextPageSubmit"]')).toBeDisabled()
		await pageForm.getByRole('textbox', { name: 'Slug' }).fill('overview')
		await pageForm.locator('[data-cy="iconFieldPick"]').click()
		await page.locator('.icon-picker__icons button').first().click()
		await pageForm.getByRole('textbox', { name: 'Description' }).fill('Open work at a glance')
		await page.locator('[data-cy="contextPageSubmit"]').click()
		await expect(page).toHaveURL(/\/application\/\d+\/page\/\d+/)
		await expect(page.locator('[data-cy="contextPageLink"]', { hasText: 'Overview' })).toHaveAttribute('aria-selected', 'true')
		const created = Object.values((await getApplication(page.request, user, contextId)).pages as Record<string, { title: string, slug: string, icon: string | null }>).find(entry => entry.title === 'Overview')
		expect(created!.slug).toBe('overview')
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
		await expect(page.getByRole('option', { name: 'grid table outside app', exact: true })).toHaveCount(0)
		await page.getByRole('option', { name: 'grid table in app', exact: true }).click()
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
		await expect(page.locator('[data-cy="gridWidget"]').getByRole('heading', { name: 'grid table in app' })).toBeVisible({ timeout: 30000 })
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
			data: { version: 0, widgets: [{ type: 'data', title: 'Shared tasks', gridX: 0, gridY: 0, gridWidth: 12, gridHeight: 5, source: { nodeRelId } }] },
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
			data: { version: 0, widgets: [{ type: 'data', title: 'Gone soon', gridX: 0, gridY: 0, gridWidth: 12, gridHeight: 5, source: { nodeRelId } }] },
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
			data: { version: 0, widgets: [{ type: 'text', title: 'Notes', gridX: 0, gridY: 0, gridWidth: 6, gridHeight: 2, settings: { text: 'Hello' } }] },
		})
		await openApplication(page, contextId, `/page/${pageId}`)

		await page.locator('[data-cy="gridPageEdit"]').click()
		await page.getByRole('button', { name: /^Actions for / }).click()
		// The steps keep the menu open, so a widget can be moved several steps in a row
		await page.locator('[data-cy="widgetMoveRight"] button').click()
		await page.locator('[data-cy="widgetWider"] button').click()
		await page.keyboard.press('Escape')

		const saved = page.waitForResponse(r => /\/pages\/\d+/.test(r.url()) && r.request().method() === 'PATCH')
		await page.locator('[data-cy="gridPageSave"]').click()
		const widget = (await (await saved).json()).ocs.data.widgets[0]
		expect(widget).toMatchObject({ gridX: 1, gridWidth: 7 })
	})

	test('A row opened from a data widget on a grid page opens in the row editor', async ({ userPage: { page, user } }) => {
		const table = await createTableViaApi(page.request, user, 'grid row table')
		const rowId = await createRowViaApi(page.request, user, table, 'First task')
		const contextId = await createApplicationViaApi(page.request, user, 'grid row app', [table])
		const pageId = await createPageViaApi(page.request, user, contextId, 'Rows')
		const nodeRelId = Number(Object.keys((await getApplication(page.request, user, contextId)).nodes)[0])
		await ocsRequest(page.request, user, {
			method: 'PATCH',
			url: `${API}/contexts/${contextId}/pages/${pageId}?format=json`,
			data: { version: 0, widgets: [{ type: 'data', title: 'Tasks', gridX: 0, gridY: 0, gridWidth: 12, gridHeight: 5, source: { nodeRelId } }] },
		})
		await openApplication(page, contextId, `/page/${pageId}`)

		const row = page.locator('[data-cy="gridWidget"] tr', { hasText: 'First task' })
		await row.locator('[data-cy="rowActionMenu"] button').click()
		await page.locator('[data-cy="editRowBtn"]').click()
		await expect(page).toHaveURL(new RegExp(`/application/${contextId}/page/${pageId}/row/${rowId}$`))
		await expect(page.locator('[data-cy="editRowModal"]')).toBeVisible()
		await expect(page.locator('[data-cy="gridPage"]')).toBeVisible()
	})

	test('An application opened on its own address keeps the page that is picked', async ({ userPage: { page, user } }) => {
		const table = await createTableViaApi(page.request, user, 'grid standalone table')
		const contextId = await createApplicationViaApi(page.request, user, 'grid standalone app', [table])
		const pageId = await createPageViaApi(page.request, user, contextId, 'Standalone page')

		await page.goto(`/index.php/apps/tables/app/${contextId}`)
		await expect(page.locator('[data-cy="contextPageBar"]')).toBeVisible({ timeout: 30000 })
		await page.locator('[data-cy="contextPageLink"]', { hasText: 'Standalone page' }).click()
		await expect(page).toHaveURL(new RegExp(`/application/${contextId}/page/${pageId}$`))
		await expect(page.locator('[data-cy="gridPageTitle"]')).toHaveText('Standalone page')

		await page.locator('[data-cy="contextPageAdd"]').click()
		await page.locator('[data-cy="contextPageForm"]').getByRole('textbox', { name: 'Title' }).fill('Added standalone')
		await page.locator('[data-cy="contextPageSubmit"]').click()
		await expect(page.locator('[data-cy="gridPageTitle"]')).toHaveText('Added standalone')
		await expect(page.locator('[data-cy="contextPageLink"]', { hasText: 'Added standalone' })).toHaveAttribute('aria-selected', 'true')
	})

	test('A page tab keeps the focus when it is activated', async ({ userPage: { page, user } }) => {
		const table = await createTableViaApi(page.request, user, 'grid focus table')
		const contextId = await createApplicationViaApi(page.request, user, 'grid focus app', [table])
		await createPageViaApi(page.request, user, contextId, 'Focused page')
		await openApplication(page, contextId)

		const tab = page.locator('[data-cy="contextPageLink"]', { hasText: 'Focused page' })
		await tab.focus()
		await page.keyboard.press('Enter')
		await expect(page.locator('[data-cy="gridPageTitle"]')).toHaveText('Focused page')
		await expect(tab).toBeFocused()

		const home = page.locator('[data-cy="contextStartPageLink"]')
		await home.focus()
		await page.keyboard.press('Enter')
		await expect(home).toHaveAttribute('aria-selected', 'true')
		await expect(home).toBeFocused()
	})

	test('Editing the application keeps the edit of the page that is shown', async ({ userPage: { page, user } }) => {
		const table = await createTableViaApi(page.request, user, 'grid reload table')
		const added = await createTableViaApi(page.request, user, 'grid reload added table')
		const contextId = await createApplicationViaApi(page.request, user, 'grid reload app', [table])
		const pageId = await createPageViaApi(page.request, user, contextId, 'Being edited')
		await ocsRequest(page.request, user, {
			method: 'PATCH',
			url: `${API}/contexts/${contextId}/pages/${pageId}?format=json`,
			data: { version: 0, widgets: [{ type: 'text', title: 'Keep me out', gridX: 0, gridY: 0, gridWidth: 6, gridHeight: 2, settings: { text: 'Hello' } }] },
		})
		expect(added).toBeGreaterThan(0)
		await openApplication(page, contextId, `/page/${pageId}`)

		await page.locator('[data-cy="gridPageEdit"]').click()
		await page.getByRole('button', { name: /^Actions for / }).click()
		await page.getByRole('menuitem', { name: 'Remove widget' }).click()
		await expect(page.locator('[data-cy="gridItem"]')).toHaveCount(0)

		await openContextEditModal(page, 'grid reload app')
		const resourceInput = page.locator('[data-cy="contextResourceForm"] input').first()
		await resourceInput.click()
		await resourceInput.fill('grid reload added table')
		await page.getByRole('option', { name: /^grid reload added table/ }).first().click()
		await expect(page.locator('[data-cy="contextResourceList"]')).toContainText('grid reload added table')
		const updated = page.waitForResponse(r => r.url().includes(`/contexts/${contextId}`) && r.request().method() === 'PUT')
		await page.locator('[data-cy="editContextSubmitBtn"]').click()
		expect((await updated).ok()).toBe(true)
		await expect(page.getByRole('dialog', { name: 'Edit application' })).toBeHidden({ timeout: 10000 })

		await expect(page.locator('[data-cy="gridPageSave"]')).toBeVisible()
		await expect(page.locator('[data-cy="gridItem"]')).toHaveCount(0)
	})

	test('A page that is opened while another one still loads shows without a flash of an error', async ({ userPage: { page, user } }) => {
		const table = await createTableViaApi(page.request, user, 'grid race table')
		const contextId = await createApplicationViaApi(page.request, user, 'grid race app', [table])
		const firstId = await createPageViaApi(page.request, user, contextId, 'First page')
		const secondId = await createPageViaApi(page.request, user, contextId, 'Second page')
		await openApplication(page, contextId)

		// The first page answers while the second one, opened after it, still loads
		const delays = { [firstId]: 1000, [secondId]: 3000 }
		await page.route(new RegExp(`/contexts/${contextId}/pages/(${firstId}|${secondId})(\\?|$)`), async route => {
			const pageId = Number(new URL(route.request().url()).pathname.split('/').pop())
			await new Promise(resolve => setTimeout(resolve, delays[pageId]))
			await route.continue()
		})
		await page.locator('[data-cy="contextPageLink"]', { hasText: 'First page' }).click()
		await page.locator('[data-cy="contextPageLink"]', { hasText: 'Second page' }).click()
		let notFoundShown = false
		const deadline = Date.now() + 4000
		while (Date.now() < deadline) {
			notFoundShown ||= await page.getByText('This page could not be found').isVisible()
			await page.waitForTimeout(100)
		}
		expect(notFoundShown).toBe(false)
		await expect(page.locator('[data-cy="gridPageTitle"]')).toHaveText('Second page')
	})

	test('Edit mode is read only while the page is being saved', async ({ userPage: { page, user } }) => {
		const table = await createTableViaApi(page.request, user, 'grid saving table')
		const contextId = await createApplicationViaApi(page.request, user, 'grid saving app', [table])
		const pageId = await createPageViaApi(page.request, user, contextId, 'Saving')
		await openApplication(page, contextId, `/page/${pageId}`)

		await page.locator('[data-cy="gridPageEdit"]').click()
		await page.locator('[data-cy="gridPageAddWidget"]').click()
		await page.locator('[data-cy="widgetFieldHeading"] input').fill('Saved slowly')
		await page.locator('[data-cy="addWidgetSubmit"]').click()

		let release: () => void = () => {}
		const held = new Promise<void>(resolve => { release = resolve })
		await page.route(new RegExp(`/contexts/${contextId}/pages/${pageId}(\\?|$)`), async route => {
			if (route.request().method() === 'PATCH') {
				await held
			}
			await route.continue()
		})
		await page.locator('[data-cy="gridPageSave"]').click()
		await expect(page.locator('[data-cy="gridPageSave"]')).toBeDisabled()
		await expect(page.locator('[data-cy="gridPageAddWidget"]')).toBeDisabled()
		await expect(page.locator('[data-cy="gridPageCancel"]')).toBeDisabled()
		await expect(page.getByRole('button', { name: /^Actions for / })).toHaveCount(0)

		release()
		await expect(page.locator('[data-cy="gridPageEdit"]')).toBeVisible()
		await expect(page.getByText('Saved slowly')).toBeVisible()
	})

	test('The focus moves to the button that takes the place of the one activated', async ({ userPage: { page, user } }) => {
		const table = await createTableViaApi(page.request, user, 'grid focus edit table')
		const contextId = await createApplicationViaApi(page.request, user, 'grid focus edit app', [table])
		const pageId = await createPageViaApi(page.request, user, contextId, 'Focus edit')
		await ocsRequest(page.request, user, {
			method: 'PATCH',
			url: `${API}/contexts/${contextId}/pages/${pageId}?format=json`,
			data: {
				version: 0,
				widgets: [
					{ type: 'text', title: 'First note', gridX: 0, gridY: 0, gridWidth: 6, gridHeight: 2, settings: { text: 'One' } },
					{ type: 'text', title: 'Second note', gridX: 6, gridY: 0, gridWidth: 6, gridHeight: 2, settings: { text: 'Two' } },
				],
			},
		})
		await openApplication(page, contextId, `/page/${pageId}`)

		await page.locator('[data-cy="gridPageEdit"]').focus()
		await page.keyboard.press('Enter')
		await expect(page.locator('[data-cy="gridPageAddWidget"]')).toBeFocused()

		const firstActions = page.locator('[data-cy="gridWidget"]', { hasText: 'First note' }).getByRole('button', { name: /^Actions for First note/ })
		const secondActions = page.locator('[data-cy="gridWidget"]', { hasText: 'Second note' }).getByRole('button', { name: 'Actions for Second note' })
		await firstActions.click()
		await page.getByRole('menuitem', { name: 'Configure widget' }).click()
		await page.locator('[data-cy="addWidgetModal"]').getByRole('textbox', { name: 'Title' }).fill('First note again')
		await page.locator('[data-cy="addWidgetSubmit"]').click()
		await expect(firstActions).toBeFocused()

		await firstActions.click()
		await page.getByRole('menuitem', { name: 'Remove widget' }).click()
		await expect(secondActions).toBeFocused()

		await page.locator('[data-cy="gridPageCancel"]').focus()
		await page.keyboard.press('Enter')
		await expect(page.locator('[data-cy="gridPageEdit"]')).toBeFocused()
	})

	test('Many pages wrap in the page bar on a phone without widening the view', async ({ userPage: { page, user } }) => {
		const table = await createTableViaApi(page.request, user, 'grid narrow table')
		const contextId = await createApplicationViaApi(page.request, user, 'grid narrow app', [table])
		let pageId = 0
		for (let index = 1; index <= 20; index++) {
			pageId = await createPageViaApi(page.request, user, contextId, `Narrow page ${index}`)
		}
		await page.setViewportSize({ width: 375, height: 800 })
		await openApplication(page, contextId, `/page/${pageId}`)
		await expect(page.locator('[data-cy="gridPage"]')).toBeVisible()

		const overflow = await page.locator('#app-content-vue').evaluate(element => element.scrollWidth - element.clientWidth)
		expect(overflow).toBeLessThanOrEqual(0)
	})

	test('Deleting a page names it as it is written', async ({ userPage: { page, user } }) => {
		const table = await createTableViaApi(page.request, user, 'grid delete table')
		const contextId = await createApplicationViaApi(page.request, user, 'grid delete app', [table])
		const pageId = await createPageViaApi(page.request, user, contextId, 'Q&A\'s plan')
		await openApplication(page, contextId, `/page/${pageId}`)

		await page.locator('[data-cy="gridPageActions"] button').first().click()
		await page.locator('[data-cy="gridPageDelete"]').click()
		const dialog = page.getByRole('dialog', { name: 'Delete page' })
		await expect(dialog).toContainText('Delete the page "Q&A\'s plan" and its widgets?')
		await dialog.getByRole('button', { name: 'Delete page' }).click()
		await expect(page).toHaveURL(new RegExp(`/application/${contextId}$`))
		await expect(page.locator('[data-cy="contextPageLink"]', { hasText: 'Q&A' })).toHaveCount(0)
	})

	test('Saving the widgets of a page does not load the application again', async ({ userPage: { page, user } }) => {
		const table = await createTableViaApi(page.request, user, 'grid save table')
		const contextId = await createApplicationViaApi(page.request, user, 'grid save app', [table])
		const pageId = await createPageViaApi(page.request, user, contextId, 'Saved once')
		await openApplication(page, contextId, `/page/${pageId}`)

		await page.locator('[data-cy="gridPageEdit"]').click()
		await page.locator('[data-cy="gridPageAddWidget"]').click()
		await page.locator('[data-cy="widgetFieldHeading"] input').fill('Just a widget')
		await page.locator('[data-cy="addWidgetSubmit"]').click()

		let reloaded = false
		page.on('request', request => {
			if (request.method() === 'GET' && new RegExp(`/contexts/${contextId}(\\?|$)`).test(request.url())) {
				reloaded = true
			}
		})
		const saved = page.waitForResponse(r => /\/pages\/\d+/.test(r.url()) && r.request().method() === 'PATCH')
		await page.locator('[data-cy="gridPageSave"]').click()
		expect((await saved).ok()).toBe(true)
		await expect(page.locator('[data-cy="gridPageEdit"]')).toBeVisible()
		await page.waitForTimeout(1000)
		expect(reloaded).toBe(false)
	})

	test('Opening a row while the start page is edited does not ask to discard the changes', async ({ userPage: { page, user } }) => {
		const table = await createTableViaApi(page.request, user, 'grid guard table')
		const rowId = await createRowViaApi(page.request, user, table, 'Guarded task')
		const contextId = await createApplicationViaApi(page.request, user, 'grid guard app', [table])
		await openApplication(page, contextId)
		const row = page.locator('[data-cy="tablesWidget"] tr', { hasText: 'Guarded task' })
		await expect(row).toBeVisible({ timeout: 30000 })

		await page.locator('[data-cy="gridPageEdit"]').click()
		await page.getByRole('button', { name: 'Widget actions' }).click()
		await page.locator('[data-cy="widgetTaller"] button').click()
		await page.keyboard.press('Escape')

		await row.locator('[data-cy="rowActionMenu"] button').click()
		await page.locator('[data-cy="editRowBtn"]').click()
		await expect(page).toHaveURL(new RegExp(`/application/${contextId}/row/${rowId}$`))
		await expect(page.locator('[data-cy="editRowModal"]')).toBeVisible()
		await expect(page.getByRole('dialog', { name: 'Discard unsaved changes?' })).toHaveCount(0)
		await expect(page.locator('[data-cy="gridPageSave"]')).toBeVisible()
	})

	test('An application opened after a missing one shows its own start page', async ({ userPage: { page, user } }) => {
		const first = await createTableViaApi(page.request, user, 'grid before missing table')
		const second = await createTableViaApi(page.request, user, 'grid after missing table')
		const firstId = await createApplicationViaApi(page.request, user, 'grid before missing app', [first])
		const secondId = await createApplicationViaApi(page.request, user, 'grid after missing app', [second])

		await openApplication(page, firstId)
		await expect(page.locator('[data-cy="tablesWidget"]').getByRole('heading', { name: 'grid before missing table' })).toBeVisible({ timeout: 30000 })
		await page.goto('/index.php/apps/tables/#/application/999999')
		await expect(page.getByText(/could not be found/)).toBeVisible({ timeout: 30000 })
		await page.goto(`/index.php/apps/tables/#/application/${secondId}`)
		const tablesWidget = page.locator('[data-cy="tablesWidget"]')
		await expect(tablesWidget.getByRole('heading', { name: 'grid after missing table' })).toBeVisible({ timeout: 30000 })
		await expect(tablesWidget.getByRole('heading', { name: 'grid before missing table' })).toHaveCount(0)
	})

	test('Going back to the start page loads each of its tables once, without a spinner', async ({ userPage: { page, user } }) => {
		const table = await createTableViaApi(page.request, user, 'grid back home table')
		const contextId = await createApplicationViaApi(page.request, user, 'grid back home app', [table])
		const pageId = await createPageViaApi(page.request, user, contextId, 'Away')
		await openApplication(page, contextId)
		const tablesWidget = page.locator('[data-cy="tablesWidget"]')
		await expect(tablesWidget.getByRole('heading', { name: 'grid back home table' })).toBeVisible({ timeout: 30000 })

		await page.locator('[data-cy="contextPageLink"]', { hasText: 'Away' }).click()
		await expect(page).toHaveURL(new RegExp(`/page/${pageId}$`))
		await expect(page.locator('[data-cy="gridPageTitle"]')).toHaveText('Away')

		const rowRequests: string[] = []
		page.on('request', request => {
			if (request.method() === 'GET' && new RegExp(`/row/table/${table}(\\?|$)`).test(request.url())) {
				rowRequests.push(request.url())
			}
		})
		await page.locator('[data-cy="contextStartPageLink"]').click()
		await expect(tablesWidget.getByRole('heading', { name: 'grid back home table' })).toBeVisible()
		await expect(tablesWidget.locator('.data-widget__loading')).toHaveCount(0)
		await page.waitForTimeout(1000)
		expect(rowRequests).toHaveLength(1)
	})
})
