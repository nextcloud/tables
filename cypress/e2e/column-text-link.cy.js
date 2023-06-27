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
		cy.contains('.app-menu-entry--label', 'Tables').click()
		cy.contains('button', 'Create new table').click()
		cy.get('.tile').contains('Custom table').click({ force: true })
		cy.get('.modal__content input[type="text"]').clear().type('Test text-link')
		cy.contains('button', 'Create table').click()

		cy.contains('h1', 'Test text-link').should('be.visible')
	})

	it('Create 3 text-link columns', () => {
		// first column
		cy.get('.app-navigation-entry-link').contains('Test text-link').click({ force: true })
		cy.get('.button-vue__text').contains('Create column').click({ force: true })
		cy.get('.modal-container').get('input[placeholder*="Enter a column title"]').clear().type('Test plain url')
		cy.get('.columnTypeSelection .vs__open-indicator').click({ force: true })
		cy.get('.multiSelectOptionLabel').contains('Link').click({ force: true })
		// deactivate unwanted provider
		cy.get('.typeSelection span label').contains('Files').click()
		cy.get('.typeSelection span label').contains('Contacts').click()
		cy.get('.modal-container button').contains('Save').click()

		cy.wait(10).get('.toastify.toast-success').should('be.visible')
		cy.get('.custom-table table tr th .cell').contains('Test plain url').should('exist')

		// second column
		cy.get('.customTableAction button').click()
		cy.get('.v-popper__popper li button span').contains('Create column').click({ force: true })
		cy.get('.modal-container').get('input[placeholder*="Enter a column title"]').clear().type('Test files')
		cy.get('.columnTypeSelection .vs__open-indicator').click({ force: true })
		cy.get('.multiSelectOptionLabel').contains('Link').click({ force: true })
		// deactivate unwanted provider
		cy.get('.typeSelection span label').contains('Url').click()
		cy.get('.typeSelection span label').contains('Contacts').click()
		cy.get('.modal-container button').contains('Save').click()

		cy.wait(10).get('.toastify.toast-success').should('be.visible')
		cy.get('.custom-table table tr th .cell').contains('Test files').should('exist')
	})

	it('Create row', () => {
		cy.get('.app-navigation-entry-link').contains('Test text-link').click({ force: true })
		cy.get('.NcTable').contains('Create row').click({ force: true })
		cy.get('.modal__content .slot input').first().type('https://nextcloud.com').wait(500).type('{downArrow}').wait(500).type('{enter}')
		cy.get('.modal__content .slot input').eq(1).type('pdf').wait(500).type('{downArrow}').wait(500).type('{enter}')
		cy.get('.modal-container button').contains('Save').click()

		cy.get('tr td a').contains('nextcloud').should('exist')
		cy.get('tr td a').contains('Nextcloud_Server').should('exist')
	})

	it('Edit row', () => {
		cy.get('.app-navigation-entry-link').contains('Test text-link').click({ force: true })
		cy.get('.NcTable tr td button').click({ force: true })

		cy.get('.modal__content .slot input').first().clear().type('https://github.com').wait(500).type('{downArrow}').wait(500).type('{enter}')
		cy.get('.modal__content .slot input').eq(1).type('photo').wait(1500).type('{downArrow}{downArrow}').wait(500).type('{enter}')
		cy.get('.modal-container button').contains('Save').click()

		cy.get('tr td a').contains('github').should('exist')
		cy.get('tr td a').contains('photo').should('exist')
	})

})
