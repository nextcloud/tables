/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
let localUser

describe('Mandatory Column Functionality', () => {

	before(function() {
		cy.createRandomUser().then(user => {
			localUser = user
		})
	})

	beforeEach(function() {
		cy.login(localUser)
		cy.visit('apps/tables')
	})

	it('Setup table with mandatory test columns and one row', () => {
		cy.createTable('Mandatory test table')
		cy.createTextLineColumn('title', null, null, true)
		cy.createTextLineColumn('description', null, null, false)

		// create one row
		cy.get('[data-cy="createRowBtn"]').click()
		cy.fillInValueTextLine('title', 'first row')
		cy.fillInValueTextLine('description', 'desc 1')
		cy.get('[data-cy="createRowSaveButton"]').click()

		// create a default view
		cy.get('[data-cy="customTableAction"] button').click()
		cy.get('.v-popper__popper li button span')
			.contains('Create view')
			.click({ force: true })
		cy.get('[data-cy="viewSettingsDialog"]').should('be.visible')
		cy.get('[data-cy="viewSettingsDialogSection"] input').type('Mandatory test view')
		cy.get('[data-cy="modifyViewBtn"]').click()
		cy.get('.icon-loading').should('not.exist')
	})

	describe('SelectedViewColumns - Mandatory Checkbox', () => {
		beforeEach(() => {
			cy.loadTable('Mandatory test table')

			// create a new view
			cy.get('[data-cy="customTableAction"] button').click()
			cy.get('[data-cy="dataTableCreateViewBtn"]').contains('Create view').click({ force: true })
			cy.get('[data-cy="viewSettingsDialogSection"] input').type('Mandatory test view')

			// ensure dialog is visible
			cy.get('[data-cy="viewSettingsDialog"]').should('be.visible')
		})

		const openColumnMenu = (columnTitle) => {
			cy.contains('.column-entry', columnTitle)
				.find('[data-cy="customColumnAction"] button')
				.click({ force: true })
		}

		const getMandatoryCheckbox = () => cy.get('[data-cy="columnMandatoryCheckbox"]').contains('Mandatory')

		const getReadonlyCheckbox = () => cy.get('[data-cy="columnReadonlyCheckbox"]').contains('Read only')

		it('should display mandatory checkbox for selected columns', () => {
			openColumnMenu('title')
			getMandatoryCheckbox().should('be.visible')
		})

		it('should disable mandatory checkbox when readonly is enabled', () => {
			openColumnMenu('title')

			getReadonlyCheckbox().should('be.visible').click({ force: true })

			// Check that the readonly checkbox is checked
			cy.get('[data-cy="columnReadonlyCheckbox"] input').should('be.checked')

			// Check that mandatory checkbox input is disabled
			cy.get('[data-cy="columnMandatoryCheckbox"] input').should('be.disabled')
		})

		it('should disable readonly checkbox when mandatory is enabled', () => {
			openColumnMenu('title')

			getMandatoryCheckbox().should('be.visible').click({ force: true })

			// Check that the mandatory checkbox is checked
			cy.get('[data-cy="columnMandatoryCheckbox"] input').should('be.checked')

			// Check that readonly checkbox input is disabled
			cy.get('[data-cy="columnReadonlyCheckbox"] input').should('be.disabled')
		})
	})

	describe('EditRow - Mandatory Field Validation', () => {
		beforeEach(() => {
			cy.loadTable('Mandatory test table')

			// Create a view with mandatory settings first
			cy.get('[data-cy="customTableAction"] button').click()
			cy.get('[data-cy="dataTableCreateViewBtn"]').contains('Create view').click({ force: true })
			cy.get('[data-cy="viewSettingsDialogSection"] input').type('Mandatory validation test view')

			// Set title column as mandatory in the view
			cy.contains('.column-entry', 'title')
				.find('[data-cy="customColumnAction"] button')
				.click({ force: true })
			cy.get('[data-cy="columnMandatoryCheckbox"]').contains('Mandatory').click({ force: true })

			// Save the view
			cy.get('[data-cy="modifyViewBtn"]').click()
			cy.get('.icon-loading').should('not.exist')

			// Now open edit row dialog
			cy.get('[data-cy="editRowBtn"]').first().click()
			cy.get('[data-cy="editRowModal"]').should('be.visible')
		})

		it('should show error when mandatory field is empty', () => {
			cy.get('[data-cy="editRowModal"] input').first().clear().blur()

			// Try multiple possible selectors for NcNoteCard with type="error"
			cy.get('[data-cy="editRowModal"]').within(() => {
				// Try different possible selectors
				cy.get('.notecard--error, .note-card--error, [type="error"], .notecard[type="error"], .error', { timeout: 5000 })
					.should('exist')
			})
		})

		it('should disable save button when mandatory field is empty', () => {
			// Clear the mandatory field (should be the title field which is mandatory)
			cy.get('[data-cy="editRowModal"] input').first().clear()

			// Trigger validation by blurring and maybe clicking somewhere else
			cy.get('[data-cy="editRowModal"] input').first().blur()

			// Wait a bit for validation to process
			cy.wait(500)

			// Check that save button is disabled
			cy.get('[data-cy="editRowSaveButton"]', { timeout: 5000 }).should('be.disabled')
		})

		it('should enable save button when mandatory field is filled', () => {
			cy.get('[data-cy="editRowModal"] input').first().type('filled value')
			cy.get('[data-cy="editRowSaveButton"]', { timeout: 5000 }).should('not.be.disabled')
		})
	})
})
