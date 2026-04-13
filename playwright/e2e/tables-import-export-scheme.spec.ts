/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Page } from '@playwright/test'
import { test, expect } from '../support/fixtures'
import * as fs from 'fs'
import { ensureNavigationOpen } from '../support/commands'

interface SchemeColumn extends Record<string, unknown> {
	id: number
	title: string
}

interface TableSummary {
	id: number
	title: string
}

interface SchemeColumnSetting {
	columnId: number
	order?: number
	readonly?: boolean
	mandatory?: boolean
}

interface SchemeColumnOrderEntry {
	columnId: number
	order?: number
}

interface SchemeSort {
	columnId: number
	mode: string
}

interface SchemeFilter {
	columnId: number
	operator: string
	value: string | number | boolean | null
}

interface SchemeView extends Record<string, unknown> {
	title: string
	description?: string
	emoji?: string
	columns?: number[]
	columnSettings?: SchemeColumnSetting[]
	sort?: SchemeSort[]
	filter?: SchemeFilter[][]
}

interface TableScheme extends Record<string, unknown> {
	title: string
	columns: SchemeColumn[]
	views: SchemeView[]
	tablesVersion?: string
	columnOrder?: SchemeColumnOrderEntry[]
	sort?: SchemeSort[]
}

async function getTutorialScheme(page: Page): Promise<TableScheme> {
	const tablesResponse = await page.request.get('/index.php/apps/tables/table')
	expect(tablesResponse.ok()).toBeTruthy()

	const tables = await tablesResponse.json() as TableSummary[]
	const tutorialTable = tables.find((table) => table.title.startsWith('Welcome to'))
	expect(tutorialTable).toBeTruthy()

	const schemeResponse = await page.request.get(
		`/index.php/apps/tables/api/1/tables/${tutorialTable?.id}/scheme`,
	)
	expect(schemeResponse.ok()).toBeTruthy()

	return JSON.parse(await schemeResponse.text()) as TableScheme
}

function omitSubFields<T extends Record<string, unknown>>(array: T[], fields: string[]) {
	return array.map((item) =>
		Object.keys(item)
			.filter((filterKey) => !fields.includes(filterKey))
			.reduce<Partial<T>>((obj, key) => {
				obj[key as keyof T] = item[key as keyof T]
				return obj
			}, {}),
	)
}

function normalizeViews(views: SchemeView[], columns: SchemeColumn[]) {
	const columnTitleById = new Map(columns.map((column) => [column.id, column.title]))

	return views.map((view) => ({
		title: view.title,
		description: view.description,
		emoji: view.emoji,
		columns: (view.columns ?? []).map((columnId) =>
			columnTitleById.get(columnId) ?? columnId,
		),
		columnSettings: (view.columnSettings ?? []).map((column) => ({
			title: columnTitleById.get(column.columnId) ?? column.columnId,
			order: column.order,
			readonly: column.readonly,
			mandatory: column.mandatory,
		})),
		sort: (view.sort ?? []).map((sort) => ({
			title: columnTitleById.get(sort.columnId) ?? sort.columnId,
			mode: sort.mode,
		})),
		filter: (view.filter ?? []).map((group) =>
			group.map((filter) => ({
				title: columnTitleById.get(filter.columnId) ?? filter.columnId,
				operator: filter.operator,
				value: filter.value,
			})),
		),
	}))
}

function normalizeTableLevelFields(scheme: TableScheme, columns: SchemeColumn[]) {
	const columnTitleById = new Map(columns.map((column) => [column.id, column.title]))
	scheme.columnOrder = (scheme.columnOrder ?? []).map((entry) => ({
		title: columnTitleById.get(entry.columnId) ?? entry.columnId,
		order: entry.order,
	})) as unknown as SchemeColumnOrderEntry[]
	scheme.sort = (scheme.sort ?? []).map((sort) => ({
		title: columnTitleById.get(sort.columnId) ?? sort.columnId,
		mode: sort.mode,
	})) as unknown as SchemeSort[]
}

function formatOperatorLabel(operator: string) {
	return operator
		.replaceAll('-', ' ')
		.replace(/^\w/, (letter) => letter.toUpperCase())
}

function formatFilterValue(value: SchemeFilter['value']) {
	if (value === '@checked') {
		return 'Checked'
	}
	if (value === '@unchecked') {
		return 'Unchecked'
	}
	return String(value)
}

function prepareSchemeForImport(scheme: TableScheme, title: string) {
	scheme.title = title

	const importedView = scheme.views[0]
	expect(importedView).toBeTruthy()

	if (!importedView.sort?.length) {
		const fallbackSortColumn = scheme.columns[0]
		expect(fallbackSortColumn).toBeTruthy()
		importedView.sort = [{
			columnId: fallbackSortColumn.id,
			mode: 'DESC',
		}]
	}

	return importedView
}

async function importSchemeViaUi(page: Page, scheme: TableScheme) {
	await page.goto('/index.php/apps/tables')
	await expect(page.locator('.icon-loading').first()).toBeHidden()
	await page.locator('[data-cy="navigationCreateTableIcon"]').click({ force: true })

	const createDialog = page.getByRole('dialog', { name: /Create table/i })
	await expect(createDialog).toBeVisible({ timeout: 10000 })

	await createDialog.locator('.tile').filter({ hasText: 'Import Scheme' }).click({ force: true })
	await createDialog.locator('[data-cy="createTableSubmitBtn"]').click()

	const importDialog = page.getByRole('dialog', { name: /Import scheme/i })
	await expect(importDialog).toBeVisible({ timeout: 10000 })

	await importDialog.locator('input[type=file]').setInputFiles({
		name: `${scheme.title}.json`,
		mimeType: 'application/json',
		buffer: Buffer.from(JSON.stringify(scheme)),
	})

	const importReqPromise = page.waitForResponse(
		(response) =>
			response.url().includes('/api/2/tables/scheme')
			&& response.request().method() === 'POST',
	)
	await importDialog.getByRole('button', { name: /^Import$/ }).click()
	await importReqPromise

	await page.goto('/index.php/apps/tables')
	await expect(page.locator('.icon-loading').first()).toBeHidden()
	await ensureNavigationOpen(page)
}

