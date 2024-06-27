let localUser
const columnTitle = 'single selection'
const tableTitle = 'Test number column'

describe('Test column ' + columnTitle, () => {

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

	it('Table and column setup', () => {
		cy.createTable(tableTitle)
	})

	it('Insert and test rows', () => {
		cy.loadTable(tableTitle)
		cy.createSelectionColumn(columnTitle, ['first option', 'second option', '👋 third option', '🤷🏻 fifths'], 'second option', true)

		// check if default value is set on row creation
		cy.get('button').contains('Create row').click()
		cy.get('.modal__content h2').contains('Create row').should('be.visible')
		cy.get('.modal__content .title').contains(columnTitle).should('be.visible')
		cy.get('.modal__content .title').click()
		cy.get('.vs__dropdown-toggle .vs__selected span[title="second option"]').should('exist')
		cy.get('button').contains('Save').click()
		cy.get('.custom-table table tr td div').contains('second option').should('be.visible')

		// create a row and select non default value
		cy.get('button').contains('Create row').click()
		cy.get('.modal__content .slot input').first().click()
		cy.get('ul.vs__dropdown-menu li span[title="👋 third option"]').click()
		cy.get('button').contains('Save').click()
		cy.get('.custom-table table tr td div').contains('third option').should('be.visible')

		// delete first row
		cy.get('.NcTable tr td button').first().click()
		cy.get('button').contains('Delete').click()
		cy.get('button').contains('I really').click()
		cy.get('.custom-table table tr td div').contains('second').should('not.exist')

		// edit second row
		cy.get('.NcTable tr td button').first().click()
		cy.get('.modal__content .slot input').first().click()
		cy.get('ul.vs__dropdown-menu li span[title="first option"]').click()
		cy.get('button').contains('Save').click()
		cy.get('.custom-table table tr td div').contains('first option').should('be.visible')

		// delete first row
		cy.get('.NcTable tr td button').first().click()
		cy.get('button').contains('Delete').click()
		cy.get('button').contains('I really').click()

		cy.removeColumn(columnTitle)
	})

	it('Test empty selection', () => {
		cy.loadTable(tableTitle)
		cy.createSelectionColumn(columnTitle, ['first option', 'second option', '👋 third option', '🤷🏻 fifths'], null, true)

		// check if default value is set on row creation
		cy.get('button').contains('Create row').click()
		cy.get('.modal__content h2').contains('Create row').should('be.visible')
		cy.get('button').contains('Save').click()
		cy.get('.custom-table table tr td div').should('exist')
		cy.get('.NcTable tr td button').should('exist')
	})

})
