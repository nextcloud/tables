let localUser
let nonLocalUser
let tableTitle = 'test table'
let viewTitle = 'test view'
let contextTitle = 'test application'

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
        cy.login(localUser)
        cy.visit('apps/tables')
        cy.createContext(contextTitle)
        cy.loadContext(contextTitle)
    })

    afterEach(function () {
        // Delete context
        cy.get('[data-cy="navigationContextItem"] button').click({ force: true })
        cy.get('[data-cy="navigationContextDeleteBtn"]').contains('Delete application').click({ force: true })
        cy.wait(1000)
        cy.get('[data-cy="deleteContextModal"]').should('be.visible')
        cy.get('[data-cy="deleteContextModal"] button').contains('Delete').click()
        cy.get('[data-cy="navigationContextItem"]').should('not.exist')
    })

    it('Update and add resources', () => {
        cy.createTable(tableTitle)
        cy.loadTable(tableTitle)
        cy.createTextLineColumn('title', null, null, true)
        cy.get('button').contains('Create row').click()
        cy.fillInValueTextLine('title', 'first row')
        cy.get('[data-cy="createRowSaveButton"]').click()
        cy.createView(viewTitle)


        cy.get('[data-cy="navigationContextItem"] button').last().click({ force: true })
        cy.get('[data-cy="navigationContextEditBtn"]').contains('Edit application').click({ force: true })
        cy.get('[data-cy="editContextModal"]').should('be.visible')
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
        cy.get('[data-cy="navigationContextItem"] button').last().click({ force: true })
        cy.get('[data-cy="navigationContextEditBtn"]').contains('Edit application').click({ force: true })
        cy.get('[data-cy="editContextModal"]').should('be.visible')

        cy.get('[data-cy="contextResourceForm"] input').clear().type(tableTitle)
        cy.get('ul.vs__dropdown-menu li div').contains(tableTitle).click()
        cy.get('[data-cy="contextResourceShare"] input').clear().type(nonLocalUser.userId)
        cy.get(`.vs__dropdown-menu [user="${nonLocalUser.userId}"]`).click()
        cy.get('[data-cy="contextResourceShare"] span').contains(nonLocalUser.userId).should('exist')
        cy.get('[data-cy="editContextSubmitBtn"]').click()
        cy.loadContext(contextTitle)

        // verify context was shared properly
        cy.login(nonLocalUser)
        cy.visit('apps/tables')
        cy.loadContext(contextTitle)
        cy.contains('header .header-left .app-menu li.app-menu-entry__active', contextTitle).should('exist')
        cy.contains('h1', contextTitle).should('exist')
        cy.contains('h1', tableTitle).should('exist')

        cy.login(localUser)
        cy.visit('apps/tables')
        cy.loadContext(contextTitle)
        cy.contains('h1', contextTitle).should('exist')
    })

    it('Transfer context', () => {
        cy.createTable(tableTitle)
        cy.get('[data-cy="navigationContextItem"] button').last().click({ force: true })
        cy.get('[data-cy="navigationContextEditBtn"]').contains('Edit application').click({ force: true })
        cy.get('[data-cy="editContextModal"]').should('be.visible')
        cy.get('[data-cy="transferContextSubmitBtn"]').click()
        cy.get('[data-cy="transferContextModal"]').should('be.visible')
        cy.get('[data-cy="transferContextModal"] input').clear().type(nonLocalUser.userId)
        cy.get(`.vs__dropdown-menu [user="${nonLocalUser.userId}"]`).click()
        cy.get('[data-cy="transferContextButton"]').click()

        cy.login(nonLocalUser)
        cy.visit('apps/tables')
        cy.loadContext(contextTitle)
        cy.contains('header .header-left .app-menu li.app-menu-entry__active', contextTitle).should('exist')
        cy.contains('h1', contextTitle).should('exist')
    })

    it('Delete context with shares', () => {
        cy.loadContext(contextTitle)
        cy.get('[data-cy="navigationContextItem"] button').last().click({ force: true })
        cy.get('[data-cy="navigationContextEditBtn"]').contains('Edit application').click({ force: true })
        cy.get('[data-cy="editContextModal"]').should('be.visible')
        cy.get('[data-cy="editContextTitle"]').clear().type(`to delete ${contextTitle}`)
        cy.get('[data-cy="contextResourceShare"] input').clear().type(nonLocalUser.userId)
        cy.get(`.vs__dropdown-menu [user="${nonLocalUser.userId}"]`).click()
        cy.get('[data-cy="contextResourceShare"] span').contains(nonLocalUser.userId).should('exist')
        cy.get('[data-cy="editContextSubmitBtn"]').click()
        
        cy.get('[data-cy="navigationContextItem"] button').last().click({ force: true })
        cy.get('[data-cy="navigationContextDeleteBtn"]').contains('Delete application').click({ force: true })
        cy.get('[data-cy="deleteContextModal"]').should('be.visible')
        cy.get('[data-cy="deleteContextModal"] button').contains('Delete').click()
        cy.get('[data-cy="navigationContextItem"]').contains(`to delete ${contextTitle}`).should('not.exist')

        cy.login(nonLocalUser)
        cy.visit('apps/tables')
        cy.get('[data-cy="navigationContextItem"]').contains(`to delete ${contextTitle}`).should('not.exist')
    })

    it('Remove context resources', () => {
        cy.createTable(tableTitle)
        cy.loadContext(contextTitle)
        cy.get('[data-cy="navigationContextItem"] button').last().click({ force: true })
        cy.get('[data-cy="navigationContextEditBtn"]').contains('Edit application').click({ force: true })
        cy.get('[data-cy="editContextModal"]').should('be.visible')
        cy.get('[data-cy="contextResourceForm"] input').clear().type(tableTitle)
        cy.get('ul.vs__dropdown-menu li div').contains(tableTitle).click()
        cy.get('[data-cy="contextResourceList"]').should('contain.text', tableTitle)
        cy.get('[data-cy="contextResourcePerms"]').should('contain.text', tableTitle)
        cy.get('[data-cy="editContextSubmitBtn"]').click()

        cy.get('[data-cy="navigationContextItem"] button').last().click({ force: true })
        cy.get('[data-cy="navigationContextEditBtn"]').contains('Edit application').click({ force: true })
        cy.get('[data-cy="editContextModal"]').should('be.visible')
        cy.get('[data-cy="contextResourceList"] button').contains('Delete').click({ force: true })
        cy.get('[data-cy="contextResourceList"]').contains(tableTitle).should('not.exist')
        cy.get('[data-cy="editContextSubmitBtn"]').click()

        cy.loadContext(contextTitle)
        cy.contains('h1', contextTitle).should('exist')
        cy.contains('h1', tableTitle).should('not.exist')

        cy.login(nonLocalUser)
        cy.visit('apps/tables')
        cy.get('[data-cy="navigationContextItem"]').contains(`to delete ${contextTitle}`).should('not.exist')
    })

    it('Modify context resources and their permissions', () => {
        cy.createTable(tableTitle)
        cy.loadContext(contextTitle)
        cy.contains('h1', contextTitle).should('exist')
        cy.get('[data-cy="navigationContextItem"] button').last().click({ force: true })
        cy.get('[data-cy="navigationContextEditBtn"]').contains('Edit application').click({ force: true })
        cy.get('[data-cy="editContextModal"]').should('be.visible')
        cy.get('[data-cy="contextResourceForm"] input').clear().type(tableTitle)
        cy.get('ul.vs__dropdown-menu li div').contains(tableTitle).click()
        cy.get('[data-cy="contextResourceList"]').should('contain.text', tableTitle)
        cy.get('[data-cy="contextResourcePerms"]').should('contain.text', tableTitle)
        cy.get('[data-cy="resourceSharePermsActions"] button').click()
        cy.get('li .action-checkbox').contains('Delete resource').click()
        cy.get('li [aria-checked="true"]').contains('Delete resource').should('exist')
        cy.get('[data-cy="contextResourceShare"] input').clear().type(nonLocalUser.userId)
        cy.get(`.vs__dropdown-menu [user="${nonLocalUser.userId}"]`).click()
        cy.get('[data-cy="contextResourceShare"] span').contains(nonLocalUser.userId).should('exist')
        cy.get('[data-cy="editContextSubmitBtn"]').click()
        cy.contains('h1', contextTitle).should('exist')
        cy.contains('h1', tableTitle).should('exist') // doing this to give Cypress time to load

        cy.login(nonLocalUser)
        cy.visit('apps/tables')
        cy.contains('header .header-left .app-menu li.app-menu-entry', contextTitle).should('exist')
        cy.loadContext(contextTitle)
        cy.contains('h1', contextTitle).should('exist')
        cy.contains('h1', tableTitle).should('exist')
        cy.createTextLineColumn('text line', 'test', '12', true)
        cy.get('button').contains('Create row').click()
        cy.fillInValueTextLine('title', 'first row')
        cy.get('[data-cy="createRowSaveButton]').click()

    })
})