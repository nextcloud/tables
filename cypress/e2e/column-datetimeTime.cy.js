let localUser
const columnTitle = 'time'
const tableTitle = 'Test datetime time'

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
		cy.createDatetimeTimeColumn(columnTitle, null, true)

		// insert row with int value
		cy.get('button').contains('Create row').click()
		cy.get('.modal__content input').first().clear().type('05:15')
		cy.get('.modal-container .checkbox-radio-switch label').click().click()
		cy.get('button').contains('Save').click()
		cy.get('.custom-table table tr td div').contains('05:15').should('be.visible')

		// delete row
		cy.get('.NcTable tr td button').first().click()
		cy.get('button').contains('Delete').click()
		cy.get('button').contains('I really').click()

		cy.removeColumn(columnTitle)
	})

	it('Insert and test rows - default now', () => {
		cy.loadTable(tableTitle)
		cy.createDatetimeTimeColumn(columnTitle, true, true)

		// insert row with int value
		cy.get('button').contains('Create row').click()
		const hour = new Date().getHours().toString().length < 2 ? '0' + new Date().getHours() : new Date().getHours().toString()
		const minutes = new Date().getMinutes().toString().length < 2 ? '0' + new Date().getMinutes() : new Date().getMinutes().toString()
		const datetime = hour + ':' + minutes
		cy.get('.modal__content input').first().should('contain.value', datetime)
		cy.get('.modal-container .checkbox-radio-switch label').click().click()
		cy.get('button').contains('Save').click()
		cy.get('.custom-table table tr td div').contains(datetime).should('be.visible')
	})

})
