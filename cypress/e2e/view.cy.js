let localUser

describe('Interact with views', () => {

	before(function() {
		cy.createRandomUser().then(user => {
			localUser = user
		})
	})

	beforeEach(function() {
		cy.login(localUser)
		cy.visit('apps/tables')
	})

	it('Create view and insert rows in the view', () => {
		const title = 'View for adding rows'
		cy.createTable('View filtering test table')
		cy.createTextLineColumn('title', null, null, true)
		cy.createSelectionColumn('selection', ['sel1', 'sel2', 'sel3', 'sel4'], null, false)

		// add row
		cy.get('button').contains('Create row').click()
		cy.fillInValueTextLine('title', 'first row')
		cy.fillInValueSelection('selection', 'sel1')
		cy.get('button').contains('Save').click()

		// add row
		cy.get('button').contains('Create row').click()
		cy.fillInValueTextLine('title', 'second row')
		cy.fillInValueSelection('selection', 'sel2')
		cy.get('button').contains('Save').click()

		// add row
		cy.get('button').contains('Create row').click()
		cy.fillInValueTextLine('title', 'sevenths row')
		cy.fillInValueSelection('selection', 'sel2')
		cy.get('button').contains('Save').click()

		cy.get('[data-cy="customTableAction"] button').click()
		cy.get('.v-popper__popper li button span').contains('Create view').click({ force: true })
		cy.get('.modal-container #settings-section_title input').type(title)

		// ## add filter
		cy.get('button').contains('Add new filter group').click()
		cy.get('.modal-container .filter-group .v-select.select').eq(0).click()
		cy.get('ul.vs__dropdown-menu li span[title="selection"]').click()
		cy.get('.modal-container .filter-group .v-select.select').eq(1).click()
		cy.get('ul.vs__dropdown-menu li span[title="Is equal"]').click()
		cy.get('.modal-container .filter-group .v-select.select').eq(2).click()
		cy.get('ul.vs__dropdown-menu li span[title="sel2"]').click()

		// ## save view
		cy.intercept({ method: 'POST', url: '**/apps/tables/view' }).as('createView')
		cy.intercept({ method: 'PUT', url: '**/apps/tables/view/*' }).as('updateView')
		cy.contains('button', 'Create View').click()
		cy.wait('@createView')
		cy.wait('@updateView')
		cy.contains('.app-navigation-entry-link span', title).should('exist')

		const expected = ['sevenths row', 'second row']
		expected.forEach(item => {
			cy.get('.custom-table table tr td div').contains(item).should('be.visible')
		})

		// Add new row in the views
		cy.get('button').contains('Create row').click()
		cy.fillInValueTextLine('title', 'new row')
		cy.fillInValueSelection('selection', 'sel2')
		cy.get('button').contains('Save').click()

		expected.push('new row')
		expected.forEach(item => {
			cy.get('.custom-table table tr td div').contains(item).should('be.visible')
		})
	})
})