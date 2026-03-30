/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
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
		cy.loadTable('Welcome to Nextcloud Tables!')
		cy.clickOnTableThreeDotMenu('Export as CSV')
		const hour = new Date().getHours().toString().length < 2 ? '0' + new Date().getHours() : new Date().getHours().toString()
		const minutes = new Date().getMinutes().toString().length < 2 ? '0' + new Date().getMinutes() : new Date().getMinutes().toString()
		const date = new Date().toISOString().slice(2, 10)
		cy.getTutorialTableName().then(tutorialName => {
			const fileName = date + '_' + hour + '-' + minutes + '_' + tutorialName + '.csv'
			cy.log('filename: ' + fileName)
			cy.readFile('cypress/downloads/' + fileName).should('contain', 'What,How to do,Ease of use,Done')
			cy.readFile('cypress/downloads/' + fileName).should('contain', 'Open the tables app,Reachable via the Tables icon in the apps list.,5,true')
		})
	})

})
