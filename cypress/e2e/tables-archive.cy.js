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
  })

  it('can archive tables', () => {
    cy.get('[data-cy="navigationTableItem"]').first().as('tutorialTable')

    cy.get('@tutorialTable').should('contain.text', 'Tutorial')
    cy.get('@tutorialTable').find('[aria-haspopup="menu"]').click({ force: true })

    cy.intercept({ method: 'PUT', url: '**/apps/tables/table/*'}).as('archiveTableReq')
    cy.contains('Archive table').click({ force: true })

    cy.wait('@archiveTableReq').then(request => {
      expect(request.response.statusCode).to.equal(200)
      expect(request.response.body.archived).to.equal(true)
    })

    cy.get('@tutorialTable').parent().parent().should('contain.text', 'Archived tables')
  })

  it('can unarchive tables', () => {
    cy.get('[data-cy="navigationTableItem"]').first().as('tutorialTable')

    cy.get('@tutorialTable').should('contain.text', 'Tutorial')
    cy.get('@tutorialTable').find('[aria-haspopup="menu"]').click({ force: true })

    cy.intercept({ method: 'PUT', url: '**/apps/tables/table/*' }).as('unarchiveTableReq')
    cy.contains('Unarchive table').click({ force: true })

    cy.wait('@unarchiveTableReq').then(request => {
      expect(request.response.statusCode).to.equal(200)
      expect(request.response.body.archived).to.equal(false)
    })

    cy.get('@tutorialTable').parent().should('contain.text', 'Tables')
  })
})
