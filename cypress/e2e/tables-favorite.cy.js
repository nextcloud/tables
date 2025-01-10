/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
let localUser

describe('Favorite tables/views', () => {

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

  it('can favorite tables', () => {
    cy.get('[data-cy="navigationTableItem"]').first().as('tutorialTable')

    cy.get('@tutorialTable').should('contain.text', 'Welcome to Nextcloud Tables!')
    cy.get('@tutorialTable').find('[aria-haspopup="menu"]').first().click({ force: true })

    cy.intercept({ method: 'POST', url: '**/ocs/v2.php/apps/tables/api/2/favorites/*/*'}).as('favoriteTableReq')
    cy.contains('Add to favorites').click({ force: true })
    cy.wait('@favoriteTableReq').its('response.statusCode').should('equal', 200)

    cy.get('@tutorialTable').parent().should('contain.text', 'Favorites')
  })

  it('can remove favorite table', () => {
    cy.get('[data-cy="navigationTableItem"]').first().as('tutorialTable')

    cy.get('@tutorialTable').should('contain.text', 'Welcome to Nextcloud Tables!')
    cy.get('@tutorialTable').find('[aria-haspopup="menu"]').first().click({ force: true })

    cy.intercept({ method: 'DELETE', url: '**/ocs/v2.php/apps/tables/api/2/favorites/*/*' }).as('unfavoriteTableReq')
    cy.contains('Remove from favorites').click({ force: true })
    cy.wait('@unfavoriteTableReq').its('response.statusCode').should('equal', 200)

    cy.get('@tutorialTable').parent().should('contain.text', 'Tables')
  })

  it('can favorite views', () => {
    cy.loadTable('Welcome to Nextcloud Tables!')

    cy.get('[data-cy="navigationViewItem"]').first().as('testView')

    cy.get('@testView').parent().parent().parent().should('contain.text', 'Welcome to ')
    cy.get('@testView').find('[aria-haspopup="menu"]').click({ force: true })

    cy.intercept({ method: 'POST', url: '**/ocs/v2.php/apps/tables/api/2/favorites/*/*' }).as('favoriteViewReq')
    cy.contains('Add to favorites').click({ force: true })
    cy.wait('@favoriteViewReq').its('response.statusCode').should('equal', 200)

    cy.get('@testView').parent().should('contain.text', 'Favorites')
  })

  it('can unfavorite views', () => {
    cy.get('[data-cy="navigationViewItem"]').first().as('testView')

    cy.get('@testView').parent().should('contain.text', 'Favorites')
    cy.get('@testView').find('[aria-haspopup="menu"]').click({ force: true })

    cy.intercept({ method: 'DELETE', url: '**/ocs/v2.php/apps/tables/api/2/favorites/*/*' }).as('unfavoriteViewReq')
    cy.contains('Remove from favorites').click({ force: true })
    cy.wait('@unfavoriteViewReq').its('response.statusCode').should('equal', 200)

    cy.get('@testView').parent().parent().parent().should('contain.text', 'Welcome to ')
  })

  it('can (un)favorite views with favorited parent tables', () => {
    cy.get('[data-cy="navigationViewItem"]').first().as('testView')
    cy.get('[data-cy="navigationTableItem"]').first().as('tutorialTable')

    cy.get('@testView').parent().parent().parent().should('contain.text', 'Welcome to ')
    cy.get('@testView').find('[aria-haspopup="menu"]').click({ force: true })
    cy.contains('Add to favorites').click({ force: true })

    cy.get('@testView').parent().should('contain.text', 'Favorites')

    cy.get('@tutorialTable').should('contain.text', 'Welcome to Nextcloud Tables!')
    cy.get('@tutorialTable').find('[aria-haspopup="menu"]').first().click({ force: true })
    cy.contains('Add to favorites').click({ force: true })

    cy.get('@tutorialTable').parent().should('contain.text', 'Tables')
    cy.get('@testView').parent().should('not.contain.text', 'Favorites')
  })

})
