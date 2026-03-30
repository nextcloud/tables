/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
let localUser

describe('Archive tables/views', () => {

  before(function() {
    cy.createRandomUser().then(user => {
      localUser = user
    })
  })

  beforeEach(function() {
    cy.login(localUser)
    cy.visit('apps/tables')
    cy.wait(1000)
  })

  it('can archive tables', () => {
    cy.get('[data-cy="navigationTableItem"]').first().as('tutorialTable')

    cy.get('@tutorialTable').should('contain.text', 'Welcome to Nextcloud Tables!')
    cy.get('@tutorialTable').find('[aria-haspopup="menu"]').first().click({ force: true })

    cy.intercept({ method: 'PUT', url: '**/apps/tables/api/2/tables/*'}).as('archiveTableReq')
    cy.contains('Archive table').click({ force: true })

    cy.wait('@archiveTableReq').then(request => {
      expect(request.response.statusCode).to.equal(200)
      expect(request.response.body.ocs.data.archived).to.equal(true)
    })

    cy.get('@tutorialTable').parent().parent().should('contain.text', 'Archived tables')
  })

  it('can unarchive tables', () => {
    cy.get('[data-cy="navigationTableItem"]').first().as('tutorialTable')

    cy.get('@tutorialTable').should('contain.text', 'Welcome to Nextcloud Tables!')
    cy.get('@tutorialTable').find('[aria-haspopup="menu"]').first().click({ force: true })

    cy.intercept({ method: 'PUT', url: '**/apps/tables/api/2/tables/*' }).as('unarchiveTableReq')
    cy.contains('Unarchive table').click({ force: true })

    cy.wait('@unarchiveTableReq').then(request => {
      expect(request.response.statusCode).to.equal(200)
      expect(request.response.body.ocs.data.archived).to.equal(false)
    })

    cy.get('@tutorialTable').parent().should('contain.text', 'Tables')
  })
})
