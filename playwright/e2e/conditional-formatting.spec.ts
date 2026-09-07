/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test as base } from '@playwright/test'
import { test, expect } from '../support/fixtures'
import type { APIRequestContext, BrowserContext, Page } from '@playwright/test'
import { createRandomUser, type TestUser } from '../support/api'
import { login } from '../support/login'
import { fillInValueTextLine, openCreateRowModal } from '../support/commands'

const API = '/index.php/apps/tables/api/1'

/**
 * Call the Tables app API with basic auth, the way an API consumer would.
 */
async function api(
	request: APIRequestContext,
	user: TestUser,
	method: 'GET' | 'POST' | 'PUT' | 'DELETE',
	path: string,
	data?: unknown,
) {
	return request.fetch(API + path, {
		method,
		headers: {
			Authorization: 'Basic ' + Buffer.from(`${user.userId}:${user.password}`).toString('base64'),
			Accept: 'application/json',
			...(data === undefined ? {} : { 'Content-Type': 'application/json' }),
		},
		data,
	})
}

async function apiJson(
	request: APIRequestContext,
	user: TestUser,
	method: 'GET' | 'POST' | 'PUT' | 'DELETE',
	path: string,
	data?: unknown,
) {
	const res = await api(request, user, method, path, data)
	expect(res.ok(), `${method} ${path} → ${res.status()} ${await res.text()}`).toBeTruthy()
	return res.json()
}

const textCondition = (columnId: number, value: string) => ({
	groups: [{ conditions: [{ columnId, columnType: 'text-line', operator: 'contains', value }] }],
})

