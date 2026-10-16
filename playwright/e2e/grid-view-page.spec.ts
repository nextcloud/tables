/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Page } from '@playwright/test'
import { test, expect } from '../support/fixtures'
import { ocsRequest, type TestUser } from '../support/api'

/**
 * Creates a grid view through the API and opens its page.
 *
 * @param page the page of the test user
 * @param user the test user
 * @param title title of the grid view
 */
async function openNewGridView(page: Page, user: TestUser, title: string) {
	const response = await ocsRequest(page.request, user, {
		method: 'POST',
		url: '/ocs/v2.php/apps/tables/api/2/views?format=json',
		data: { title },
	})
	const view = (await response.json()).ocs.data
	await page.goto(`/index.php/apps/tables/#/view/${view.id}`)
	await expect(page.locator('[data-cy="gridViewHeading"]')).toContainText(title)
	return view
}

/**
 * Adds a header widget with the given heading through the widget dialog.
 *
 * @param page the page of the test user
 * @param heading the heading of the header widget
 */
async function addHeaderWidget(page: Page, heading: string) {
	await page.locator('[data-cy="gridViewAddWidget"]').click()
	const dialog = page.locator('[data-cy="addWidgetModal"]')
	await dialog.getByRole('textbox', { name: 'Heading', exact: true }).fill(heading)
	await dialog.locator('[data-cy="addWidgetSubmit"]').click()
	await expect(dialog).toBeHidden()
}

/**
 * Saves the grid and waits for the server to store it.
 *
 * @param page the page of the test user
 * @param viewId the id of the grid view
 */
async function saveGrid(page: Page, viewId: number) {
	const saved = page.waitForResponse(response => response.url().includes(`/apps/tables/view/${viewId}`) && response.request().method() === 'PUT')
	await page.locator('[data-cy="gridViewSave"]').click()
	expect((await saved).status()).toBe(200)
}

test.describe('Grid view page', () => {
	test('A grid view created from the navigation opens its page and is listed', async ({ userPage: { page } }) => {
		await page.goto('/index.php/apps/tables')
		await page.locator('[data-cy="navigationCreateGridViewIcon"]').click({ force: true })

		const dialog = page.locator('[data-cy="gridViewSettingsModal"]')
		await dialog.getByRole('textbox', { name: 'Title', exact: true }).fill('Team page')
		await dialog.locator('[data-cy="gridViewSubmit"]').click()

		await expect(page).toHaveURL(/#\/view\/\d+$/)
		await expect(page.locator('[data-cy="gridViewHeading"]')).toContainText('Team page')
		await expect(page.locator('[data-cy="navigationViewItem"]').filter({ hasText: 'Team page' })).toBeVisible()
	})

	test('Widgets are added, configured without losing their content, and kept after a reload', async ({ userPage: { page, user } }) => {
		const view = await openNewGridView(page, user, 'Intake home')

		await page.locator('[data-cy="gridViewEdit"]').click()
		await addHeaderWidget(page, 'Welcome')
		await saveGrid(page, view.id)

		await page.reload()
		await expect(page.locator('[data-cy="gridWidget"]')).toContainText('Welcome')

		// Configuring the widget opens it with its own content, even after another type was picked before
		await page.locator('[data-cy="gridViewEdit"]').click()
		await page.locator('[data-cy="gridViewAddWidget"]').click()
		const dialog = page.locator('[data-cy="addWidgetModal"]')
		// An NcSelect opens from its indicator, like in the other specs
		await dialog.locator('[data-cy="addWidgetType"] .vs__open-indicator').click()
		await page.locator('.vs__dropdown-menu li').filter({ hasText: 'Description' }).first().click()
		// Escape would only leave the select, which keeps the focus after the pick
		await dialog.getByRole('button', { name: 'Close', exact: true }).click()
		await expect(dialog).toBeHidden()

		await page.locator('[data-cy="gridWidget"]').getByRole('button', { name: 'Widget actions' }).click()
		await page.getByRole('menuitem', { name: 'Configure widget' }).click()
		const heading = dialog.getByRole('textbox', { name: 'Heading', exact: true })
		await expect(heading).toHaveValue('Welcome')
		await heading.fill('Hello')
		await dialog.locator('[data-cy="addWidgetSubmit"]').click()
		await saveGrid(page, view.id)

		await page.reload()
		await expect(page.locator('[data-cy="gridWidget"]')).toContainText('Hello')
	})

	test('The title of a grid view is edited from the navigation', async ({ userPage: { page, user } }) => {
		const view = await openNewGridView(page, user, 'Old title')

		const item = page.locator('[data-cy="navigationViewItem"]').filter({ hasText: 'Old title' })
		await item.hover()
		await item.getByRole('button', { name: /Actions|Open menu/ }).first().click({ force: true })
		await page.getByRole('menuitem', { name: 'View settings' }).click()

		const dialog = page.locator('[data-cy="gridViewSettingsModal"]')
		await dialog.getByRole('textbox', { name: 'Title', exact: true }).fill('New title')
		const saved = page.waitForResponse(response => response.url().includes(`/apps/tables/view/${view.id}`) && response.request().method() === 'PUT')
		await dialog.locator('[data-cy="gridViewSubmit"]').click()
		expect((await saved).status()).toBe(200)

		await page.reload()
		await expect(page.locator('[data-cy="gridViewHeading"]')).toContainText('New title')
	})

	test('Leaving a page with unsaved changes asks first', async ({ userPage: { page, user } }) => {
		await openNewGridView(page, user, 'Draft page')

		await page.locator('[data-cy="gridViewEdit"]').click()
		await addHeaderWidget(page, 'Not saved')
		await page.goto('/index.php/apps/tables/#/')

		await expect(page.getByRole('dialog', { name: 'Discard unsaved changes?' })).toBeVisible()
		await page.getByRole('button', { name: 'Keep editing' }).click()
		await expect(page.locator('[data-cy="gridWidget"]')).toContainText('Not saved')
	})
})
