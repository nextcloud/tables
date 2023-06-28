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
		const now = new Date()
		cy.clock(now)

		cy.loadTable('Test text-link')
		cy.get('.NcTable').contains('Create row').click({ force: true })
		cy.get('.modal__content .slot input').first().type('https://nextcloud.com').tick(500)
		cy.get('.icon-label-container .labels a').contains('https://nextcloud.com').click()

		cy.intercept({ method: 'GET', url: '**/search/providers/files/*' }).as('filesResults')
		cy.get('.modal__content .slot input').eq(1).type('pdf').tick(500)
		cy.wait('@filesResults')
		cy.get('.icon-label-container .labels a').contains('Nextcloud_Server').click()

		cy.get('.modal-container button').contains('Save').click()

		cy.get('tr td a').contains('nextcloud').should('exist')
		cy.get('tr td a').contains('Nextcloud_Server').should('exist')
	})

	it('Edit row', () => {
		const now = new Date()
		cy.clock(now)

		cy.loadTable('Test text-link')
		cy.get('.NcTable tr td button').click({ force: true })

		cy.get('.modal__content .slot input').first().clear().type('https://github.com').tick(500)
		cy.get('.icon-label-container .labels a').contains('github').click()

		cy.get('.modal__content .slot input').eq(1).type('photo').tick(500)
		cy.get('.icon-label-container .labels a').contains('photo').click()

		cy.get('.modal-container button').contains('Save').click()

		cy.get('tr td a').contains('github').should('exist')
		cy.get('tr td a').contains('photo').should('exist')
	})

})
