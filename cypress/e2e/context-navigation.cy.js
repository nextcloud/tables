/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

let localUser
let nonLocalUser
let contextTitlePrefix = 'test application'
let contextTitle = contextTitlePrefix
let testNumber = 0

describe('Test context navigation', () => {
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
        cy.login(localUser)
        cy.visit('apps/tables')
        cy.get('[aria-label="Create new table"]').should('be.visible')
    })

    it('Create context that is hidden in nav by default', () => {
        cy.createContext(contextTitle, false)
        cy.visit('apps/tables')
        cy.get(`#header .app-menu-entry [title="${contextTitle}"]`).should('not.exist')
        
        cy.get(`[data-cy="navigationContextItem"]:contains("${contextTitle}")`).find('button').click({ force: true })
        cy.get('[data-cy="navigationContextShowInNavSwitch"] input').should('not.be.checked')
        cy.get('[data-cy="navigationContextShowInNavSwitch"] input').click({ force: true })
        cy.get('[data-cy="navigationContextShowInNavSwitch"] input').should('be.checked')
        cy.get(`#header .app-menu-entry [title="${contextTitle}"]`).should('exist')
    })

    it('Create context that shows in nav by default', () => {
        cy.createContext(contextTitle, true)
        cy.visit('apps/tables')

        // Confirming that the context is shown in the navigation for the owner
        cy.get(`#header .app-menu-entry [title="${contextTitle}"]`).should('exist')

        cy.loadContext(contextTitle)
        cy.get('[data-cy="context-title"]').should('be.visible')
        cy.openContextEditModal(contextTitle)
        cy.get('[data-cy="contextResourceShare"] input').clear().type(nonLocalUser.userId)
        cy.get(`.vs__dropdown-menu [id="${nonLocalUser.userId}"]`).click()
        cy.get('[data-cy="contextResourceShare"] span').contains(nonLocalUser.userId).should('exist')
        cy.get('[data-cy="editContextSubmitBtn"]').click()

        // Hiding the context from nav for the current user
        cy.get('[data-cy="navigationContextItem"]').contains(contextTitle).click({ force: true })
        cy.get('[data-cy="navigationContextShowInNavSwitch"] input').should('be.checked')
        cy.get('[data-cy="navigationContextShowInNavSwitch"] input').click({ force: true })
        cy.get('[data-cy="navigationContextShowInNavSwitch"] input').should('not.be.checked')
        cy.get(`#header .app-menu-entry [title="${contextTitle}"]`).should('not.exist')

        // Confirming that the context is still shown by default
        // in the navigation for the shared user
        cy.login(nonLocalUser)
        cy.visit('apps/tables')
        cy.get(`#header .app-menu-entry [title="${contextTitle}"]`).should('exist')
    })
})