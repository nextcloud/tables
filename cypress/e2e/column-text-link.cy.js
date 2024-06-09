let localUser

describe('Test column text-link', () => {

	before(function() {
		cy.createRandomUser().then(user => {
			localUser = user
			cy.login(localUser)
			cy.uploadFile('photo-test-1.jpeg', 'image/jpeg', '/photo-test-1.jpeg')
			cy.uploadFile('NC_server_test.pdf', 'application/pdf', '/NC_server_test.pdf')
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
		const now = new Date()
		cy.clock(now)

		cy.loadTable('Test text-link')
		cy.get('.NcTable').contains('Create row').click({ force: true })
		cy.get('.modal__content .slot input').first().type('https://nextcloud.com')

		cy.intercept({ method: 'GET', url: '**/search/providers/files/*' }).as('filesResults')
		cy.get('.modal__content .slot input').eq(1).type('pdf').tick(500)
		cy.wait('@filesResults')
		cy.get('[data-cy*="NC_server_test"]').click()

		cy.get('.modal-container button').contains('Save').click()

		cy.get('tr td a').contains('nextcloud').should('exist')
		cy.get('tr td a').contains('NC_server_test').should('exist')
	})

	it('Edit row', () => {
		const now = new Date()
		cy.clock(now)

		cy.loadTable('Test text-link')
		cy.get('.NcTable tr td button').click({ force: true })

		cy.get('.modal__content .slot input').first().clear().type('https://github.com')

		cy.get('.modal__content .slot input').eq(1).type('photo-test').tick(500)
		cy.get('[data-cy*="photo-test"]').first().click()

		cy.get('.modal-container button').contains('Save').click()

		cy.get('tr td a').contains('github').should('exist')
		cy.get('tr td a').contains('photo').should('exist')
	})

})
