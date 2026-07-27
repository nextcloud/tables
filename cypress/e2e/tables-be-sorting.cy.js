/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
let localUser

describe('Backend sorting in table and view', () => {

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

	it('Table header sorting - single column ascending', () => {
		cy.createTable('Sorting Table')
		cy.createTextLineColumn('Name', null, null, true)
		cy.createNumberColumn('Score')

		// Create test rows with unsorted data
		cy.get('[data-cy="createRowBtn"]').click()
		cy.get('[data-cy="createRowModal"] input').first().type('Alice')
		cy.get('[data-cy="createRowSaveButton"]').click()

		cy.get('[data-cy="createRowBtn"]').click()
		cy.get('[data-cy="createRowModal"] input').first().type('Charlie')
		cy.get('[data-cy="createRowSaveButton"]').click()

		cy.get('[data-cy="createRowBtn"]').click()
		cy.get('[data-cy="createRowModal"] input').first().type('Bob')
		cy.get('[data-cy="createRowSaveButton"]').click()

		// Intercept backend sorting request
		cy.intercept('GET', '**/apps/tables/row/table/*').as('sortingRequest')

		// Sort by Name column ascending
		cy.sortTableColumn('Name', 'ASC')

		// Wait for backend request to complete
		cy.wait('@sortingRequest')

		// Verify sorting after backend confirms
		cy.get('.custom-table table tbody tr').then($rows => {
			expect($rows.eq(0).text()).to.contain('Alice')
			expect($rows.eq(1).text()).to.contain('Bob')
			expect($rows.eq(2).text()).to.contain('Charlie')
		})
	})

	it('Table header sorting - single column descending', () => {
		cy.createTable('Sorting Table Desc')
		cy.createTextLineColumn('Name', null, null, true)

		// Create test rows
		cy.get('[data-cy="createRowBtn"]').click()
		cy.get('[data-cy="createRowModal"] input').first().type('Alice')
		cy.get('[data-cy="createRowSaveButton"]').click()

		cy.get('[data-cy="createRowBtn"]').click()
		cy.get('[data-cy="createRowModal"] input').first().type('Charlie')
		cy.get('[data-cy="createRowSaveButton"]').click()

		cy.get('[data-cy="createRowBtn"]').click()
		cy.get('[data-cy="createRowModal"] input').first().type('Bob')
		cy.get('[data-cy="createRowSaveButton"]').click()

		// Intercept backend sorting request
		cy.intercept('GET', '**/apps/tables/row/table/*').as('sortingDescRequest')

		// Sort by Name column descending
		cy.sortTableColumn('Name', 'DESC')

		// Wait for backend request to complete
		cy.wait('@sortingDescRequest')

		// Verify sorting after backend confirms
		cy.get('.custom-table table tbody tr').then($rows => {
			expect($rows.eq(0).text()).to.contain('Charlie')
			expect($rows.eq(1).text()).to.contain('Bob')
			expect($rows.eq(2).text()).to.contain('Alice')
		})
	})

	it('Sort by number column - ascending order', () => {
		cy.createTable('Number Sorting Table')
		cy.createTextLineColumn('Name', null, null, true)
		cy.createNumberColumn('Value')

		// Create rows with numbers in random order
		cy.get('[data-cy="createRowBtn"]').click()
		cy.get('[data-cy="createRowModal"] input').first().type('First')
		cy.get('[data-cy="createRowModal"] input[type="number"]').first().clear().type('30')
		cy.get('[data-cy="createRowSaveButton"]').click()

		cy.get('[data-cy="createRowBtn"]').click()
		cy.get('[data-cy="createRowModal"] input').first().type('Second')
		cy.get('[data-cy="createRowModal"] input[type="number"]').first().clear().type('10')
		cy.get('[data-cy="createRowSaveButton"]').click()

		cy.get('[data-cy="createRowBtn"]').click()
		cy.get('[data-cy="createRowModal"] input').first().type('Third')
		cy.get('[data-cy="createRowModal"] input[type="number"]').first().clear().type('20')
		cy.get('[data-cy="createRowSaveButton"]').click()

		// Intercept backend sorting request
		cy.intercept('GET', '**/apps/tables/row/table/*').as('sortingRequest')

		// Sort by number column ascending
		cy.sortTableColumn('Value', 'ASC')

		// Wait for backend request to complete
		cy.wait('@sortingRequest')

		// Verify sorting by number is applied after backend confirms
		cy.get('.custom-table table tbody tr').should('have.length', 3)
		cy.get('.custom-table table tbody tr').then($rows => {
			expect($rows.eq(0).text()).to.contain('Second')
			expect($rows.eq(1).text()).to.contain('Third')
			expect($rows.eq(2).text()).to.contain('First')
		})
	})

	it('View preset sorting - verify saved sort persists', () => {
		cy.createTable('View Sorting Table')
		cy.createTextLineColumn('Title', null, null, true)
		cy.createTextLineColumn('Category')

		// Create test rows
		cy.get('[data-cy="createRowBtn"]').click()
		cy.get('[data-cy="createRowModal"] input').first().type('Item A')
		cy.get('[data-cy="createRowSaveButton"]').click()

		cy.get('[data-cy="createRowBtn"]').click()
		cy.get('[data-cy="createRowModal"] input').first().type('Item Z')
		cy.get('[data-cy="createRowSaveButton"]').click()

		// Create a view and configure sorting
		cy.createView('Sorted View')
		cy.get('[data-cy="customTableAction"] button').click()
		cy.get('.v-popper__popper li button span').contains('Edit view').click({ force: true })
		cy.get('[data-cy="viewSettingsDialogTitleInput"]').should('be.visible')

		// Open view settings and configure sort
		cy.get('#settings-section_sort').should('exist')
		cy.get('#settings-section_sort button').contains('Add new sorting rule').click({ force: true })
		cy.get('#settings-section_sort .sort-entry').should('have.length.at.least', 1)
		cy.get('#settings-section_sort .sort-entry .v-select .vs__open-indicator-button').first().click({ force: true })
		cy.contains('.vs__dropdown-menu li', 'Title').click({ force: true })

		// Verify sort direction can be set
		cy.get('#settings-section_sort .sort-entry .mode-switch .checkbox-radio-switch__input[value="ASC"]').should('exist')

		// Save view settings
		cy.get('[data-cy="modifyViewBtn"]').click()

		// Verify view is created with sorting
		cy.get('[data-cy="navigationViewItem"]').contains('Sorted View').should('exist')
	})

	it.skip('Sorting persistence - table vs view', () => {
		// Create first table
		cy.createTable('First Table')
		cy.createTextLineColumn('Data', null, null, true)

		// Create second table and view
		cy.createTable('Second Table')
		cy.createTextLineColumn('Content', null, null, true)

		// Load first table and apply sort
		cy.loadTable('First Table')
		cy.get('[data-cy="createRowBtn"]').click()
		cy.get('[data-cy="createRowModal"] input').first().type('A')
		cy.get('[data-cy="createRowSaveButton"]').click()

		cy.get('[data-cy="createRowBtn"]').click()
		cy.get('[data-cy="createRowModal"] input').first().type('B')
		cy.get('[data-cy="createRowSaveButton"]').click()

		// Intercept backend sorting request
		cy.intercept('GET', '**/apps/tables/row/table/*').as('sortingRequest')

		cy.sortTableColumn('Data', 'DESC')

		// Wait for backend request to complete
		cy.wait('@sortingRequest')

		// Reset local adjustments is view-specific and should not be shown on tables
		cy.contains('button', 'Reset local adjustments').should('not.exist')

		// Navigate to second table - local sort should be reset
		cy.loadTable('Second Table')
		cy.get('.info').contains('Reset local adjustments').should('not.exist')

		// Navigate back to first table - reset action should still not be visible on tables
		cy.loadTable('First Table')
		cy.contains('button', 'Reset local adjustments').should('not.exist')
	})

	it('Multiple sort rules in view settings', () => {
		cy.createTable('Multi Sort Table')
		cy.createTextLineColumn('Category', null, null, true)
		cy.createTextLineColumn('Name', null, null, false)
		cy.createNumberColumn('Priority')

		// Create view
		cy.createView('Multi Sort View')

		// Navigate to view settings
		cy.get('[data-cy="navigationViewItem"]').contains('Multi Sort View').click({ force: true })
		cy.get('[data-cy="customTableAction"] button').click()
		cy.get('.v-popper__popper li button span').contains('Edit view').click({ force: true })

		// Configure primary sort
		cy.get('#settings-section_sort button').contains('Add new sorting rule').click({ force: true })
		cy.get('#settings-section_sort .sort-entry').should('have.length', 1)
		cy.get('#settings-section_sort .sort-entry .v-select .vs__open-indicator-button').first().click({ force: true })
		cy.contains('.vs__dropdown-menu li', 'Category').click({ force: true })
		cy.get('#settings-section_sort .sort-entry .mode-switch .checkbox-radio-switch__input[value="DESC"]').check({ force: true })
		cy.get('#settings-section_sort .sort-entry .mode-switch .checkbox-radio-switch__input[value="DESC"]').should('be.checked')

		// Save settings
		cy.get('[data-cy="modifyViewBtn"]').click()

		// Verify view loads without errors
		cy.get('[data-cy="ncTable"]').should('exist')
	})

	it('Sort with text search applied', () => {
		cy.createTable('Search Sort Table')
		cy.createTextLineColumn('Title', null, null, true)

		// Create test rows
		cy.get('[data-cy="createRowBtn"]').click()
		cy.get('[data-cy="createRowModal"] input').first().type('Apple')
		cy.get('[data-cy="createRowSaveButton"]').click()

		cy.get('[data-cy="createRowBtn"]').click()
		cy.get('[data-cy="createRowModal"] input').first().type('Banana')
		cy.get('[data-cy="createRowSaveButton"]').click()

		cy.get('[data-cy="createRowBtn"]').click()
		cy.get('[data-cy="createRowModal"] input').first().type('Apricot')
		cy.get('[data-cy="createRowSaveButton"]').click()

		// Apply search filter
		cy.get('.searchAndFilter input').type('Ap')

		// Intercept backend request that contains both filter and sort
		//@todo apply it after seach will be moved to the backend
		// cy.intercept('GET', '**/apps/tables/row/table/*', req => {
		// 	if (req.query?.sort && req.query?.customFilters) {
		// 		req.alias = 'filteredSortingRequest'
		// 	}
		// })

		// Verify sorting applies to filtered results
		cy.get('.custom-table table tbody tr').should('have.length.greaterThan', 0)

		// Apply sort on filtered results
		cy.sortTableColumn('Title', 'ASC')

		//check the order
		cy.get('.custom-table table tbody tr').first().should('contain', 'Apple')

		//chnage the ordering
		cy.sortTableColumn('Title', 'DESC')

		//check the order for second tr scond is not a function
		cy.get('.custom-table table tbody tr').first().should('contain', 'Apricot')

		//check if not contains the non filtered item
		cy.get('.custom-table table tbody tr').should('not.contain', 'Banana')
		//@todo apply it after seach will be moved to the backend
		// Wait for backend request to complete
		// cy.wait('@filteredSortingRequest').then(({ request }) => {
		// 	expect(request.query).to.have.property('sort')
		// 	expect(request.query).to.have.property('customFilters')
		// })
	})

	it('View navigation - sort settings not affected by table changes', () => {
		cy.createTable('Table 1 Sort')
		cy.createTextLineColumn('Col1', null, null, true)

		cy.createView('View A')

		// Add rows
		cy.get('[data-cy="createRowBtn"]').click()
		cy.get('[data-cy="createRowModal"] input').first().type('Z')
		cy.get('[data-cy="createRowSaveButton"]').click()

		cy.get('[data-cy="createRowBtn"]').click()
		cy.get('[data-cy="createRowModal"] input').first().type('A')
		cy.get('[data-cy="createRowSaveButton"]').click()
		// Intercept backend sorting request
		cy.intercept('GET', '**/apps/tables/row/view/*').as('sortingRequest')

		// Apply local sort on view
		cy.sortTableColumn('Col1', 'DESC')
		// Wait for backend request to complete
		cy.wait('@sortingRequest')

		cy.get('.info').contains('Reset local adjustments').should('be.visible')

		// Navigate to table
		cy.loadTable('Table 1 Sort')
		cy.get('.info').contains('Reset local adjustments').should('not.exist')
	})

})
