let localUser
const columnTitle = 'date and time'
const tableTitle = 'Test datetime'

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
		cy.createDatetimeColumn(columnTitle, null, true)

		// insert row with int value
		cy.get('button').contains('Create row').click()
		cy.get('.modal__content input').first().clear().type('2023-12-24 05:15')
		cy.get('.modal-container .checkbox-radio-switch label').click().click()
		cy.get('button').contains('Save').click()
		cy.get('.custom-table table tr td div').contains('24 Dec 2023 05:15').should('be.visible')

		// delete row
		cy.get('.NcTable tr td button').first().click()
		cy.get('button').contains('Delete').click()
		cy.get('button').contains('I really').click()

		cy.removeColumn(columnTitle)
	})

	it('Insert and test rows - default now', () => {
		cy.loadTable(tableTitle)
		cy.createDatetimeColumn(columnTitle, true, true)

		// insert row with int value
		cy.get('button').contains('Create row').click()
		const hour = new Date().getHours().toString().length < 2 ? '0' + new Date().getHours() : new Date().getHours().toString()
		const minutes = new Date().getMinutes().toString().length < 2 ? '0' + new Date().getMinutes() : new Date().getMinutes().toString()
		const date = new Date().toISOString().slice(2, 10)
		const datetime = date + ' ' + hour + ':' + minutes
		cy.get('.modal__content input').first().should('contain.value', datetime)
		cy.get('.modal-container .checkbox-radio-switch label').click().click()
		cy.get('button').contains('Save').click()
		const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'June',
			'July', 'Aug', 'Sept', 'Oct', 'Nov', 'Dec']
		const datetime2 = new Date().getDate() + ' ' + monthNames[new Date().getMonth()] + ' ' + new Date().getFullYear() + ' ' + hour + ':' + minutes
		cy.log(datetime2)
		cy.get('.custom-table table tr td div').contains(datetime2).should('be.visible')
	})

})
