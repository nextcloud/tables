let localUser

describe('Test column selection multi', () => {

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
		cy.createTable('Test selection multi columns')
		cy.loadTable('Test selection multi columns')

		cy.createSelectionMultiColumn('multiple selection 1', ['first option', 'second option', '👋 third option', '🤷🏻 fifths'], ['second option', 'first option'], true)
	})

	it('Insert and test rows', () => {
		cy.loadTable('Test selection multi columns')

		// check if default value is set on row creation
		cy.get('button').contains('Create row').click()
		cy.get('.modal__content h2').contains('Create row').should('be.visible')
		cy.get('.modal__content .title').contains('multiple selection 1').should('be.visible')
		cy.get('.modal__content span[title="first option"]').should('be.visible')
		cy.get('.modal__content span[title="second option"]').should('be.visible')
		cy.get('button').contains('Save').click()
		cy.get('.custom-table table tr td div').contains('first option').should('be.visible')
		cy.get('.custom-table table tr td div').contains('second option').should('be.visible')

		// create a row and select non default value
		cy.get('button').contains('Create row').click()
		cy.get('.vs--multiple .vs__selected button').first().click()
		cy.get('.vs--multiple .vs__selected button').first().click()
		cy.get('.modal__content .slot input').first().click()
		cy.get('ul.vs__dropdown-menu li span[title="👋 third option"]').click()
		cy.get('button').contains('Save').click()
		cy.get('.custom-table table tr td div').contains('third option').should('be.visible')

		// delete first row
		cy.get('.NcTable tr td button').first().click()
		cy.get('button').contains('Delete').click()
		cy.get('button').contains('I really').click()
		cy.get('.custom-table table tr td div').contains('first option').should('not.exist')
		cy.get('.custom-table table tr td div').contains('second option').should('not.exist')

		// edit second row
		cy.get('.NcTable tr td button').first().click()
		cy.get('.modal__content .slot input').first().click()
		cy.get('ul.vs__dropdown-menu li span[title="first option"]').click()
		cy.get('button').contains('Save').click()
		cy.get('.custom-table table tr td div').contains('first option').should('be.visible')
		cy.get('.custom-table table tr td div').contains('third option').should('be.visible')
	})

})
