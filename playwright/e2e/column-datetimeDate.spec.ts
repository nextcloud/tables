/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test, expect } from '../support/fixtures'
import { createDatetimeDateColumn, createTable, loadTable, removeColumn } from '../support/commands'

const columnTitle = 'date'
const tableTitle = 'Test datetimeDate'

test.describe('Test column ' + columnTitle, () => {

	test('Manage date column', async ({ userPage: { page } }) => {
		await page.goto('/index.php/apps/tables')
		await createTable(page, tableTitle)

		await loadTable(page, tableTitle)
		await createDatetimeDateColumn(page, columnTitle, false, true)

		// insert row
		await page.locator('button').filter({ hasText: 'Create row' }).click()
		await page.locator('.modal__content input').first().clear()
		await page.locator('.modal__content input').first().fill('2023-12-24')
		await page.locator('button').filter({ hasText: 'Save' }).click()

		await expect(page.locator('.custom-table table tr td div').filter({ hasText: '24' }).first()).toBeVisible()
		await expect(page.locator('.custom-table table tr td div').filter({ hasText: 'Dec' }).first()).toBeVisible()
		await expect(page.locator('.custom-table table tr td div').filter({ hasText: '2023' }).first()).toBeVisible()

		// delete row
		await page.locator('.NcTable tr td button').first().click()
		await page.locator('button').filter({ hasText: 'Delete' }).click()
		await page.locator('button').filter({ hasText: /I really/ }).click({ force: true })

		await removeColumn(page, columnTitle)
	})

	test('Insert and test rows - default now', async ({ userPage: { page } }) => {
		await page.goto('/index.php/apps/tables')
		await createTable(page, tableTitle)
		await page.addInitScript(() => {
				type DateArgs =
					| []
					| [value: string | number]
					| [year: number, monthIndex: number, date?: number, hours?: number, minutes?: number, seconds?: number, ms?: number]

				const mockDate = new Date(2023, 11, 24, 7, 21)
				globalThis.Date = class extends Date {

					constructor(...args: DateArgs) {
						if (args.length === 0) {
							super(mockDate.getTime())
						} else {
							super(...args)
						}
					}

				} as DateConstructor
				globalThis.Date.now = () => mockDate.getTime()
		})

		await page.reload()

		await loadTable(page, tableTitle)
		await createDatetimeDateColumn(page, columnTitle, true, true)

		await page.locator('button').filter({ hasText: 'Create row' }).click()
		await expect(page.locator('.modal__content input').first()).toHaveValue(/2023-12-24/)

		await page.locator('button').filter({ hasText: 'Save' }).click()

		await expect(page.locator('.custom-table table tr td div').filter({ hasText: '24' }).first()).toBeVisible()
		await expect(page.locator('.custom-table table tr td div').filter({ hasText: 'Dec' }).first()).toBeVisible()
		await expect(page.locator('.custom-table table tr td div').filter({ hasText: '2023' }).first()).toBeVisible()
	})
})
