/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { type APIRequestContext } from '@playwright/test'
import { test, expect } from '../support/fixtures'
import { ocsRequest, type TestUser } from '../support/api'

test.use({ viewport: { width: 1600, height: 1000 } })

const API = '/ocs/v2.php/apps/tables/api/2'

async function createApplicationWithPage(request: APIRequestContext, user: TestUser, name: string) {
	const table = await ocsRequest(request, user, { method: 'POST', url: '/index.php/apps/tables/api/1/tables', data: { title: name + ' table' } })
	const tableId = (await table.json()).id
	await ocsRequest(request, user, { method: 'PUT', url: `/ocs/v2.php/apps/tables/api/2/tables/${tableId}?format=json`, data: { description: 'What this table is for' } })
	const context = await ocsRequest(request, user, {
		method: 'POST',
		url: `${API}/contexts?format=json`,
		data: { name, iconName: 'star', description: '', nodes: [{ id: tableId, type: 0, permissions: 1 }] },
	})
	const contextId = (await context.json()).ocs.data.id
	const contextData = (await (await ocsRequest(request, user, { method: 'GET', url: `${API}/contexts/${contextId}?format=json` })).json()).ocs.data
	const page = await ocsRequest(request, user, { method: 'POST', url: `${API}/contexts/${contextData.id}/pages?format=json`, data: { title: 'Tabs' } })
	return { contextId: contextData.id, pageId: (await page.json()).ocs.data.id, nodeRelId: Number(Object.keys(contextData.nodes)[0]), tableTitle: name + ' table' }
}

test.describe('Tabs widget', () => {
	test('Each tab holds its own widgets, a table included', async ({ userPage: { page, user } }) => {
		const { contextId, pageId, nodeRelId, tableTitle } = await createApplicationWithPage(page.request, user, 'tabs view')
		const saved = await ocsRequest(page.request, user, {
			method: 'PATCH',
			url: `${API}/contexts/${contextId}/pages/${pageId}?format=json`,
			data: {
				widgets: [
					{ type: 'tabs', gridX: 0, gridY: 0, gridWidth: 12, gridHeight: 10, settings: { tabs: 'Notes\nData' } },
					{ type: 'text', gridX: 0, gridY: 0, gridWidth: 6, gridHeight: 2, settings: { text: 'In the first tab' }, parentIndex: 0, parentTab: 0 },
					{ type: 'data', gridX: 0, gridY: 0, gridWidth: 12, gridHeight: 6, source: { nodeRelId }, parentIndex: 0, parentTab: 1 },
				],
			},
		})
		expect(saved.ok()).toBe(true)

		await page.goto(`/index.php/apps/tables/#/application/${contextId}/page/${pageId}`)
		const tabs = page.locator('[data-cy="tabsWidget"]').getByRole('tab')
		await expect(tabs).toHaveCount(2, { timeout: 30000 })
		await expect(page.getByText('In the first tab')).toBeVisible()
		await expect(page.locator('h1', { hasText: tableTitle })).toHaveCount(0)

		// The keyboard moves between tabs, Enter opens one
		await tabs.nth(0).focus()
		await page.keyboard.press('ArrowRight')
		await expect(tabs.nth(1)).toBeFocused()
		await page.keyboard.press('Enter')
		await expect(tabs.nth(1)).toHaveAttribute('aria-selected', 'true')
		await expect(page.locator('h1', { hasText: tableTitle })).toBeVisible({ timeout: 30000 })
		await expect(page.getByText('In the first tab')).toHaveCount(0)

		// A table with a description shows it behind a button
		const toggle = page.locator('[data-cy="collapsibleDescriptionToggle"]')
		await expect(toggle).toHaveAttribute('aria-expanded', 'false')
		await toggle.click()
		await expect(toggle).toHaveAttribute('aria-expanded', 'true')
	})

	test('A widget is added to a tab in edit mode and stays there after a reload', async ({ userPage: { page, user } }) => {
		const { contextId, pageId } = await createApplicationWithPage(page.request, user, 'tabs edit')
		await page.goto(`/index.php/apps/tables/#/application/${contextId}/page/${pageId}`)

		await page.locator('[data-cy="gridPageEdit"]').click({ timeout: 30000 })
		await page.locator('[data-cy="gridPageAddWidget"]').click()
		await page.locator('[data-cy="addWidgetType"] input').click()
		await page.locator('.vs__dropdown-menu li').filter({ hasText: 'Tabs' }).click()
		await page.locator('[data-cy="widgetFieldTabs"] textarea').fill('First\nSecond')
		await page.locator('[data-cy="addWidgetSubmit"]').click()

		await page.locator('[data-cy="tabsWidget"]').getByRole('tab', { name: 'Second' }).click()
		await page.locator('[data-cy="tabsWidgetAdd"]').click()
		await page.locator('[data-cy="addWidgetType"] input').click()
		// Tabs go one level deep
		await expect(page.locator('.vs__dropdown-menu li').filter({ hasText: 'Tabs' })).toHaveCount(0)
		await page.locator('.vs__dropdown-menu li').filter({ hasText: 'Description' }).click()
		await page.locator('[data-cy="widgetFieldText"] textarea').fill('Only in the second tab')
		await page.locator('[data-cy="addWidgetSubmit"]').click()

		const response = page.waitForResponse(r => /\/pages\/\d+/.test(r.url()) && r.request().method() === 'PATCH')
		await page.locator('[data-cy="gridPageSave"]').click()
		const widgets = (await (await response).json()).ocs.data.widgets
		const tabsWidget = widgets.find((widget: { type: string }) => widget.type === 'tabs')
		expect(widgets.find((widget: { type: string }) => widget.type === 'text')).toMatchObject({ parentId: tabsWidget.id, parentTab: 1 })

		await page.reload()
		await expect(page.locator('[data-cy="tabsWidget"]').getByRole('tab')).toHaveCount(2, { timeout: 30000 })
		await expect(page.getByText('Only in the second tab')).toHaveCount(0)
		await page.locator('[data-cy="tabsWidget"]').getByRole('tab', { name: 'Second' }).click()
		await expect(page.getByText('Only in the second tab')).toBeVisible()
	})
})
