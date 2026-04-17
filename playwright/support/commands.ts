/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, type Locator, type Page } from '@playwright/test'

function escapeRegExp(value: string) {
	return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

async function setCheckboxState(control: Locator, checked: boolean) {
	if ((await control.count()) === 0) {
		return
	}

	await control.first().waitFor({ state: 'visible', timeout: 5000 })
	if ((await control.first().isChecked()) !== checked) {
		await control.first().click({ force: true })
	}
}

async function waitForTransientModalsToClose(page: Page) {
	await page
		.locator('[data-cy="createRowModal"], [data-cy="editRowModal"]')
		.waitFor({ state: 'hidden', timeout: 5000 })
		.catch(() => {})
}

async function firstVisible(locator: Locator, timeout: number = 0) {
	const deadline = Date.now() + timeout

	do {
		const count = await locator.count()
		for (let index = 0; index < count; index++) {
			const candidate = locator.nth(index)
			if (await candidate.isVisible().catch(() => false)) {
				return candidate
			}
		}
		if (Date.now() >= deadline) {
			break
		}
		await new Promise(resolve => setTimeout(resolve, 100))
	} while (true)

	return null
}

async function navigateViaNavLink(page: Page, link: Locator) {
	await link.waitFor({ state: 'visible', timeout: 10000 })
	await link.scrollIntoViewIfNeeded()

	const href = await link.getAttribute('href')
	if (href) {
		await page.goto(href, { waitUntil: 'domcontentloaded', timeout: 60000 })
	} else {
		await link.click()
	}
}

async function openTableActionsMenu(page: Page) {
	await waitForTransientModalsToClose(page)
	await expect(page.locator('.icon-loading').first()).toBeHidden({
		timeout: 10000,
	})

	const menuButton = page.locator('[data-cy="customTableAction"] button').first()
	const anyMenuAction = page.locator(
		'[data-cy="dataTableEditTableBtn"], [data-cy="dataTableCreateViewBtn"], [data-cy="dataTableCreateColumnBtn"], [data-cy="dataTableShareBtn"], [data-cy="dataTableExportAllBtn"]',
	)

	for (let attempt = 1; attempt <= 3; attempt++) {
		await menuButton.waitFor({ state: 'visible', timeout: 10000 })
		await menuButton.scrollIntoViewIfNeeded()
		await page.locator('.toastify').first().waitFor({ state: 'hidden', timeout: 5000 }).catch(() => {})
		await menuButton.click()
		if (await firstVisible(anyMenuAction, 10000)) {
			return
		}

		await page.keyboard.press('Escape').catch(() => {})
		await page.waitForTimeout(attempt * 500)
	}

	throw new Error('Could not open the table actions menu')
}

function getTableActionLocator(page: Page, optionName: string) {
	switch (optionName) {
	case 'Edit table':
		return page.locator('[data-cy="dataTableEditTableBtn"]')
	case 'Create view':
		return page.locator('[data-cy="dataTableCreateViewBtn"]')
	case 'Create column':
		return page.locator('[data-cy="dataTableCreateColumnBtn"]')
	case 'Share':
		return page.locator('[data-cy="dataTableShareBtn"]')
	case 'Import':
		return page.locator('[data-cy="dataTableImportBtn"]')
	case 'Export all rows':
		return page.locator('[data-cy="dataTableExportAllBtn"]')
	case 'Export filtered rows':
		return page.locator('[data-cy="dataTableExportFilteredBtn"]')
	default:
		return null
	}
}

export async function ensureNavigationOpen(page: Page) {
	const openButton = page.getByRole('button', { name: /open navigation/i }).first()
	if (await openButton.isVisible().catch(() => false)) {
		await openButton.click({ force: true })
		await page.waitForTimeout(250)
	}

	const collapsedToggle = page
		.locator(
			".app-navigation-toggle-wrapper button[aria-expanded='false'], button.app-navigation-toggle[aria-expanded='false'], button[aria-label*='navigation'][aria-expanded='false']",
		)
		.first()
	if (await collapsedToggle.isVisible().catch(() => false)) {
		await collapsedToggle.click({ force: true })
		await page.waitForTimeout(250)
	}
}

export async function openCreateRowModal(page: Page) {
	await expect(page.locator('.icon-loading').first()).toBeHidden({
		timeout: 10000,
	})

	const createRowButton = page.locator('[data-cy="createRowBtn"]').filter({ hasText: 'Create row' }).first()
	for (let attempt = 1; attempt <= 2; attempt++) {
		await page.locator('.toastify').first().waitFor({ state: 'hidden', timeout: 5000 }).catch(() => {})
		await createRowButton.click({ force: true })

		if (await page.locator('[data-cy="createRowModal"]').isVisible().catch(() => false)) {
			return
		}

		await page.waitForTimeout(attempt * 250)
	}

	await expect(page.locator('[data-cy="createRowModal"]')).toBeVisible({ timeout: 10000 })
}

export async function createTable(page: Page, title: string) {
	await ensureNavigationOpen(page)
	await expect(page.locator('.icon-loading').first()).toBeHidden({
		timeout: 10000,
	})
	const createButton = page.getByRole('button', { name: /^Create table$/ }).first()
	if (!(await createButton.isVisible().catch(() => false))) {
		await ensureNavigationOpen(page)
	}
	await createButton.waitFor({ state: 'visible', timeout: 10000 })
	// Wait for any lingering toasts/overlays to clear
	await page.locator('.toastify').first().waitFor({ state: 'hidden', timeout: 5000 }).catch(() => {})
	await createButton.click()

	const createDialog = page.getByRole('dialog', { name: /^Create table$/ })
	await expect(createDialog).toBeVisible({
		timeout: 10000,
	})

	const input = createDialog.locator('input[type="text"]').first()
	await input.waitFor({ state: 'visible' })
	await input.clear()
	await input.fill(title)

	const customTableTile = createDialog
		.locator('.tile')
		.filter({ hasText: /Custom table/i })
		.first()
	await customTableTile.waitFor({ state: 'visible' })
	await customTableTile.click({ force: true })
	await createDialog.locator('[data-cy="createTableSubmitBtn"]').click()

	await expect(
		page.locator('h1').filter({ hasText: title }).first(),
	).toBeVisible({ timeout: 10000 })
}

export async function deleteTable(page: Page, title: string) {
	await page
		.locator('[data-cy="navigationTableItem"]')
		.filter({ hasText: title })
		.first()
		.click({ force: true })
	await openTableActionsMenu(page)
	await page.locator('[data-cy="dataTableEditTableBtn"]').click()
	await page
		.locator('[data-cy="editTableModal"] [data-cy="editTableDeleteBtn"]')
		.click()
	await page
		.locator('[data-cy="editTableModal"] [data-cy="editTableConfirmDeleteBtn"]')
		.click()

	await page.waitForTimeout(10)
	await expect(page.locator('.toastify.toast-success').first()).toBeVisible()
	await expect(
		page.locator('[data-cy="navigationTableItem"]').filter({ hasText: title }),
	).not.toBeVisible()
}

export async function deleteRow(page: Page, rowIndex: number) {
	const deleteResponse = page.waitForResponse(
		(response) =>
			/\/apps\/tables\/(row|view\/\d+\/row)\//.test(response.url())
      && response.request().method() === 'DELETE',
	).catch(() => null)
	await page
		.locator('[data-cy="ncTable"] [data-cy="editRowBtn"]')
		.nth(rowIndex)
		.click()
	await page.locator('[data-cy="editRowDeleteButton"]').click({ force: true })
	const confirmDeleteButton = page.locator('[data-cy="editRowDeleteConfirmButton"]')
	if (await confirmDeleteButton.isVisible().catch(() => false)) {
		await confirmDeleteButton.click({ force: true })
	}
	await deleteResponse
	await page
		.locator('[data-cy="editRowModal"]')
		.waitFor({ state: 'hidden', timeout: 10000 })
		.catch(async () => {
			if (await page.locator('[data-cy="editRowModal"]').isVisible().catch(() => false)) {
				await page.locator('[data-cy="editRowModal"] button[aria-label="Close"]').click().catch(() => {})
			}
		})
}

export async function createView(page: Page, title: string) {
	await expect(page.locator('.icon-loading').first()).toBeHidden({
		timeout: 10000,
	})
	await openTableActionsMenu(page)
	const createViewBtn = page
		.locator('[data-cy="dataTableCreateViewBtn"]')
		.filter({ hasText: 'Create view' })
	await createViewBtn.waitFor({ state: 'visible' })
	await createViewBtn.click({ force: true })

	const input = page.locator('[data-cy="viewSettingsDialogTitleInput"]')
	await expect(input).toBeVisible()
	await expect(input).toBeEnabled()
	await input.fill(title)

	const createResponsePromise = page.waitForResponse(
		(response) =>
			response.url().includes('/apps/tables/view')
      && response.request().method() === 'POST',
	)

	await page.locator('[data-cy="modifyViewBtn"]').click()

	await createResponsePromise

	await ensureNavigationOpen(page)
	await expect(
		page.locator('.app-navigation-entry-link span').filter({ hasText: title }),
	).toBeVisible()
}

export async function openCreateColumnModal(
	page: Page,
	isFirstColumn: boolean,
) {
	await expect(page.locator('.icon-loading').first()).toBeHidden({
		timeout: 10000,
	})
	if (isFirstColumn) {
		const createBtn = page
			.locator('.button-vue__text')
			.filter({ hasText: 'Create column' })
		await createBtn.waitFor({ state: 'visible' })
		await createBtn.click({ force: true })
	} else {
		await clickOnTableThreeDotMenu(page, 'Create column')
	}
	await expect(page.locator('[data-cy="columnTypeFormInput"]')).toBeVisible()
}

export async function createContext(
	page: Page,
	title: string,
	showInNav: boolean = false,
) {
	await ensureNavigationOpen(page)
	await page
		.getByRole('button', { name: /^Create application$/ })
		.first()
		.click({ force: true })
	await expect(page.locator('[data-cy="createContextModal"]')).toBeVisible()

	const input = page.locator('[data-cy="createContextTitle"]')
	await input.clear()
	await input.fill(title)

	if (showInNav) {
		await page
			.locator('[data-cy="createContextShowInNavSwitch"] input')
			.check({ force: true })
	}
	await page.locator('[data-cy="createContextSubmitBtn"]').click()

	await expect(
		page
			.locator('[data-cy="navigationContextItem"]')
			.filter({ hasText: title }),
	).toBeVisible()
	await expect(
		page.locator('h1').filter({ hasText: title }).first(),
	).toBeVisible()
}

export async function openContextEditModal(page: Page, title: string) {
	await ensureNavigationOpen(page)
	const contextItem = page
		.locator('[data-cy="navigationContextItem"]')
		.filter({ hasText: title })
		.first()
	await contextItem.waitFor({ state: 'visible', timeout: 5000 })
	await contextItem.scrollIntoViewIfNeeded()
	await contextItem.hover()
	const contextMenuButton = contextItem
		.getByRole('button', { name: /Actions|Open menu/i })
		.first()
	await contextMenuButton.waitFor({ state: 'visible', timeout: 5000 })
	await contextMenuButton.click({ force: true })
	const editBtn = page
		.locator('[data-cy="navigationContextEditBtn"]')
		.filter({ hasText: 'Edit application' })
	await editBtn.waitFor({ state: 'visible', timeout: 5000 })
	await editBtn.click({ force: true })
	await expect(page.getByRole('dialog', { name: /^Edit application$/ })).toBeVisible()
}

export async function clickOnTableThreeDotMenu(page: Page, optionName: string) {
	for (let attempt = 1; attempt <= 2; attempt++) {
		await openTableActionsMenu(page)

		const directAction = getTableActionLocator(page, optionName)
		const visibleDirectAction = directAction ? await firstVisible(directAction, 5000) : null
		if (visibleDirectAction) {
			await visibleDirectAction.click({ force: true })
			return
		}

		const menuItem = await firstVisible(page.getByRole('menuitem', { name: optionName }), 5000)
		if (menuItem) {
			await menuItem.click({ force: true })
			return
		}

		const fallbackItem = await firstVisible(page
			.locator('[role="menu"] button, [role="menu"] a, [role="menu"] li, .v-popper__popper button, .v-popper__popper a, .v-popper__popper li')
			.filter({ hasText: new RegExp(`^${escapeRegExp(optionName)}$`, 'i') }), 5000)
		if (fallbackItem) {
			await fallbackItem.click({ force: true })
			return
		}

		await page.keyboard.press('Escape').catch(() => {})
		await page.waitForTimeout(attempt * 250)
	}

	throw new Error(`Could not find table action menu item: ${optionName}`)
}

export async function sortTableColumn(
	page: Page,
	columnTitle: string,
	mode: 'ASC' | 'DESC' = 'ASC',
) {
	await expect(page.locator('.icon-loading').first()).toBeHidden({
		timeout: 10000,
	})
	const th = page.locator('th').filter({ hasText: columnTitle })
	await th.hover()
	await th.getByRole('button', { name: 'Actions' }).click()
	if (mode === 'ASC') {
		const sortBtn = page.locator('button[aria-label="Sort asc"]')
		await sortBtn.waitFor({ state: 'visible', timeout: 5000 })
		await sortBtn.click()
	} else {
		const sortBtn = page.locator('button[aria-label="Sort desc"]')
		await sortBtn.waitFor({ state: 'visible', timeout: 5000 })
		await sortBtn.click()
	}
}

export async function loadTable(page: Page, name: string) {
	await ensureNavigationOpen(page)
	const tableLink = page
		.locator(`[data-cy="navigationTableItem"] a[title="${name}"]`)
		.last()
	await navigateViaNavLink(page, tableLink)
	await expect(page.locator('.icon-loading').first()).toBeHidden()
}

export async function getTutorialTableName(page: Page) {
	return await page
		.locator('[data-cy="navigationTableItem"] a[title^="Welcome to"]')
		.getAttribute('title')
}

export async function loadView(page: Page, name: string) {
	await ensureNavigationOpen(page)
	await navigateViaNavLink(
		page,
		page.locator(`[data-cy="navigationViewItem"] a[title="${name}"]`),
	)
	await expect(page.locator('.icon-loading').first()).toBeHidden()
}

export async function loadContext(page: Page, title: string) {
	await ensureNavigationOpen(page)
	const contextItem = page
		.locator('[data-cy="navigationContextItem"]')
		.filter({ hasText: title })
		.first()
	await contextItem.waitFor({ state: 'visible', timeout: 10000 })
	await contextItem.scrollIntoViewIfNeeded()
	const contextLink = contextItem.locator('a').first()
	await navigateViaNavLink(page, contextLink)
	await expect(page.locator('.icon-loading').first()).toBeHidden()
}

export async function unifiedSearch(page: Page, term: string) {
	await page.locator('#unified-search').click()
	await page.locator('#unified-search__input').fill(term)
	await expect(
		page
			.locator('.unified-search__results .unified-search__result-line-one span')
			.filter({ hasText: new RegExp(term, 'i') }),
	).toBeVisible()
}

export async function createUsergroupColumn(
	page: Page,
	title: string,
	selectUsers: boolean,
	selectGroups: boolean,
	selectCircles: boolean,
	hasMultipleValues: boolean,
	defaultValue: string[],
	isFirstColumn: boolean,
) {
	await openCreateColumnModal(page, isFirstColumn)
	await page.locator('[data-cy="columnTypeFormInput"]').clear()
	await page.locator('[data-cy="columnTypeFormInput"]').fill(title)
	await page.locator('.columnTypeSelection .vs__open-indicator').click()
	await page
		.locator('.vs__dropdown-menu .multiSelectOptionLabel')
		.filter({ hasText: 'Users and groups' })
		.click()

	if (hasMultipleValues) {
		// NcCheckboxRadioSwitch type="switch" renders with role="switch", not "checkbox",
		// and the label is in a separate div, so use data-cy to find it
		const multiSwitchInput = page.locator('[data-cy="usergroupMultipleSwitch"] input')
		if (await multiSwitchInput.count() > 0 && !(await multiSwitchInput.isChecked())) {
			await multiSwitchInput.check({ force: true })
		}
	}

	await setCheckboxState(
		page.getByRole('checkbox', { name: /^Groups$/ }),
		selectGroups,
	)
	await setCheckboxState(
		page.getByRole('checkbox', { name: /^Teams$/ }),
		selectCircles,
	)
	await setCheckboxState(
		page.getByRole('checkbox', { name: /^Users$/ }),
		selectUsers,
	)

	for (const value of defaultValue) {
		const defaultSelectInput = page
			.locator('[data-cy="usergroupDefaultSelect"] input')
			.first()
		await defaultSelectInput.click()
		await page.waitForTimeout(250)
		// Avoid clear() as Backspace on empty vue-select multi-input deselects the last item
		const currentVal = await defaultSelectInput.inputValue()
		if (currentVal) {
			await defaultSelectInput.evaluate((input) => {
				(input as HTMLInputElement).value = ''
			})
			await defaultSelectInput.dispatchEvent('input')
		}
		await defaultSelectInput.pressSequentially(value)
		const dropdownItem = page.locator(`.vs__dropdown-menu [id="${value}"]`)
		await dropdownItem.waitFor({ state: 'visible', timeout: 10000 })
		await dropdownItem.click()
		await page.waitForTimeout(500)
		await expect(
			page.locator('[data-cy="usergroupDefaultSelect"] .vs__selected').filter({ hasText: value }).first(),
		).toBeVisible({ timeout: 5000 })
	}

	await page.locator('[data-cy="createColumnSaveBtn"]').click()
	await page.waitForTimeout(10)
	await expect(page.locator('.toastify.toast-success').first()).toBeVisible()
	await expect(
		page
			.locator('[data-cy="ncTable"] table tr th')
			.filter({ hasText: title })
			.first(),
	).toBeVisible()
}

export async function createTextLinkColumn(
	page: Page,
	title: string,
	ressourceProvider: string[],
	isFirstColumn: boolean,
) {
	await openCreateColumnModal(page, isFirstColumn)

	await page.locator('[data-cy="columnTypeFormInput"]').clear()
	await page.locator('[data-cy="columnTypeFormInput"]').fill(title)
	await page.locator('.columnTypeSelection').click()
	await page
		.locator('.vs__dropdown-menu li')
		.filter({ hasText: 'Link' })
		.waitFor({ state: 'visible' })
	await page
		.locator('.vs__dropdown-menu li')
		.filter({ hasText: 'Link' })
		.click()
	await expect(page.getByText('Allowed types')).toBeVisible()

	await setCheckboxState(
		page.getByRole('checkbox', { name: /^URL$/i }),
		false,
	)
	await setCheckboxState(
		page.getByRole('checkbox', { name: /^Files$/i }),
		false,
	)

	for (const provider of ressourceProvider) {
		await setCheckboxState(
			page.getByRole('checkbox', {
				name: new RegExp(`^${escapeRegExp(provider)}$`, 'i'),
			}),
			true,
		)
	}
	await page
		.locator('.modal-container button')
		.filter({ hasText: 'Save' })
		.click()

	await page.waitForTimeout(10)
	await expect(page.locator('.toastify.toast-success').first()).toBeVisible()
	await expect(
		page
			.locator('.custom-table table tr th .cell')
			.filter({ hasText: title })
			.first(),
	).toBeVisible()
}

export async function createSelectionColumn(
	page: Page,
	title: string,
	options: string[],
	defaultOption: string,
	isFirstColumn: boolean,
) {
	await openCreateColumnModal(page, isFirstColumn)

	await page.locator('[data-cy="columnTypeFormInput"]').clear()
	await page.locator('[data-cy="columnTypeFormInput"]').fill(title)
	await page.locator('.columnTypeSelection .vs__open-indicator').click()
	await page
		.locator('.vs__dropdown-menu .multiSelectOptionLabel')
		.filter({ hasText: 'Selection' })
		.click()
	// remove default option
	await page.locator('[data-cy="selectionOption"] button').first().click()
	await page.locator('[data-cy="selectionOption"] button').first().click()

	// add wanted option
	for (const option of options) {
		await page
			.locator('button')
			.filter({ hasText: 'Add option' })
			.first()
			.click()
		await page.locator('[data-cy="selectionOptionLabel"]').last().fill(option)
		if (defaultOption === option) {
			await page
				.locator('[data-cy="selectionOption"] .checkbox-content')
				.last()
				.click()
		}
	}
	await page
		.locator('.modal-container button')
		.filter({ hasText: 'Save' })
		.click()

	await page.waitForTimeout(10)
	await expect(page.locator('.toastify.toast-success').first()).toBeVisible()
	await expect(
		page
			.locator('.custom-table table tr th .cell')
			.filter({ hasText: title })
			.first(),
	).toBeVisible()
}

export async function createSelectionMultiColumn(
	page: Page,
	title: string,
	options: string[],
	defaultOptions: string[],
	isFirstColumn: boolean,
) {
	await openCreateColumnModal(page, isFirstColumn)

	await page.locator('[data-cy="columnTypeFormInput"]').clear()
	await page.locator('[data-cy="columnTypeFormInput"]').fill(title)
	await page.locator('.columnTypeSelection .vs__open-indicator').click()
	await page
		.locator('.vs__dropdown-menu .multiSelectOptionLabel')
		.filter({ hasText: 'Selection' })
		.click()
	await page
		.locator('[data-cy="createColumnMultipleSelectionSwitch"]')
		.filter({ hasText: 'Multiple selection' })
		.click()

	// remove default option
	await page.locator('[data-cy="selectionOption"] button').first().click()
	await page.locator('[data-cy="selectionOption"] button').first().click()

	// add wanted option
	if (options) {
		for (const option of options) {
			await page
				.locator('button')
				.filter({ hasText: 'Add option' })
				.first()
				.click()
			await page
				.locator('[data-cy="selectionOptionLabel"]')
				.last()
				.fill(option)
			if (defaultOptions?.includes(option)) {
				await page
					.locator('[data-cy="selectionOption"] .checkbox-content')
					.last()
					.click()
			}
		}
	}
	await page
		.locator('.modal-container button')
		.filter({ hasText: 'Save' })
		.click()

	await page.waitForTimeout(10)
	await expect(page.locator('.toastify.toast-success').first()).toBeVisible()
	await expect(
		page
			.locator('.custom-table table tr th .cell')
			.filter({ hasText: title })
			.first(),
	).toBeVisible()
}

export async function createTextLineColumn(
	page: Page,
	title: string,
	defaultValue: string,
	maxLength: string,
	isFirstColumn: boolean,
) {
	await openCreateColumnModal(page, isFirstColumn)
	await page.locator('[data-cy="columnTypeFormInput"]').clear()
	await page.locator('[data-cy="columnTypeFormInput"]').fill(title)
	if (defaultValue) {
		await page
			.locator('[data-cy="TextLineForm"] input')
			.first()
			.fill(defaultValue)
	}
	if (maxLength) {
		await page.locator('[data-cy="TextLineForm"] input').nth(1).fill(maxLength)
	}
	await page
		.locator('.modal-container button')
		.filter({ hasText: 'Save' })
		.click()
	await page.waitForTimeout(10)
	await expect(page.locator('.toastify.toast-success').first()).toBeVisible()
	await expect(
		page
			.locator('.custom-table table tr th .cell')
			.filter({ hasText: title })
			.first(),
	).toBeVisible()
}

export async function createDatetimeColumn(
	page: Page,
	title: string,
	setNow: boolean,
	isFirstColumn: boolean,
) {
	await openCreateColumnModal(page, isFirstColumn)
	await page.locator('[data-cy="columnTypeFormInput"]').clear()
	await page.locator('[data-cy="columnTypeFormInput"]').fill(title)
	await page.locator('.columnTypeSelection .vs__open-indicator').click()
	await page
		.locator('.vs__dropdown-menu .multiSelectOptionLabel')
		.filter({ hasText: 'Date and time' })
		.click()

	if (setNow) {
		await page.locator('[data-cy="datetimeFormNowSwitch"]').click()
	}

	await page
		.locator('.modal-container button')
		.filter({ hasText: 'Save' })
		.click()
	await page.waitForTimeout(10)
	await expect(page.locator('.toastify.toast-success').first()).toBeVisible()
	await expect(
		page
			.locator('.custom-table table tr th .cell')
			.filter({ hasText: title })
			.first(),
	).toBeVisible()
}

export async function createDatetimeDateColumn(
	page: Page,
	title: string,
	setNow: boolean,
	isFirstColumn: boolean,
) {
	await openCreateColumnModal(page, isFirstColumn)
	await page.locator('[data-cy="columnTypeFormInput"]').clear()
	await page.locator('[data-cy="columnTypeFormInput"]').fill(title)
	await page.locator('.columnTypeSelection .vs__open-indicator').click()
	await page
		.locator('.vs__dropdown-menu .multiSelectOptionLabel')
		.filter({ hasText: 'Date and time' })
		.click()
	await page
		.locator('[data-cy="createColumnDateSwitch"]')
		.filter({ hasText: 'Date' })
		.click()

	if (setNow) {
		await page.locator('[data-cy="datetimeDateFormTodaySwitch"]').click()
	}

	await page
		.locator('.modal-container button')
		.filter({ hasText: 'Save' })
		.click()
	await page.waitForTimeout(10)
	await expect(page.locator('.toastify.toast-success').first()).toBeVisible()
	await expect(
		page
			.locator('.custom-table table tr th .cell')
			.filter({ hasText: title })
			.first(),
	).toBeVisible()
}

export async function createDatetimeTimeColumn(
	page: Page,
	title: string,
	setNow: boolean,
	isFirstColumn: boolean,
) {
	await openCreateColumnModal(page, isFirstColumn)
	await page.locator('[data-cy="columnTypeFormInput"]').clear()
	await page.locator('[data-cy="columnTypeFormInput"]').fill(title)
	await page.locator('.columnTypeSelection .vs__open-indicator').click()
	await page
		.locator('.vs__dropdown-menu .multiSelectOptionLabel')
		.filter({ hasText: 'Date and time' })
		.click()
	await page
		.locator('[data-cy="createColumnTimeSwitch"]')
		.filter({ hasText: 'Time' })
		.click()

	if (setNow) {
		await page.locator('[data-cy="datetimeTimeFormNowSwitch"]').click()
	}

	await page
		.locator('.modal-container button')
		.filter({ hasText: 'Save' })
		.click()
	await page.waitForTimeout(10)
	await expect(page.locator('.toastify.toast-success').first()).toBeVisible()
	await expect(
		page
			.locator('.custom-table table tr th .cell')
			.filter({ hasText: title })
			.first(),
	).toBeVisible()
}

export async function createNumberColumn(
	page: Page,
	title: string,
	defaultValue: string | null,
	decimals: number | null,
	min: number | null,
	max: number | null,
	prefix: string | null,
	suffix: string | null,
	isFirstColumn: boolean,
) {
	await openCreateColumnModal(page, isFirstColumn)
	await page.locator('[data-cy="columnTypeFormInput"]').clear()
	await page.locator('[data-cy="columnTypeFormInput"]').fill(title)
	await page.locator('.columnTypeSelection .vs__open-indicator').click()
	await page
		.locator('.vs__dropdown-menu .multiSelectOptionLabel')
		.filter({ hasText: 'Number' })
		.click()

	if (defaultValue) {
		await page.locator('[data-cy="NumberForm"] input').nth(0).clear()
		await page
			.locator('[data-cy="NumberForm"] input')
			.nth(0)
			.fill(defaultValue)
	}
	if (decimals !== null && decimals !== undefined) {
		await page.locator('[data-cy="NumberForm"] input').nth(1).clear()
		await page
			.locator('[data-cy="NumberForm"] input')
			.nth(1)
			.fill('' + decimals)
	}
	if (min !== null && min !== undefined) {
		await page.locator('[data-cy="NumberForm"] input').nth(2).clear()
		await page
			.locator('[data-cy="NumberForm"] input')
			.nth(2)
			.fill('' + min)
	}
	if (max !== null && max !== undefined) {
		await page.locator('[data-cy="NumberForm"] input').nth(3).clear()
		await page
			.locator('[data-cy="NumberForm"] input')
			.nth(3)
			.fill('' + max)
	}
	if (prefix) {
		await page.locator('[data-cy="NumberForm"] input').nth(4).clear()
		await page.locator('[data-cy="NumberForm"] input').nth(4).fill(prefix)
	}
	if (suffix) {
		await page.locator('[data-cy="NumberForm"] input').nth(5).clear()
		await page.locator('[data-cy="NumberForm"] input').nth(5).fill(suffix)
	}
	await page
		.locator('.modal-container button')
		.filter({ hasText: 'Save' })
		.click()
	await page.waitForTimeout(10)
	await expect(page.locator('.toastify.toast-success').first()).toBeVisible()
	await expect(
		page
			.locator('.custom-table table tr th .cell')
			.filter({ hasText: title })
			.first(),
	).toBeVisible()
}

export async function createNumberProgressColumn(
	page: Page,
	title: string,
	defaultValue: string,
	isFirstColumn: boolean,
) {
	await openCreateColumnModal(page, isFirstColumn)
	await page.locator('[data-cy="columnTypeFormInput"]').clear()
	await page.locator('[data-cy="columnTypeFormInput"]').fill(title)
	await page.locator('.columnTypeSelection .vs__open-indicator').click()
	await page
		.locator('.vs__dropdown-menu .multiSelectOptionLabel')
		.filter({ hasText: 'Progress' })
		.click()

	if (defaultValue) {
		await page.locator('[data-cy="NumberProgressForm"] input').nth(0).clear()
		await page
			.locator('[data-cy="NumberProgressForm"] input')
			.nth(0)
			.fill(defaultValue)
	}
	await page
		.locator('.modal-container button')
		.filter({ hasText: 'Save' })
		.click()
	await page.waitForTimeout(10)
	await expect(page.locator('.toastify.toast-success').first()).toBeVisible()
	await expect(
		page
			.locator('.custom-table table tr th .cell')
			.filter({ hasText: title })
			.first(),
	).toBeVisible()
}

export async function createNumberStarsColumn(
	page: Page,
	title: string,
	defaultValue: number,
	isFirstColumn: boolean,
) {
	await openCreateColumnModal(page, isFirstColumn)
	await page.locator('[data-cy="columnTypeFormInput"]').clear()
	await page.locator('[data-cy="columnTypeFormInput"]').fill(title)
	await page.locator('.columnTypeSelection .vs__open-indicator').click()
	await page
		.locator('.vs__dropdown-menu .multiSelectOptionLabel')
		.filter({ hasText: 'Stars rating' })
		.click()

	if (defaultValue) {
		for (let n = 0; n < defaultValue; n++) {
			await page.locator('[data-cy="NumberStarsForm"] button').last().click()
		}
	}
	await page
		.locator('.modal-container button')
		.filter({ hasText: 'Save' })
		.click()
	await page.waitForTimeout(10)
	await expect(page.locator('.toastify.toast-success').first()).toBeVisible()
	await expect(
		page
			.locator('.custom-table table tr th .cell')
			.filter({ hasText: title })
			.first(),
	).toBeVisible()
}

export async function createSelectionCheckColumn(
	page: Page,
	title: string,
	defaultValue: boolean,
	isFirstColumn: boolean,
) {
	await openCreateColumnModal(page, isFirstColumn)
	await page.locator('[data-cy="columnTypeFormInput"]').clear()
	await page.locator('[data-cy="columnTypeFormInput"]').fill(title)
	await page.locator('.columnTypeSelection .vs__open-indicator').click()
	await page
		.locator('.vs__dropdown-menu .multiSelectOptionLabel')
		.filter({ hasText: 'Selection' })
		.click()
	await page
		.locator('[data-cy="createColumnYesNoSwitch"]')
		.filter({ hasText: 'Yes/No' })
		.click()

	if (defaultValue) {
		await page.locator('[data-cy="selectionCheckFormDefaultSwitch"]').click()
	}
	await page
		.locator('.modal-container button')
		.filter({ hasText: 'Save' })
		.click()
	await page.waitForTimeout(10)
	await expect(page.locator('.toastify.toast-success').first()).toBeVisible()
	await expect(
		page
			.locator('.custom-table table tr th .cell')
			.filter({ hasText: title })
			.first(),
	).toBeVisible()
}

export async function removeColumn(page: Page, title: string) {
	const columnHeader = page
		.locator('.custom-table table tr th')
		.filter({ hasText: new RegExp('^' + title + '$', 'i') })
		.or(page.locator('.custom-table table tr th').filter({ hasText: title })) // fallback
		.first()

	await columnHeader.hover()
	await columnHeader.getByRole('button', { name: 'Actions' }).first().click()
	await expect(page.locator('[data-cy="deleteColumnActionBtn"]')).toBeVisible({ timeout: 10000 })
	await page.locator('[data-cy="deleteColumnActionBtn"]').click()
	await page
		.locator('[data-cy="confirmDialog"] button')
		.filter({ hasText: 'Confirm' })
		.click()
	await expect(
		page.locator('.custom-table table tr th .cell').filter({ hasText: title }),
	).toBeHidden()
}

export async function fillInValueTextLine(
	page: Page,
	columnTitle: string,
	value: string,
) {
	const structuredInput = page.locator(
		`.modal__content [data-cy="${columnTitle}"] .slot input, .modal__content [data-cy="${columnTitle}"] input, .modal__content [data-cy="${columnTitle}"] textarea`,
	).first()

	if (await structuredInput.isVisible().catch(() => false)) {
		await structuredInput.fill(value)
		return
	}

	const visibleDialogInput = page
		.getByRole('dialog')
		.filter({ has: page.getByText(new RegExp(`^${escapeRegExp(columnTitle)}$`, 'i')) })
		.getByRole('textbox')
		.first()

	if (await visibleDialogInput.isVisible().catch(() => false)) {
		await visibleDialogInput.fill(value)
		return
	}

	const fallbackInput = page.getByRole('dialog').getByRole('textbox').first()
	await fallbackInput.waitFor({ state: 'visible', timeout: 10000 })
	await fallbackInput.fill(value)
}

export async function fillInValueSelection(
	page: Page,
	columnTitle: string,
	optionLabel: string,
) {
	await page
		.locator(`.modal__content [data-cy="${columnTitle}"] .slot input`)
		.click()
	await page
		.locator(`ul.vs__dropdown-menu li span[title="${optionLabel}"]`)
		.click()
}

export async function fillInValueSelectionMulti(
	page: Page,
	columnTitle: string,
	optionLabels: string[],
) {
	for (const item of optionLabels) {
		await page
			.locator(`.modal__content [data-cy="${columnTitle}"] .slot input`)
			.click()
		await page.locator(`ul.vs__dropdown-menu li span[title="${item}"]`).click()
	}
}

export async function fillInValueSelectionCheck(
	page: Page,
	columnTitle: string,
) {
	await page
		.locator(
			`.modal__content [data-cy="${columnTitle}"] [data-cy="selectionCheckFormSwitch"]`,
		)
		.click()
}
