/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
let localUser
let nonLocalUser
const columnTitle = 'usergroup'
const tableTitlePrefix = 'Test usergroup'
let tableTitle = tableTitlePrefix
let testNumber = 0

describe('Test column ' + columnTitle, () => {

	before(function() {
		cy.createRandomUser().then(user => {
			localUser = user
		})

        cy.createRandomUser().then(user => {
			nonLocalUser = user
		})
	})

	beforeEach(function() {
        testNumber += 1
        tableTitle = `${tableTitlePrefix} ${testNumber}`
		cy.login(localUser)
		cy.visit('apps/tables')
	})

	it('Create column and rows with default values', () => {
		cy.createTable(tableTitle)
        cy.loadTable(tableTitle)
        cy.createUsergroupColumn(columnTitle, true, true, true, true, [localUser.userId, nonLocalUser.userId], true)
        cy.get('button').contains('Create row').click()
        cy.get('[data-cy="createRowSaveButton"]').click()
        cy.get('[data-cy="ncTable"] table tr td .user-bubble__name').contains(localUser.userId).should('be.visible')
        cy.get('[data-cy="ncTable"] table tr td .user-bubble__name').contains(nonLocalUser.userId).should('be.visible')
	})

    it('Create column and rows without default values', () => {
		cy.createTable(tableTitle)
        cy.loadTable(tableTitle)
        cy.createUsergroupColumn(columnTitle, true, false, false, false, [], true)
        
        cy.get('button').contains('Create row').click()
        cy.get('[data-cy="usergroupRowSelect"] input').type(nonLocalUser.userId)
        cy.get(`.vs__dropdown-menu [id="${nonLocalUser.userId}"]`).click()
        cy.get('[data-cy="createRowSaveButton"]').click()
        cy.get('[data-cy="ncTable"] table tr td .user-bubble__name').contains(nonLocalUser.userId).should('be.visible')
	})

    it('Create and edit rows', () => {
		cy.createTable(tableTitle)
        cy.loadTable(tableTitle)
        cy.createUsergroupColumn(columnTitle, true, true, true, true, [localUser.userId], true)
        cy.get('button').contains('Create row').click()
        cy.get('[data-cy="createRowSaveButton"]').click()
        cy.get('[data-cy="ncTable"] table tr td .user-bubble__name').contains(localUser.userId).should('be.visible')

        cy.get('[data-cy="ncTable"] [data-cy="editRowBtn"]').click()
        cy.get('[data-cy="usergroupRowSelect"] .vs__deselect').click({ multiple: true })
        cy.get('[data-cy="usergroupRowSelect"] input').clear().type(nonLocalUser.userId)
        cy.get(`.vs__dropdown-menu [id="${nonLocalUser.userId}"]`).click()
        cy.get('[data-cy="editRowSaveButton"]').click()
        cy.get('[data-cy="ncTable"] table tr td .user-bubble__name').contains(localUser.userId).should('not.exist')
        cy.get('[data-cy="ncTable"] table tr td .user-bubble__name').contains(nonLocalUser.userId).should('be.visible')
	})


})
