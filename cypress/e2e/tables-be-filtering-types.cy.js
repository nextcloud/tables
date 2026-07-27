/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
let localUser

const today = new Date()
const tomorrow = new Date(today)
tomorrow.setUTCDate(tomorrow.getUTCDate() + 1)

const formatDate = (date) => date.toISOString().split('T')[0]

describe('Backend filtering in table header menu', () => {

	before(function() {
		cy.createRandomUser().then(user => {
			localUser = user
			cy.login(localUser)
		})
	})

	beforeEach(function() {
		cy.login(localUser)
		cy.visit('apps/tables')
	})

	const openCreateRow = () => {
		cy.get('[data-cy="createRowBtn"]').click()
	}

	const saveCreateRow = () => {
		cy.get('[data-cy="createRowSaveButton"]').click()
	}

	const expectVisibleRows = (rows) => {
		rows.forEach(row => {
			cy.contains('[data-cy="ncTable"] [data-cy="customTableRow"]', row).should('be.visible')
		})
	}

	const expectHiddenRows = (rows) => {
		rows.forEach(row => {
			cy.contains('[data-cy="ncTable"] [data-cy="customTableRow"]', row).should('not.exist')
		})
	}

	const captureBackendFilterRequest = () => {
		cy.intercept('GET', '**/apps/tables/row/table/*', req => {
			if (req.query?.customFilters) {
				req.alias = 'filterRequest'
			}
		})
	}

	const waitForBackendFilterRequest = () => {
		cy.wait('@filterRequest').then(({ request }) => {
			expect(request.query).to.have.property('customFilters')
		})
	}

	const openColumnMenu = (columnTitle) => {
		cy.contains('th', columnTitle).trigger('mouseover')
		cy.contains('th', columnTitle)
			.find('button[aria-label="Actions"], .action-item__menutoggle')
			.first()
			.click({ force: true })
		getActivePopper()
	}

	const getActivePopper = () => cy.get('.v-popper__popper:visible').last()

	const selectOperator = (operatorLabel) => {
		cy.contains('button', 'Select Operator').click({ force: true })
		getActivePopper().contains(operatorLabel).click({ force: true })
	}

	const submitTypedValue = (value) => {
		getActivePopper().find('.action-input .input-field__input').first().clear().type(value)
		getActivePopper().find('.action-input .input-field__trailing-button').first().click({ force: true })
	}

	const submitSelectionValue = (value) => {
		getActivePopper().find('li.action .action-input__multi .vs__open-indicator-button').first().click({ force: true })
		cy.get(`.v-popper__popper:visible ul.vs__dropdown-menu li span[title="${value}"]`).click({ force: true })
		getActivePopper().contains('button', 'Submit').click({ force: true })
		// close the floating menu to avoid capturing clicks in next interactions
		cy.get('body').click(0, 0)
	}

	const submitMagicValue = (value) => {
		getActivePopper().contains('button', value).click({ force: true })
	}

	const clickBackIfExists = () => {
		cy.get('body').then($body => {
			const backButton = $body
				.find('.v-popper__popper:visible button')
				.filter((_, el) => el.textContent?.trim() === 'Back')
				.first()

			if (backButton.length) {
				cy.wrap(backButton).click({ force: true })
			}
		})
	}

	const applyHeaderFilter = (columnTitle, operatorLabel, value = null, valueMode = 'none') => {
		captureBackendFilterRequest()
		openColumnMenu(columnTitle)
		// click Back if previous interaction left menu in previous used state
		clickBackIfExists()
		selectOperator(operatorLabel)

		if (valueMode === 'typed') {
			submitTypedValue(value)
		}

		if (valueMode === 'selection') {
			submitSelectionValue(value)
		}

		if (valueMode === 'magic') {
			submitMagicValue(value)
		}
		// close the floating menu to avoid capturing clicks in next interactions
		cy.get('body').click(0, 0)
		waitForBackendFilterRequest()
	}

	const addSingleFilterWithMagicValue = (columnLabel, operatorLabel, magicValueLabel) => {
		applyHeaderFilter(columnLabel, operatorLabel, magicValueLabel, 'magic')
	}

	it('Filter by text line column (contains)', () => {
		cy.createTable('Filter Types Header - Text Line')
		cy.createTextLineColumn('Name', null, null, true)
		cy.createTextLineColumn('TextLine', null, null, false)

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row Match')
		cy.fillInValueTextLine('TextLine', 'match-me')
		saveCreateRow()

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row Other')
		cy.fillInValueTextLine('TextLine', 'other')
		saveCreateRow()

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row Empty')
		saveCreateRow()

		applyHeaderFilter('TextLine', 'Contains', 'match', 'typed')
		expectVisibleRows(['Row Match'])
		expectHiddenRows(['Row Other', 'Row Empty'])
	})

	it('Filter by text link column (contains)', () => {
		cy.createTable('Filter Types Header - Text Link')
		cy.createTextLineColumn('Name', null, null, true)
		cy.createTextLinkColumn('Link', ['Url'], false)

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row A')
		cy.get('[data-cy="createRowModal"] [data-cy="Link"] .slot input').clear().type('https://a.example')
		saveCreateRow()

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row B')
		cy.get('[data-cy="createRowModal"] [data-cy="Link"] .slot input').clear().type('https://b.example')
		saveCreateRow()

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row Empty')
		saveCreateRow()

		applyHeaderFilter('Link', 'Contains', 'a.example', 'typed')
		expectVisibleRows(['Row A'])
		expectHiddenRows(['Row B', 'Row Empty'])
	})

	it('Filter by selection column (is equal)', () => {
		cy.createTable('Filter Types Header - Selection')
		cy.createTextLineColumn('Name', null, null, true)
		cy.createSelectionColumn('Selection', ['A', 'B', 'C'], null, false)

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row A')
		cy.fillInValueSelection('Selection', 'A')
		saveCreateRow()

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row B')
		cy.fillInValueSelection('Selection', 'B')
		saveCreateRow()

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row Empty')
		saveCreateRow()

		applyHeaderFilter('Selection', 'Is equal', 'B', 'selection')
		expectVisibleRows(['Row B'])
		expectHiddenRows(['Row A', 'Row Empty'])
	})

	it('Filter by selection multi column (contains)', () => {
		cy.createTable('Filter Types Header - Selection Multi')
		cy.createTextLineColumn('Name', null, null, true)
		cy.createSelectionMultiColumn('SelectionMulti', ['A', 'B', 'C'], null, false)

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row B')
		cy.fillInValueSelectionMulti('SelectionMulti', ['B'])
		saveCreateRow()

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row AC')
		cy.fillInValueSelectionMulti('SelectionMulti', ['A', 'C'])
		saveCreateRow()

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row Empty')
		saveCreateRow()

		applyHeaderFilter('SelectionMulti', 'Contains', 'B', 'selection')
		expectVisibleRows(['Row B'])
		expectHiddenRows(['Row AC', 'Row Empty'])
	})

	it('Selection multi: contains vs is equal behave differently', () => {
		cy.createTable('Filter Types Header - Selection Multi Operators')
		cy.createTextLineColumn('Name', null, null, true)
		cy.createSelectionMultiColumn('SelectionMulti', ['A', 'B', 'C'], null, false)

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row Only B')
		cy.fillInValueSelectionMulti('SelectionMulti', ['B'])
		saveCreateRow()

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row A and B')
		cy.fillInValueSelectionMulti('SelectionMulti', ['A', 'B'])
		saveCreateRow()

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row Only A')
		cy.fillInValueSelectionMulti('SelectionMulti', ['A'])
		saveCreateRow()

		// Contains uses contains-item logic and should match any row that includes B
		applyHeaderFilter('SelectionMulti', 'Contains', 'B', 'selection')
		expectVisibleRows(['Row Only B', 'Row A and B'])
		expectHiddenRows(['Row Only A'])

		//click Reset local adjustments
		cy.contains('button', 'Reset local adjustments').click({ force: true })
		cy.get('body').click(0, 0) // close menu

		// Re-open the same operator menu and add an is-equal rule.
		// For selection-multi, Is equal compares full rendered string,
		// so only the exact single-value row should match "B".
		applyHeaderFilter('SelectionMulti', 'Is equal', 'B', 'selection')
		expectVisibleRows(['Row Only B'])
		expectHiddenRows(['Row A and B', 'Row Only A'])
	})

	it('Filter by selection check column (checked)', () => {
		cy.createTable('Filter Types Header - Selection Check')
		cy.createTextLineColumn('Name', null, null, true)
		cy.createSelectionCheckColumn('Check', null, false)

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row Checked')
		cy.fillInValueSelectionCheck('Check')
		saveCreateRow()

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row Unchecked')
		saveCreateRow()

		addSingleFilterWithMagicValue('Check', 'Is equal', 'Checked')
		expectVisibleRows(['Row Checked'])
		expectHiddenRows(['Row Unchecked'])
	})

	it('Filter by number column (is empty)', () => {
		cy.createTable('Filter Types Header - Number')
		cy.createTextLineColumn('Name', null, null, true)
		cy.createNumberColumn('Number', null, null, null, null, null, null, false)

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row 10')
		cy.get('[data-cy="createRowModal"] [data-cy="Number"] input[type="number"]').clear().type('10')
		saveCreateRow()

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row Empty')
		saveCreateRow()

		applyHeaderFilter('Number', 'Is empty')
		expectVisibleRows(['Row Empty'])
		expectHiddenRows(['Row 10'])
	})

	it('Filter by progress column (is empty)', () => {
		cy.createTable('Filter Types Header - Progress')
		cy.createTextLineColumn('Name', null, null, true)
		cy.createNumberProgressColumn('Progress', null, false)

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row 90')
		cy.get('[data-cy="createRowModal"] [data-cy="Progress"] input[type="number"]').clear().type('90')
		saveCreateRow()

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row Empty')
		saveCreateRow()

		applyHeaderFilter('Progress', 'Is empty')
		expectVisibleRows(['Row Empty'])
		expectHiddenRows(['Row 90'])
	})

	//we do not have empty option
	it.skip('Filter by stars column (is empty)', () => {
		cy.createTable('Filter Types Header - Stars')
		cy.createTextLineColumn('Name', null, null, true)
		cy.createNumberStarsColumn('Stars', null, false)

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row 3')
		for (let i = 0; i < 3; i++) {
			cy.get('[data-cy="createRowModal"] [aria-label="Increase stars"]').click()
		}
		saveCreateRow()

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row Empty')
		saveCreateRow()

		applyHeaderFilter('Stars', 'Is empty')
		expectVisibleRows(['Row Empty'])
		expectHiddenRows(['Row 3'])
	})

	it('Filter by date column (is equal)', () => {
		cy.createTable('Filter Types Header - Date')
		cy.createTextLineColumn('Name', null, null, true)
		cy.createDatetimeDateColumn('Date', false, false)

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row Today')
		cy.get('[data-cy="createRowModal"] [data-cy="Date"] input.native-datetime-picker--input').clear().type(formatDate(today))
		saveCreateRow()

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row Tomorrow')
		cy.get('[data-cy="createRowModal"] [data-cy="Date"] input.native-datetime-picker--input').clear().type(formatDate(tomorrow))
		saveCreateRow()

		applyHeaderFilter('Date', 'Is equal', formatDate(today), 'typed')
		expectVisibleRows(['Row Today'])
		expectHiddenRows(['Row Tomorrow'])
	})

	it('Filter by time column (is equal)', () => {
		cy.createTable('Filter Types Header - Time')
		cy.createTextLineColumn('Name', null, null, true)
		cy.createDatetimeTimeColumn('Time', false, false)

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row 08')
		cy.get('[data-cy="createRowModal"] [data-cy="Time"] input.native-datetime-picker--input').clear().type('08:00')
		saveCreateRow()

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row 12')
		cy.get('[data-cy="createRowModal"] [data-cy="Time"] input.native-datetime-picker--input').clear().type('12:00')
		saveCreateRow()

		applyHeaderFilter('Time', 'Is equal', '08:00', 'typed')
		expectVisibleRows(['Row 08'])
		expectHiddenRows(['Row 12'])
	})

	it('Filter by datetime column (is equal)', () => {
		cy.createTable('Filter Types Header - DateTime')
		cy.createTextLineColumn('Name', null, null, true)
		cy.createDatetimeColumn('DateTime', false, false)

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row DT10')
		cy.get('[data-cy="createRowModal"] [data-cy="DateTime"] input.native-datetime-picker--input').clear().type('2026-01-01T10:00')
		saveCreateRow()

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row DT12')
		cy.get('[data-cy="createRowModal"] [data-cy="DateTime"] input.native-datetime-picker--input').clear().type('2026-01-01T12:00')
		saveCreateRow()

		applyHeaderFilter('DateTime', 'Is equal', '2026-01-01 10:00', 'typed')
		expectVisibleRows(['Row DT10'])
		expectHiddenRows(['Row DT12'])
	})

})