test.describe('Conditional formatting', () => {
	test.describe.configure({ mode: 'serial' })
	test.setTimeout(90000)

	let context: BrowserContext
	let page: Page
	let user: TestUser
	let stranger: TestUser
	let strangerApi: APIRequestContext
	let tableId: number
	let viewId: number
	let nameColumnId: number
	let scoreColumnId: number

	// @ts-expect-error - Playwright complex types mismatch in this environment
	base.beforeAll(async ({ browser, baseURL, playwright }) => {
		context = await browser.newContext({ baseURL })
		page = await context.newPage()

		const provisioning = await playwright.request.newContext({ baseURL })
		user = await createRandomUser(provisioning)
		stranger = await createRandomUser(provisioning)
		await provisioning.dispose()
		strangerApi = await playwright.request.newContext({ baseURL })

		const table = await apiJson(page.request, user, 'POST', '/tables', {
			title: 'Fmt test table',
			emoji: '🎨',
			template: 'custom',
		})
		tableId = table.id

		const name = await apiJson(page.request, user, 'POST', `/tables/${tableId}/columns`, {
			title: 'Name',
			type: 'text',
			subtype: 'line',
			mandatory: false,
		})
		nameColumnId = name.id
		const score = await apiJson(page.request, user, 'POST', `/tables/${tableId}/columns`, {
			title: 'Score',
			type: 'number',
			mandatory: false,
		})
		scoreColumnId = score.id

		const view = await apiJson(page.request, user, 'POST', `/tables/${tableId}/views`, {
			title: 'Fmt test view',
			emoji: '🎨',
		})
		viewId = view.id
		await apiJson(page.request, user, 'PUT', `/views/${viewId}`, {
			data: { columnSettings: [{ columnId: nameColumnId, order: 0 }, { columnId: scoreColumnId, order: 1 }] },
		})

		await context.clearCookies()
		await login(page, user)
	}, 120000)

	test.afterAll(async () => {
		await strangerApi?.dispose()
		await context?.close()
	})

	test.describe('API', () => {
		let rowRuleSetId: string
		let columnRuleSetId: string
		let ruleId: string

		test('creates a row-target rule set with generated id and sort order', async () => {
			const created = await apiJson(page.request, user, 'POST', `/views/${viewId}/formatting/rulesets`, {
				title: 'Highlights',
				targetType: 'row',
				targetCol: null,
				mode: 'first-match',
				enabled: true,
				rules: [],
			})
			rowRuleSetId = created.id

			expect(created.id).toMatch(/^[0-9a-f-]{36}$/)
			expect(created.sortOrder).toBe(0)
			expect(created.targetType).toBe('row')
			expect(created.broken).toBe(false)
			expect(created.rules).toEqual([])
		})

		test('rejects a column-target rule set without a target column', async () => {
			const res = await api(page.request, user, 'POST', `/views/${viewId}/formatting/rulesets`, {
				title: 'Column',
				targetType: 'column',
				mode: 'first-match',
			})
			expect(res.status()).toBe(400)
			expect((await res.json()).message).toContain('targetCol is required')
		})

		test('creates a column-target rule set', async () => {
			const created = await apiJson(page.request, user, 'POST', `/views/${viewId}/formatting/rulesets`, {
				title: 'Score color',
				targetType: 'column',
				targetCol: scoreColumnId,
				mode: 'all-matches',
			})
			columnRuleSetId = created.id

			expect(created.targetCol).toBe(scoreColumnId)
			expect(created.sortOrder).toBe(1)
		})

		test('creates a rule and exposes it through the view payload', async () => {
			const rule = await apiJson(page.request, user, 'POST', `/views/${viewId}/formatting/rulesets/${rowRuleSetId}/rules`, {
				title: 'Red when highlighted',
				enabled: true,
				condition: textCondition(nameColumnId, 'highlight'),
				style: { backgroundColor: '#ff0000', textColor: '#ffffff', fontWeight: 'bold' },
			})
			ruleId = rule.id

			expect(rule.id).toMatch(/^[0-9a-f-]{36}$/)
			expect(rule.format).toEqual({ backgroundColor: '#ff0000', textColor: '#ffffff', fontWeight: 'bold' })
			expect(rule.broken).toBe(false)

			const view = await apiJson(page.request, user, 'GET', `/views/${viewId}`)
			const stored = view.formatting.find((rs: { id: string }) => rs.id === rowRuleSetId)
			expect(stored.rules).toHaveLength(1)
			expect(stored.rules[0].id).toBe(ruleId)
			expect(stored.rules[0].condition.groups[0].conditions[0].columnId).toBe(nameColumnId)
		})

		test('rejects unknown operators, unknown style keys and foreign columns', async () => {
			const badOperator = await api(page.request, user, 'POST', `/views/${viewId}/formatting/rulesets/${rowRuleSetId}/rules`, {
				title: 'x',
				condition: { groups: [{ conditions: [{ columnId: nameColumnId, columnType: 'text-line', operator: 'no-such-operator' }] }] },
				style: {},
			})
			expect(badOperator.status()).toBe(400)
			expect((await badOperator.json()).message).toContain('Unknown operator')

			const badStyle = await api(page.request, user, 'POST', `/views/${viewId}/formatting/rulesets/${rowRuleSetId}/rules`, {
				title: 'x',
				condition: textCondition(nameColumnId, 'a'),
				style: { border: '1px' },
			})
			expect(badStyle.status()).toBe(400)
			expect((await badStyle.json()).message).toContain('Unknown style key')

			const foreignColumn = await api(page.request, user, 'POST', `/views/${viewId}/formatting/rulesets/${rowRuleSetId}/rules`, {
				title: 'x',
				condition: textCondition(999999, 'a'),
				style: {},
			})
			expect(foreignColumn.ok()).toBeFalsy()
		})

		test('reorders rule sets and persists the new sort order', async () => {
			await apiJson(page.request, user, 'PUT', `/views/${viewId}/formatting/reorder`, {
				orderedIds: [columnRuleSetId, rowRuleSetId],
			})

			const view = await apiJson(page.request, user, 'GET', `/views/${viewId}`)
			expect(view.formatting.map((rs: { id: string }) => rs.id)).toEqual([columnRuleSetId, rowRuleSetId])
			expect(view.formatting.map((rs: { sortOrder: number }) => rs.sortOrder)).toEqual([0, 1])
		})

		test('updates a rule set in place and keeps its id', async () => {
			const updated = await apiJson(page.request, user, 'PUT', `/views/${viewId}/formatting/rulesets/${columnRuleSetId}`, {
				title: 'Score color (off)',
				targetType: 'column',
				targetCol: scoreColumnId,
				mode: 'all-matches',
				enabled: false,
				rules: [],
			})
			expect(updated.id).toBe(columnRuleSetId)
			expect(updated.enabled).toBe(false)
			expect(updated.title).toBe('Score color (off)')
		})

		test('updates and deletes a rule', async () => {
			const updated = await apiJson(page.request, user, 'PUT', `/views/${viewId}/formatting/rulesets/${rowRuleSetId}/rules/${ruleId}`, {
				title: 'Renamed',
				enabled: false,
				condition: textCondition(nameColumnId, 'highlight'),
				style: { fontStyle: 'italic' },
			})
			expect(updated.id).toBe(ruleId)
			expect(updated.enabled).toBe(false)
			expect(updated.format).toEqual({ fontStyle: 'italic' })

			const missing = await api(page.request, user, 'PUT', `/views/${viewId}/formatting/rulesets/${rowRuleSetId}/rules/does-not-exist`, {
				title: 'x',
				condition: textCondition(nameColumnId, 'a'),
				style: {},
			})
			expect(missing.status()).toBe(404)

			await apiJson(page.request, user, 'DELETE', `/views/${viewId}/formatting/rulesets/${rowRuleSetId}/rules/${ruleId}`)
			const view = await apiJson(page.request, user, 'GET', `/views/${viewId}`)
			expect(view.formatting.find((rs: { id: string }) => rs.id === rowRuleSetId).rules).toEqual([])
		})

		test('refuses formatting changes from a user without manage rights', async () => {
			const res = await api(strangerApi, stranger, 'POST', `/views/${viewId}/formatting/rulesets`, {
				title: 'Nope',
				targetType: 'row',
				mode: 'first-match',
			})
			expect([403, 404]).toContain(res.status())
		})

		test('marks rules broken when a referenced column is deleted', async () => {
			const rule = await apiJson(page.request, user, 'POST', `/views/${viewId}/formatting/rulesets/${rowRuleSetId}/rules`, {
				title: 'Depends on Score',
				condition: { groups: [{ conditions: [{ columnId: scoreColumnId, columnType: 'number', operator: 'is-greater-than', value: 10 }] }] },
				style: { backgroundColor: '#00ff00' },
			})

			await apiJson(page.request, user, 'DELETE', `/columns/${scoreColumnId}`)

			const view = await apiJson(page.request, user, 'GET', `/views/${viewId}`)
			const stored = view.formatting
				.find((rs: { id: string }) => rs.id === rowRuleSetId)
				.rules.find((r: { id: string }) => r.id === rule.id)
			expect(stored.broken).toBe(true)
			expect(stored.enabled).toBe(false)

			await apiJson(page.request, user, 'DELETE', `/views/${viewId}/formatting/rulesets/${rowRuleSetId}`)
			await apiJson(page.request, user, 'DELETE', `/views/${viewId}/formatting/rulesets/${columnRuleSetId}`)
			const cleaned = await apiJson(page.request, user, 'GET', `/views/${viewId}`)
			expect(cleaned.formatting).toEqual([])
		})
	})

	test.describe('UI', () => {
		const highlightedRow = () => page.locator('tr[data-cy="customTableRow"]').filter({ hasText: 'highlight-me' }).first()
		const plainRow = () => page.locator('tr[data-cy="customTableRow"]').filter({ hasText: 'plain' }).first()
		const dialog = () => page.getByRole('dialog').filter({ hasText: 'Conditional Formatting' })

		const openView = async () => {
			await page.goto(`/index.php/apps/tables/#/view/${viewId}`)
			await expect(page.locator('[data-cy="createRowBtn"]').first()).toBeVisible()
		}

		test.beforeEach(async () => {
			await openView()
		})

		test('Create sample rows for the formatting checks', async () => {
			for (const name of ['highlight-me', 'plain']) {
				await openCreateRowModal(page)
				await fillInValueTextLine(page, 'Name', name)
				await page.locator('[data-cy="createRowSaveButton"]').click()
				await expect(page.locator('[data-cy="createRowModal"]')).toBeHidden()
			}
			await expect(highlightedRow()).toBeVisible()
			await expect(plainRow()).toBeVisible()
		})

		test('Format rules button is visible on a view', async () => {
			await expect(page.locator('button[aria-label="Format rules"]')).toBeVisible()
		})

		test('Open formatting manager modal from toolbar', async () => {
			await page.locator('button[aria-label="Format rules"]').click()
			await expect(dialog()).toBeVisible()
			await expect(dialog().getByText('No rule sets yet')).toBeVisible()
			await page.keyboard.press('Escape')
			await expect(dialog()).toBeHidden()
		})

		test('Create rule set and rule, verify row style applied', async () => {
			await page.locator('button[aria-label="Format rules"]').click()
			const modal = dialog()
			await expect(modal).toBeVisible()

			const createRuleSet = page.waitForResponse(r => r.url().includes('/formatting/rulesets') && r.request().method() === 'POST')
			await modal.getByRole('button', { name: 'Add rule set' }).click()
			await createRuleSet

			const editor = modal.locator('.rule-set-editor')
			await expect(editor).toBeVisible()
			await editor.getByRole('button', { name: 'Add rule', exact: true }).click()

			const ruleEditor = editor.locator('.rule-editor').first()
			await ruleEditor.getByPlaceholder('Rule name').fill('Highlight matches')
			await ruleEditor.getByRole('button', { name: 'Add condition', exact: true }).click()

			await ruleEditor.locator('[data-cy="filterEntryColumn"]').click()
			await page.locator('ul.vs__dropdown-menu li span[title="Name"]').click()
			await expect(ruleEditor.locator('[data-cy="filterEntryColumn"]')).toContainText('Name')
			const operatorSelect = ruleEditor.locator('[data-cy="filterEntryOperator"]')
			await expect(operatorSelect).toBeVisible()
			await operatorSelect.locator('input').fill('Contains')
			await operatorSelect.locator('input').press('Enter')
			await expect(operatorSelect).toContainText('Contains')

			const valueSelect = ruleEditor.locator('[data-cy="filterEntrySeachValue"]')
			await expect(valueSelect).toBeVisible()
			await valueSelect.locator('input').fill('highlight')
			await valueSelect.locator('input').press('Enter')
			await expect(valueSelect).toContainText('highlight')

			await ruleEditor.getByPlaceholder('#rrggbb').first().fill('#ff0000')
			await ruleEditor.getByPlaceholder('#rrggbb').first().press('Tab')

			const createRule = page.waitForResponse(r => /\/formatting\/rulesets\/[^/]+\/rules$/.test(r.url()) && r.request().method() === 'POST')
			await ruleEditor.getByRole('button', { name: 'Save rule', exact: true }).click()
			const ruleResponse = await createRule
			expect(ruleResponse.ok(), await ruleResponse.text()).toBeTruthy()

			await page.keyboard.press('Escape')
			await expect(modal).toBeHidden()

			await expect(highlightedRow()).toHaveCSS('background-color', 'rgb(255, 0, 0)')
			await expect(plainRow()).not.toHaveCSS('background-color', 'rgb(255, 0, 0)')
		})

		test('Toggle rule set enabled from column header popover', async () => {
			await expect(highlightedRow()).toHaveCSS('background-color', 'rgb(255, 0, 0)')

			const dot = page.locator('.formatting-column-popover__dot').first()
			await expect(dot).toBeVisible()
			await dot.hover()

			const popover = page.locator('.formatting-column-popover')
			await expect(popover).toBeVisible()
			await expect(popover.getByText('New rule set')).toBeVisible()

			const update = page.waitForResponse(r => /\/formatting\/rulesets\/[^/]+$/.test(r.url()) && r.request().method() === 'PUT')
			await popover.locator('.checkbox-radio-switch').first().click()
			await update

			await expect(highlightedRow()).not.toHaveCSS('background-color', 'rgb(255, 0, 0)')
		})

		test('Broken indicator visible for rule after its column is deleted', async () => {
			const view = await apiJson(page.request, user, 'GET', `/views/${viewId}`)
			const ruleSet = view.formatting[0]
			await apiJson(page.request, user, 'PUT', `/views/${viewId}/formatting/rulesets/${ruleSet.id}`, {
				...ruleSet,
				enabled: true,
			})
			const extra = await apiJson(page.request, user, 'POST', `/tables/${tableId}/columns`, {
				title: 'Extra',
				type: 'text',
				subtype: 'line',
				mandatory: false,
				selectedViewIds: [viewId],
			})
			await apiJson(page.request, user, 'POST', `/views/${viewId}/formatting/rulesets/${ruleSet.id}/rules`, {
				title: 'Depends on Extra',
				condition: textCondition(extra.id, 'x'),
				style: { fontWeight: 'bold' },
			})
			await apiJson(page.request, user, 'DELETE', `/columns/${extra.id}`)

			await page.reload()
			await openView()

			const dot = page.locator('.formatting-column-popover__dot').first()
			await expect(dot).toBeVisible()
			await dot.hover()
			await expect(page.locator('.formatting-column-popover').getByRole('img', { name: 'Broken rule' })).toBeVisible()
		})
	})
})
