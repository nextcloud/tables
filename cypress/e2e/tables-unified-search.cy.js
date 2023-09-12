let localUser
let localUser2

describe('The Home Page', () => {

	before(function() {
		cy.createRandomUser().then(user => {
			localUser = user
		})
		cy.createRandomUser().then(user => {
			localUser2 = user
		})
	})

	beforeEach(function() {
		cy.login(localUser)
		cy.visit('apps/tables')
	})

	it('Create a table and view and search via unified search for it', () => {
		cy.loadTable('Tutorial')
		cy.createView('asdfghjkl')
		cy.unifiedSearch('HJK')
		cy.loadTable('Tutorial')
		cy.unifiedSearch('asd')
		cy.loadTable('Tutorial')
		cy.unifiedSearch('Tutorial')
	})

	it('Search for shared table via user share', () => {
		cy.login(localUser)
		cy.visit('apps/tables')

		// create table to share
		cy.contains('.app-menu-entry--label', 'Tables').click()
		cy.createTable('Share for user')
		cy.createTextLineColumn('any', true)

		cy.clickOnTableThreeDotMenu('Share')

		cy.intercept({ method: 'GET', url: '**/ocs/v2.php/apps/files_sharing/api/v1/sharees*' }).as('searchShareUsers')
		cy.get('.sharing input').type(localUser2.userId)
		cy.wait('@searchShareUsers')
		cy.get('.sharing input').type('{enter}')

		cy.get('h3').contains('Shares').parent().find('ul').contains(localUser2.userId).should('exist')

		cy.login(localUser2)
		cy.visit('apps/tables')
		cy.unifiedSearch('Share for user')
	})

	it('Search for shared view via user share', () => {
		cy.login(localUser)
		cy.visit('apps/tables')

		// create table to share
		cy.contains('.app-menu-entry--label', 'Tables').click()
		cy.createTable('Share for user')
		cy.createTextLineColumn('any', true)
		cy.createView('ShareView1')

		cy.clickOnTableThreeDotMenu('Share')

		cy.intercept({ method: 'GET', url: '**/ocs/v2.php/apps/files_sharing/api/v1/sharees*' }).as('searchShareUsers')
		cy.get('.sharing input').type(localUser2.userId)
		cy.wait('@searchShareUsers')
		cy.get('.sharing input').type('{enter}')

		cy.get('h3').contains('Shares').parent().find('ul').contains(localUser2.userId).should('exist')

		cy.login(localUser2)
		cy.visit('apps/tables')
		cy.unifiedSearch('ShareView1')
	})

	it('Search for shared table via group share', () => {
		cy.login({ userId: 'user1', password: '1234561' })
		cy.visit('apps/tables')

		// create table to share
		cy.contains('.app-menu-entry--label', 'Tables').click()
		cy.createTable('Share for group')
		cy.createTextLineColumn('any', true)

		cy.clickOnTableThreeDotMenu('Share')

		cy.intercept({ method: 'GET', url: '**/ocs/v2.php/apps/files_sharing/api/v1/sharees*' }).as('searchShareUsers')
		cy.get('.sharing input').type('shareTestGroup')
		cy.wait('@searchShareUsers')
		cy.get('.sharing input').type('{enter}')

		cy.get('h3').contains('Shares').parent().find('ul').contains('shareTestGroup').should('exist')

		cy.login({ userId: 'user2', password: '1234561' })
		cy.visit('apps/tables')
		cy.unifiedSearch('Share for group')
	})

	it('Search for shared view via group share', () => {
		cy.login({ userId: 'user1', password: '1234561' })
		cy.visit('apps/tables')

		// create table to share
		cy.contains('.app-menu-entry--label', 'Tables').click()
		cy.createTable('Share for group')
		cy.createTextLineColumn('any', true)
		cy.createView('ShareView2')

		cy.clickOnTableThreeDotMenu('Share')

		cy.intercept({ method: 'GET', url: '**/ocs/v2.php/apps/files_sharing/api/v1/sharees*' }).as('searchShareUsers')
		cy.get('.sharing input').type('shareTestGroup')
		cy.wait('@searchShareUsers')
		cy.get('.sharing input').type('{enter}')

		cy.get('h3').contains('Shares').parent().find('ul').contains('shareTestGroup').should('exist')

		cy.login({ userId: 'user2', password: '1234561' })
		cy.visit('apps/tables')
		cy.unifiedSearch('ShareView2')
	})

})
