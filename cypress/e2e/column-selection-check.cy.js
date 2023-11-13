let localUser
const columnTitle = 'check'
const tableTitle = 'Test selection check'

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

	it('Insert and test rows - default value unchecked', () => {
		cy.loadTable(tableTitle)
		cy.createSelectionCheckColumn(columnTitle, null, true)

		// insert row
		cy.get('button').contains('Create row').click()
		cy.get('button').contains('Save').click()
		cy.get('.custom-table table tr td div .material-design-icon.radiobox-blank-icon').should('be.visible')

		// insert row
		cy.get('button').contains('Create row').click()
		cy.get('.modal__content .checkbox-radio-switch__label').first().click()
		cy.get('button').contains('Save').click()
		cy.get('.custom-table table tr td div .material-design-icon.check-circle-outline-icon').should('be.visible')

		cy.removeColumn(columnTitle)
	})

	it('Insert and test rows - default value checked', () => {
		cy.loadTable(tableTitle)
		cy.createSelectionCheckColumn(columnTitle, true, true)

		// insert row
		cy.get('button').contains('Create row').click()
		cy.get('button').contains('Save').click()
		cy.get('.custom-table table tr td div .material-design-icon.check-circle-outline-icon').should('be.visible')

		// insert row
		cy.get('button').contains('Create row').click()
		cy.get('.modal__content .checkbox-radio-switch__label').first().click()
		cy.get('button').contains('Save').click()
		cy.get('.custom-table table tr td div .material-design-icon.radiobox-blank-icon').should('be.visible')

		cy.removeColumn(columnTitle)
	})

})
