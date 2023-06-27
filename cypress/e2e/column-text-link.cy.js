let localUser

describe('Test column text-link', () => {

	before(function() {
		cy.createRandomUser().then(user => {
			localUser = user
		})
	})

	beforeEach(function() {
		cy.login(localUser)
		cy.visit('apps/tables')
	})


	it('Create', () => {
		cy.createTable('Test text-link')
	})

	it('Create 2 text-link columns', () => {
		cy.loadTable('Test text-link')

		cy.createTextLinkColumn('Test plain url', ['Url'], true)
		cy.createTextLinkColumn('Test files', ['Files'])
	})

	it('Create row', () => {
		cy.loadTable('Test text-link')
		cy.get('.NcTable').contains('Create row').click({ force: true })
		cy.get('.modal__content .slot input').first().type('https://nextcloud.com').wait(500).type('{downArrow}{enter}')


		cy.intercept({ method: 'GET', url: '**/search/providers/files/*' }).as('filesResults')
		cy.get('.modal__content .slot input').eq(1).type('pdf')
		cy.wait('@filesResults')
		cy.get('.modal__content .slot input').eq(1).type('{downArrow}').wait(20).type('{enter}')

		cy.get('.modal-container button').contains('Save').click()

		cy.get('tr td a').contains('nextcloud').should('exist')
		cy.get('tr td a').contains('Nextcloud_Server').should('exist')
	})

	it('Edit row', () => {
		cy.loadTable('Test text-link')
		cy.get('.NcTable tr td button').click({ force: true })

		cy.get('.modal__content .slot input').first().clear().type('https://github.com').wait(500).type('{downArrow}{enter}')
		cy.get('.modal__content .slot input').eq(1).type('photo').wait(1500).type('{downArrow}{downArrow}').wait(500).type('{enter}')
		cy.get('.modal-container button').contains('Save').click()

		cy.get('tr td a').contains('github').should('exist')
		cy.get('tr td a').contains('photo').should('exist')
	})

})
