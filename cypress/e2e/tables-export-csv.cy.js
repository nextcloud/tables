let localUser

describe('Import csv', () => {

	before(function() {
		cy.createRandomUser().then(user => {
			localUser = user
		})
	})

	beforeEach(function() {
		cy.login(localUser)
		cy.visit('apps/tables')
	})

	it('Export csv', () => {
		cy.loadTable('Tutorial')
		cy.clickOnTableThreeDotMenu('Export as CSV')
		const time = new Date()
		const fileName = new Date().toISOString().slice(2, 10) + '_' + time.getHours() + '-' + time.getMinutes() + '_' + 'Tutorial.csv'
		cy.log('filename: ' + fileName)
		cy.readFile('cypress/downloads/' + fileName).should('contain', 'What,How to do,Ease of use,Done')
		cy.readFile('cypress/downloads/' + fileName).should('contain', 'Open the tables app,Click on tables icon in the menu bar.,5,true')
	})

})
