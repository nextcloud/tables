/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
let localUser
let nonLocalUser
let tableTitlePrefix = 'test table'
let tableTitle = tableTitlePrefix
let viewTitle = 'test view'
let contextTitlePrefix = 'test application'
let contextTitle = contextTitlePrefix
let testNumber = 0

describe('Manage a context', () => {
    before(function () {
        cy.createRandomUser().then(user => {
            localUser = user
        })
        cy.createRandomUser().then(user => {
            nonLocalUser = user
        })
    })

    beforeEach(function () {
        testNumber += 1
        contextTitle = contextTitlePrefix + ' ' + testNumber
        tableTitle = tableTitlePrefix + ' ' + testNumber
        cy.login(localUser)
        cy.visit('apps/tables')
        cy.createContext(contextTitle)
        cy.loadContext(contextTitle)
    })

    it('Update and add resources', () => {
        cy.createTable(tableTitle)
        cy.loadTable(tableTitle)
        cy.createTextLineColumn('title', null, null, true)
        cy.get('button').contains('Create row').click()
        cy.fillInValueTextLine('title', 'first row')
        cy.get('[data-cy="createRowSaveButton"]').click()
        cy.createView(viewTitle)

        cy.openContextEditModal(contextTitle)
        cy.get('[data-cy="editContextTitle"]').clear().type(`updated ${contextTitle}`)
        cy.get('[data-cy="contextResourceForm"] input').clear().type(tableTitle)
        cy.get('ul.vs__dropdown-menu li div').contains(tableTitle).click()
        cy.get('[data-cy="contextResourceList"]').should('contain.text', tableTitle)
        cy.get('[data-cy="contextResourcePerms"]').should('contain.text', tableTitle)
        cy.get('[data-cy="contextResourceForm"] input').clear().type(viewTitle)
        cy.get('ul.vs__dropdown-menu li div').contains(viewTitle).click()
        cy.get('[data-cy="contextResourceList"]').should('contain.text', viewTitle)
        cy.get('[data-cy="contextResourcePerms"]').should('contain.text', viewTitle)
        cy.get('[data-cy="editContextSubmitBtn"]').click()
        cy.get('[data-cy="navigationContextItem"]').contains(`updated ${contextTitle}`).should('exist')
        cy.get('[data-cy="navigationContextItem"]').contains(`updated ${contextTitle}`).click({ force: true })
        cy.contains('h1', `updated ${contextTitle}`).should('exist')
        cy.contains('h1', tableTitle).should('exist')
        cy.contains('h1', viewTitle).should('exist')

    })

    it('Share context with resources', () => {
        cy.createTable(tableTitle)
        cy.openContextEditModal(contextTitle)
        cy.get('[data-cy="contextResourceForm"] input').clear().type(tableTitle)
        cy.get('ul.vs__dropdown-menu li div').contains(tableTitle).click()
        cy.get('[data-cy="contextResourceShare"] input').clear().type(nonLocalUser.userId)
        cy.get(`.vs__dropdown-menu [id="${nonLocalUser.userId}"]`).click()
        cy.get('[data-cy="contextResourceShare"] span').contains(nonLocalUser.userId).should('exist')
        cy.get('[data-cy="editContextSubmitBtn"]').click()
        cy.loadContext(contextTitle)
        cy.contains('h1', contextTitle).should('exist')
        cy.contains('h1', tableTitle).should('exist')

        // verify context was shared properly
        cy.login(nonLocalUser)
        cy.visit('apps/tables')
        cy.loadContext(contextTitle)
        cy.contains('h1', contextTitle).should('exist')
        cy.contains('h1', tableTitle).should('exist')

        cy.login(localUser)
        cy.visit('apps/tables')
        cy.loadContext(contextTitle)
        cy.contains('h1', contextTitle).should('exist')
    })

    it('Transfer context', () => {
        cy.createTable(tableTitle)
        cy.openContextEditModal(contextTitle)
        cy.get('[data-cy="transferContextSubmitBtn"]').click()
        cy.get('[data-cy="transferContextModal"]').should('be.visible')
        cy.get('[data-cy="transferContextModal"] input').clear().type(nonLocalUser.userId)
        cy.get(`.vs__dropdown-menu [id="${nonLocalUser.userId}"]`).click()
        cy.get('[data-cy="transferContextButton"]').click()

        // verify that context was properly transferred
        cy.login(nonLocalUser)
        cy.visit('apps/tables')
        cy.loadContext(contextTitle)
        cy.contains('h1', contextTitle).should('exist')
    })

    it('Delete context with shares', () => {
        cy.loadContext(contextTitle)
        cy.openContextEditModal(contextTitle)
        cy.get('[data-cy="contextResourceShare"] input').clear().type(nonLocalUser.userId)
        cy.get(`.vs__dropdown-menu [id="${nonLocalUser.userId}"]`).click()
        cy.get('[data-cy="contextResourceShare"] span').contains(nonLocalUser.userId).should('exist')
        cy.get('[data-cy="editContextSubmitBtn"]').click()  
        cy.wait(1000)
        
        // verify that context was deleted from current user
        cy.get(`[data-cy="navigationContextItem"]:contains("${contextTitle}")`).find('button').click({ force: true })
        cy.wait(1000)
        cy.get('[data-cy="navigationContextDeleteBtn"]').contains('Delete application').click({ force: true })
        cy.get('[data-cy="deleteContextModal"]').should('be.visible')
        cy.get('[data-cy="deleteContextModal"] button').contains('Delete').click()
        cy.get('li').contains(contextTitle).should('not.exist')
        cy.contains('h1', contextTitle).should('not.exist')

        // verify that context was deleted from shared user
        cy.login(nonLocalUser)
        cy.visit('apps/tables')
        cy.get('li').contains(contextTitle).should('not.exist')
    })

    it('Remove context resource', () => {
        cy.createTable(tableTitle)
        cy.loadContext(contextTitle)
        cy.openContextEditModal(contextTitle)
        cy.get('[data-cy="contextResourceForm"] input').clear().type(tableTitle)
        cy.get('ul.vs__dropdown-menu li div').contains(tableTitle).click()
        cy.get('[data-cy="contextResourceList"]').should('contain.text', tableTitle)
        cy.get('[data-cy="contextResourcePerms"]').should('contain.text', tableTitle)
        cy.get('[data-cy="editContextSubmitBtn"]').click()

        cy.contains('h1', contextTitle).should('exist')
        cy.contains('h1', tableTitle).should('exist') 
        cy.openContextEditModal(contextTitle)
        cy.get('[data-cy="contextResourceList"] button').contains('Delete').click({ force: true })
        cy.get('[data-cy="contextResourceList"]').contains(tableTitle).should('not.exist')
        cy.get('[data-cy="editContextSubmitBtn"]').click()
        cy.contains('h1', contextTitle).should('exist')
        cy.contains('h1', tableTitle).should('not.exist')
    })

    it('Modify resource rows and columns from context', () => {
        cy.createTable(tableTitle)
        cy.loadContext(contextTitle)
        cy.contains('h1', contextTitle).should('exist')
        cy.openContextEditModal(contextTitle)
        cy.get('[data-cy="contextResourceForm"] input').clear().type(tableTitle)
        cy.get('ul.vs__dropdown-menu li div').contains(tableTitle).click()
        cy.get('[data-cy="contextResourceList"]').should('contain.text', tableTitle)
        cy.get('[data-cy="contextResourcePerms"]').should('contain.text', tableTitle)
        cy.get('[data-cy="editContextSubmitBtn"]').click()
        cy.createTextLineColumn('title', null, null, true)
        cy.get('button').contains('Create row').click()
        cy.fillInValueTextLine('title', 'first row')
        cy.get('[data-cy="createRowSaveButton"]').click()
		cy.get('[data-cy="ncTable"] table').contains('first row').should('exist')
    })

    it('Modify context resources and their permissions', () => {
        cy.createTable(tableTitle)
        cy.loadTable(tableTitle)
        cy.createTextLineColumn('title', null, null, true)
        cy.loadContext(contextTitle)
        cy.contains('h1', contextTitle).should('exist')
        cy.openContextEditModal(contextTitle)
        cy.get('[data-cy="contextResourceForm"] input').clear().type(tableTitle)
        cy.get('ul.vs__dropdown-menu li div').contains(tableTitle).click()
        cy.get('[data-cy="contextResourceList"]').should('contain.text', tableTitle)
        cy.get('[data-cy="contextResourcePerms"]').should('contain.text', tableTitle)
        // give delete permission for resource
        cy.get('[data-cy="resourceSharePermsActions"] button').click()
        cy.get('li .action-checkbox').contains('Delete resource').click()
        cy.get('li [aria-checked="true"]').contains('Delete resource').should('exist')
        
        cy.get('[data-cy="contextResourceShare"] input').clear().type(nonLocalUser.userId)
        cy.get(`.vs__dropdown-menu [id="${nonLocalUser.userId}"]`).click()
        cy.get('[data-cy="contextResourceShare"] span').contains(nonLocalUser.userId).should('exist')
        cy.get('[data-cy="editContextSubmitBtn"]').click()
        cy.contains('h1', contextTitle).should('exist')
        cy.contains('h1', tableTitle).should('exist')

        // verify that shared user can modify and delete data in the resource
        cy.login(nonLocalUser)
        cy.visit('apps/tables')
        cy.loadContext(contextTitle)
        cy.contains('h1', contextTitle).should('exist')
        cy.contains('h1', tableTitle).should('exist')
        cy.get('button').contains('Create row').click()
        cy.fillInValueTextLine('title', 'first row')
        cy.get('[data-cy="createRowSaveButton"]').click()
        cy.get('[data-cy="ncTable"] table').contains('first row').should('exist')
        cy.get('[data-cy="ncTable"] [data-cy="customTableRow"]').contains('first row').closest('[data-cy="customTableRow"]').find('[data-cy="editRowBtn"]').click()
		cy.get('[data-cy="editRowDeleteButton"]').click()
		cy.get('[data-cy="editRowDeleteConfirmButton"]').click()
        cy.get('[data-cy="ncTable"] table').contains('first row').should('not.exist')
    })
})