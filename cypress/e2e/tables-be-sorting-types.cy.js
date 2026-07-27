/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
let localUser

describe('Backend sorting different types in table and view', () => {

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

	const sortAndWait = (columnTitle, mode = 'ASC') => {
		cy.intercept('GET', '**/apps/tables/row/table/*').as('sortingRequest')
		cy.sortTableColumn(columnTitle, mode)
		cy.wait('@sortingRequest').then(({ request }) => {
			expect(request.query).to.have.property('sort')
		})
	}

	const expectRowOrderByTitles = (expectedTitles) => {
		cy.get('.custom-table table tbody tr').should('have.length', expectedTitles.length)
		cy.get('.custom-table table tbody tr').then($rows => {
			expectedTitles.forEach((title, index) => {
				expect($rows.eq(index).text()).to.contain(title)
			})
		})
	}

	it('Sort by text line column', () => {
		cy.createTable('Sort Types - Text Line')
		cy.createTextLineColumn('Name', null, null, true)
		cy.createTextLineColumn('TextLine', null, null, false)

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row 1')
		cy.fillInValueTextLine('TextLine', 'Charlie')
		saveCreateRow()

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row 2')
		cy.fillInValueTextLine('TextLine', 'Alice')
		saveCreateRow()

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row 3')
		cy.fillInValueTextLine('TextLine', 'Bob')
		saveCreateRow()

		sortAndWait('TextLine', 'ASC')
		expectRowOrderByTitles(['Row 2', 'Row 3', 'Row 1'])
	})

	it('Sort by text link column', () => {
		cy.createTable('Sort Types - Text Link')
		cy.createTextLineColumn('Name', null, null, true)
		cy.createTextLinkColumn('Link', ['Url'], false)

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row Z')
		cy.get('[data-cy="createRowModal"] [data-cy="Link"] .slot input').clear().type('https://z.example')
		saveCreateRow()

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row A')
		cy.get('[data-cy="createRowModal"] [data-cy="Link"] .slot input').clear().type('https://a.example')
		saveCreateRow()

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row B')
		cy.get('[data-cy="createRowModal"] [data-cy="Link"] .slot input').clear().type('https://b.example')
		saveCreateRow()

		sortAndWait('Link', 'ASC')
		expectRowOrderByTitles(['Row A', 'Row B', 'Row Z'])
	})

	it('Sort by selection column', () => {
		cy.createTable('Sort Types - Selection')
		cy.createTextLineColumn('Name', null, null, true)
		cy.createSelectionColumn('Selection', ['A', 'B', 'C'], null, false)

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row C')
		cy.fillInValueSelection('Selection', 'C')
		saveCreateRow()

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row A')
		cy.fillInValueSelection('Selection', 'A')
		saveCreateRow()

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row B')
		cy.fillInValueSelection('Selection', 'B')
		saveCreateRow()

		sortAndWait('Selection', 'ASC')
		expectRowOrderByTitles(['Row A', 'Row B', 'Row C'])
	})

	it('Sort by multi selection column', () => {
		cy.createTable('Sort Types - Selection Multi')
		cy.createTextLineColumn('Name', null, null, true)
		cy.createSelectionMultiColumn('SelectionMulti', ['A', 'B', 'C'], null, false)

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row C')
		cy.fillInValueSelectionMulti('SelectionMulti', ['C'])
		saveCreateRow()

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row A')
		cy.fillInValueSelectionMulti('SelectionMulti', ['A'])
		saveCreateRow()

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row B')
		cy.fillInValueSelectionMulti('SelectionMulti', ['B'])
		saveCreateRow()

		sortAndWait('SelectionMulti', 'ASC')
		expectRowOrderByTitles(['Row A', 'Row B', 'Row C'])
	})

	it('Sort by number column', () => {
		cy.createTable('Sort Types - Number')
		cy.createTextLineColumn('Name', null, null, true)
		cy.createNumberColumn('Number', null, null, null, null, null, null, false)

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row 30')
		cy.get('[data-cy="createRowModal"] [data-cy="Number"] input[type="number"]').clear().type('30')
		saveCreateRow()

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row 10')
		cy.get('[data-cy="createRowModal"] [data-cy="Number"] input[type="number"]').clear().type('10')
		saveCreateRow()

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row 20')
		cy.get('[data-cy="createRowModal"] [data-cy="Number"] input[type="number"]').clear().type('20')
		saveCreateRow()

		sortAndWait('Number', 'ASC')
		expectRowOrderByTitles(['Row 10', 'Row 20', 'Row 30'])
	})

	it('Sort by progress column', () => {
		cy.createTable('Sort Types - Progress')
		cy.createTextLineColumn('Name', null, null, true)
		cy.createNumberProgressColumn('Progress', null, false)

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row 90')
		cy.get('[data-cy="createRowModal"] [data-cy="Progress"] input[type="number"]').clear().type('90')
		saveCreateRow()

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row 10')
		cy.get('[data-cy="createRowModal"] [data-cy="Progress"] input[type="number"]').clear().type('10')
		saveCreateRow()

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row 50')
		cy.get('[data-cy="createRowModal"] [data-cy="Progress"] input[type="number"]').clear().type('50')
		saveCreateRow()

		sortAndWait('Progress', 'ASC')
		expectRowOrderByTitles(['Row 10', 'Row 50', 'Row 90'])
	})

	it('Sort by stars column', () => {
		cy.createTable('Sort Types - Stars')
		cy.createTextLineColumn('Name', null, null, true)
		cy.createNumberStarsColumn('Stars', null, false)

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row 5')
		for (let i = 0; i < 5; i++) {
			cy.get('[data-cy="createRowModal"] [aria-label="Increase stars"]').click()
		}
		saveCreateRow()

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row 1')
		cy.get('[data-cy="createRowModal"] [aria-label="Increase stars"]').click()
		saveCreateRow()

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row 3')
		for (let i = 0; i < 3; i++) {
			cy.get('[data-cy="createRowModal"] [aria-label="Increase stars"]').click()
		}
		saveCreateRow()

		sortAndWait('Stars', 'ASC')
		expectRowOrderByTitles(['Row 1', 'Row 3', 'Row 5'])
	})

	it('Sort by date column', () => {
		cy.createTable('Sort Types - Date')
		cy.createTextLineColumn('Name', null, null, true)
		cy.createDatetimeDateColumn('Date', false, false)

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row D3')
		cy.get('[data-cy="createRowModal"] [data-cy="Date"] input.native-datetime-picker--input').clear().type('2026-01-03')
		saveCreateRow()

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row D1')
		cy.get('[data-cy="createRowModal"] [data-cy="Date"] input.native-datetime-picker--input').clear().type('2026-01-01')
		saveCreateRow()

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row D2')
		cy.get('[data-cy="createRowModal"] [data-cy="Date"] input.native-datetime-picker--input').clear().type('2026-01-02')
		saveCreateRow()

		sortAndWait('Date', 'ASC')
		expectRowOrderByTitles(['Row D1', 'Row D2', 'Row D3'])
	})

	it('Sort by time column', () => {
		cy.createTable('Sort Types - Time')
		cy.createTextLineColumn('Name', null, null, true)
		cy.createDatetimeTimeColumn('Time', false, false)

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row T23')
		cy.get('[data-cy="createRowModal"] [data-cy="Time"] input.native-datetime-picker--input').clear().type('23:30')
		saveCreateRow()

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row T08')
		cy.get('[data-cy="createRowModal"] [data-cy="Time"] input.native-datetime-picker--input').clear().type('08:15')
		saveCreateRow()

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row T12')
		cy.get('[data-cy="createRowModal"] [data-cy="Time"] input.native-datetime-picker--input').clear().type('12:00')
		saveCreateRow()

		sortAndWait('Time', 'ASC')
		expectRowOrderByTitles(['Row T08', 'Row T12', 'Row T23'])
	})

	it('Sort by datetime column', () => {
		cy.createTable('Sort Types - DateTime')
		cy.createTextLineColumn('Name', null, null, true)
		cy.createDatetimeColumn('DateTime', false, false)

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row DT12')
		cy.get('[data-cy="createRowModal"] [data-cy="DateTime"] input.native-datetime-picker--input').clear().type('2026-01-01T12:00')
		saveCreateRow()

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row DT08')
		cy.get('[data-cy="createRowModal"] [data-cy="DateTime"] input.native-datetime-picker--input').clear().type('2026-01-01T08:00')
		saveCreateRow()

		openCreateRow()
		cy.fillInValueTextLine('Name', 'Row DT10')
		cy.get('[data-cy="createRowModal"] [data-cy="DateTime"] input.native-datetime-picker--input').clear().type('2026-01-01T10:00')
		saveCreateRow()

		sortAndWait('DateTime', 'ASC')
		expectRowOrderByTitles(['Row DT08', 'Row DT10', 'Row DT12'])
	})

})