test.describe('Import Export Scheme', () => {
	test('Import table from scheme', async ({ userPage: { page } }) => {
		const scheme = await getTutorialScheme(page)
		const importedView = prepareSchemeForImport(scheme, 'Imported scheme table')

		await importSchemeViaUi(page, scheme)

		const todoListItem = page
			.locator('[data-cy="navigationTableItem"]')
			.filter({ hasText: scheme.title })
		await expect(todoListItem).toBeVisible({ timeout: 10000 })

		await todoListItem.locator('a').first().click()
		await todoListItem
			.getByRole('button', { name: /Expand menu|Collapse menu/i })
			.first()
			.click({ force: true })

		const importedViewRow = page
			.locator('main table tr')
			.filter({ hasText: importedView.title })
			.first()
		await expect(importedViewRow).toBeVisible({ timeout: 10000 })
		await importedViewRow.click()

		await page.locator('[data-cy="customTableAction"] button').first().click()
		const editViewBtn = page.getByText('Edit view', { exact: true })
		await editViewBtn.waitFor({ state: 'visible' })
		await editViewBtn.click()

		const visibleColumnIds = importedView.columns
			?? importedView.columnSettings
				?.filter((column) => column.order !== undefined)
				.sort((left, right) => (left.order ?? 0) - (right.order ?? 0))
				.map((column) => column.columnId)
			?? []
		const columns = visibleColumnIds
			.map((columnId) => scheme.columns.find((column) => column.id === columnId)?.title)
			.filter((columnTitle): columnTitle is string => Boolean(columnTitle))
		for (let index = 0; index < columns.length; index++) {
			const selectedColumn = page.locator('[data-cy="selectedViewColumnEl"]').nth(index)
			await expect(selectedColumn).toContainText(columns[index])
			await expect(selectedColumn.locator('input.checkbox-radio-switch__input')).toBeChecked()
		}

		const firstFilter = importedView.filter?.[0]?.[0]
		expect(firstFilter).toBeTruthy()
		const filterColumnTitle = scheme.columns.find((column) => column.id === firstFilter?.columnId)?.title
		expect(filterColumnTitle).toBeTruthy()
		await expect(page.locator('#settings-section_filter .v-select').nth(0)).toContainText(filterColumnTitle!)
		await expect(page.locator('#settings-section_filter .v-select').nth(1)).toContainText(
			formatOperatorLabel(firstFilter!.operator),
		)
		await expect(page.locator('#settings-section_filter .v-select').nth(2)).toContainText(
			formatFilterValue(firstFilter!.value),
		)

		const firstSort = importedView.sort?.[0]
		expect(firstSort).toBeTruthy()
		const sortColumnTitle = scheme.columns.find((column) => column.id === firstSort?.columnId)?.title
		expect(sortColumnTitle).toBeTruthy()
		await expect(page.locator('#settings-section_sort .v-select')).toContainText(sortColumnTitle!)
		await expect(
			page.locator(`#settings-section_sort .checkbox-radio-switch__input[value="${firstSort!.mode}"]`),
		).toBeChecked()
	})

	test('Export scheme to json', async ({ userPage: { page } }) => {
		const sourceScheme = await getTutorialScheme(page)
		prepareSchemeForImport(sourceScheme, 'Imported scheme export')
		await importSchemeViaUi(page, sourceScheme)

		const columnFieldsToIgnore = [
			'id',
			'tableId',
			'createdAt',
			'lastEditAt',
			'createdBy',
			'createdByDisplayName',
			'lastEditBy',
			'lastEditByDisplayName',
			'technicalName',
		]

		const todoListItem = page
			.locator('[data-cy="navigationTableItem"]')
			.filter({ hasText: sourceScheme.title })
		await expect(todoListItem).toBeVisible({ timeout: 10000 })
		await todoListItem.locator('a').first().click()
		await expect(page.locator('.row.first-row')).toBeVisible()

		await todoListItem.hover()
		const menuButton = todoListItem.locator('[aria-haspopup="menu"]').first()
		await menuButton.waitFor({ state: 'visible' })
		await menuButton.click({ force: true })

		const [download] = await Promise.all([
			page.waitForEvent('download'),
			page.locator('.action-button__text').filter({ hasText: 'Export' }).click(),
		])

		const downloadPath = await download.path()
		const contentRaw = fs.readFileSync(downloadPath, 'utf8')
		const content = JSON.parse(contentRaw) as TableScheme
		const sourceColumns = sourceScheme.columns
		const contentColumns = content.columns

		sourceScheme.columns = omitSubFields(sourceScheme.columns, columnFieldsToIgnore) as SchemeColumn[]
		content.columns = omitSubFields(content.columns, columnFieldsToIgnore) as SchemeColumn[]
		sourceScheme.views = normalizeViews(sourceScheme.views, sourceColumns) as SchemeView[]
		content.views = normalizeViews(content.views, contentColumns) as SchemeView[]
		normalizeTableLevelFields(sourceScheme, sourceColumns)
		normalizeTableLevelFields(content, contentColumns)
		content.tablesVersion = ''
		sourceScheme.tablesVersion = ''

		expect(JSON.stringify(sourceScheme)).toEqual(JSON.stringify(content))
	})
})
