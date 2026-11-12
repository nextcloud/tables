/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
let localUser

describe('Import csv', () => {

	before(function() {
		cy.createRandomUser().then(user => {
			localUser = user
			cy.login(localUser)
			cy.uploadFile('test-import-small.csv', 'text/csv')
			cy.uploadFile('test-import-large.csv', 'text/csv')
		})
	})

	beforeEach(function() {
		cy.login(localUser)
		cy.visit('apps/tables')
	})

	it('Import small csv from Files', () => {
		cy.loadTable('Welcome to Nextcloud Tables!')
		cy.clickOnTableThreeDotMenu('Import rows')
		cy.get('.modal__content button').contains('Select from Files').click()
		cy.get('.file-picker__files').contains('test-import-small').click()
		cy.get('.file-picker button span').contains('Import').click()
		cy.get('.modal__content .import-filename', { timeout: 5000 }).should('be.visible')

		cy.intercept({ method: 'POST', url: '**/apps/tables/import-preview/**' }).as('importPreviewPath')
		cy.get('.modal__content button').contains('Preview').click()
		cy.wait('@importPreviewPath')
		cy.get('.file_import__preview tbody tr', { timeout: 20000 }).should('have.length', 4)

		cy.intercept({ method: 'POST', url: '**/apps/tables/v2/import/table/*' }).as('importUploadReq')
		cy.get('.modal__content button').contains('Import').click()
		cy.wait('@importUploadReq')
		cy.get('[data-cy="importResultColumnsFound"]').should('contain.text', '4')
		cy.get('[data-cy="importResultColumnsMatch"]').should('contain.text', '4')
		cy.get('[data-cy="importResultColumnsCreated"]').should('contain.text', '0')
		cy.get('[data-cy="importResultRowsInserted"]').should('contain.text', '3')
		cy.get('[data-cy="importResultParsingErrors"]').should('contain.text', '0')
		cy.get('[data-cy="importResultRowErrors"]').should('contain.text', '0')
	})

	it('Import large csv from Files', () => {
		cy.loadTable('Welcome to Nextcloud Tables!')
		cy.clickOnTableThreeDotMenu('Import rows')
		cy.get('.modal__content button').contains('Select from Files').click()
		cy.get('.file-picker__files').contains('test-import-large').click()
		cy.get('.file-picker button span').contains('Import').click()
		cy.get('.modal__content .import-filename', { timeout: 5000 }).should('be.visible')

		cy.intercept({ method: 'POST', url: '**/apps/tables/import-preview/**' }).as('importPreviewPath')
		cy.get('.modal__content button').contains('Preview').click()
		cy.wait('@importPreviewPath')
		cy.get('.file_import__preview tbody tr', { timeout: 20000 }).should('have.length', 4)

		cy.intercept({ method: 'POST', url: '**/apps/tables/v2/import/table/*' }).as('importUploadReq')
		cy.get('.modal__content button').contains('Import').click()
		cy.wait('@importUploadReq').its('response.statusCode').should('equal', 200)

		// The import runs as a queued background job — execute it via cron.php CLI
		let cronResult
		cy.task('exec', { command: 'php ../../cron.php -v "OCA\\Tables\\BackgroundJob\\ImportTableJob"', timeout: 120000 }, { timeout: 130000 })
			.then(result => {
				cronResult = result
				cy.task('log', `[cron.php] code=${result.code}\nstdout: ${result.stdout}\nstderr: ${result.stderr}`)
			})

		const waitForProblemsFile = (retries = 15) => {
			cy.task('exec', { command: 'find ../../data -name "*import-problems*" -print -quit' })
				.then(result => {
					if (result.stdout.trim() !== '') {
						cy.visit('apps/files/files?dir=/Tables/Imports')
						cy.get('[data-cy-files-list-row-name*="import-problems"]', { timeout: 20000 })
							.should('be.visible')
						return
					}
					if (retries <= 0) {
						cy.task('exec', { command: 'tail -500 ../../data/nextcloud.log | grep -i "import\\|report" | tail -40' })
							.then(r => {
								throw new Error('Import problems file was not created. '
									+ `cron.php: code=${cronResult?.code} stderr=${JSON.stringify(cronResult?.stderr)}\n`
									+ `log tail: ${r.stdout}`)
							})
						return
					}
					cy.wait(2000)
					waitForProblemsFile(retries - 1)
				})
		}
		waitForProblemsFile()
	})

	it('Import small csv from device', () => {
		cy.loadTable('Welcome to Nextcloud Tables!')
		cy.clickOnTableThreeDotMenu('Import rows')
		cy.get('.modal__content button').contains('Upload from device').click()
		cy.get('input[type="file"]').selectFile('cypress/fixtures/test-import-small.csv', { force: true })

		cy.intercept({ method: 'POST', url: '**/apps/tables/importupload-preview/**' }).as('importPreviewUpload')
		cy.get('.modal__content button').contains('Preview').click()
		cy.wait('@importPreviewUpload')
		cy.get('.file_import__preview tbody tr', { timeout: 20000 }).should('have.length', 4)

		cy.intercept({ method: 'POST', url: '**/apps/tables/v2/importupload/table/*' }).as('importUploadReq')
		cy.get('.modal__content button').contains('Import').click()
		cy.wait('@importUploadReq')
		cy.get('[data-cy="importResultColumnsFound"]', { timeout: 20000 }).should('contain.text', '4')
		cy.get('[data-cy="importResultColumnsMatch"]').should('contain.text', '4')
		cy.get('[data-cy="importResultColumnsCreated"]').should('contain.text', '0')
		cy.get('[data-cy="importResultRowsInserted"]').should('contain.text', '3')
		cy.get('[data-cy="importResultParsingErrors"]').should('contain.text', '0')
		cy.get('[data-cy="importResultRowErrors"]').should('contain.text', '0')
	})

	it('Import large csv from device', () => {
		cy.loadTable('Welcome to Nextcloud Tables!')
		cy.clickOnTableThreeDotMenu('Import rows')
		cy.get('.modal__content button').contains('Upload from device').click()
		cy.get('input[type="file"]').selectFile('cypress/fixtures/test-import-large.csv', { force: true })

		cy.intercept({ method: 'POST', url: '**/apps/tables/importupload-preview/**' }).as('importPreviewUpload')
		cy.get('.modal__content button').contains('Preview').click()
		cy.wait('@importPreviewUpload')
		cy.get('.file_import__preview tbody tr', { timeout: 20000 }).should('have.length', 4)

		cy.intercept({ method: 'POST', url: '**/apps/tables/v2/importupload/table/*' }).as('importUploadReq')
		cy.get('.modal__content button').contains('Import').click()
		cy.wait('@importUploadReq').its('response.statusCode').should('equal', 200)
	})

	it('Import csv from device with updating of existent files and a broken row', () => {
		cy.intercept({ method: 'GET', url: '**/apps/tables/row/table/*' }).as('rowsReq')

		cy.loadTable('Welcome to Nextcloud Tables!')

		cy.wait('@rowsReq').then(({ response }) => {
			const firstRow = response.body[0]
			const csv = [
				['id', 'What', 'How to do'],
				[firstRow.id, 'What (Updated)', 'How to do (Updated)'],
				['not-a-number', 'Skipped row', 'Skipped row'],
			]

			cy.writeFile('cypress/fixtures/test-import-update.csv', csv.map(row => row.join(',')).join('\n'))
		})

		cy.clickOnTableThreeDotMenu('Import rows')
		cy.get('.modal__content button').contains('Upload from device').click()
		cy.get('input[type="file"]').selectFile('cypress/fixtures/test-import-update.csv', { force: true })

		cy.intercept({ method: 'POST', url: '**/apps/tables/importupload-preview/**' }).as('importPreviewUpdate')
		cy.get('.modal__content button').contains('Preview').click()
		cy.wait('@importPreviewUpdate')
		cy.get('.file_import__preview tbody tr', { timeout: 20000 }).should('have.length', 3)

		cy.intercept({ method: 'POST', url: '**/apps/tables/v2/importupload/table/*' }).as('importUploadReq')
		cy.get('.modal__content button').contains('Import').click()
		cy.wait('@importUploadReq')
		cy.get('[data-cy="importResultColumnsFound"]', { timeout: 20000 }).should('contain.text', '2')
		cy.get('[data-cy="importResultColumnsMatch"]').should('contain.text', '3')
		cy.get('[data-cy="importResultColumnsCreated"]').should('contain.text', '0')
		cy.get('[data-cy="importResultRowsInserted"]').should('contain.text', '0')
		cy.get('[data-cy="importResultRowsUpdated"]').should('contain.text', '1')
		cy.get('[data-cy="importResultParsingErrors"]').should('contain.text', '0')
		cy.get('[data-cy="importResultRowErrors"]').should('contain.text', '1')
		cy.get('[data-cy="importResultProblems"] tbody tr').should('have.length', 1)
		cy.get('[data-cy="importResultProblems"] tbody tr').first()
			.should('contain.text', '3')
			.and('contain.text', 'not numeric')
	})

	it('Import small csv from device with an extra ignored column', () => {
		const csv = [
			['What', 'How to do', 'Ease of use', 'Done', 'Extra column to ignore'],
			['A1', 'A2', '0', 'true', 'ignore1'],
			['B1', 'B2', '2', 'true', 'ignore2'],
			['C1', 'C2', '5', 'false', 'ignore3'],
		]
		cy.writeFile('cypress/fixtures/test-import-with-extra.csv', csv.map(row => row.join(',')).join('\n'))

		cy.loadTable('Welcome to Nextcloud Tables!')
		cy.clickOnTableThreeDotMenu('Import rows')
		cy.get('.modal__content button').contains('Upload from device').click()
		cy.get('input[type="file"]').selectFile('cypress/fixtures/test-import-with-extra.csv', { force: true })

		cy.get('.modal__content input[type="checkbox"]').first().uncheck({ force: true })

		cy.intercept({ method: 'POST', url: '**/apps/tables/importupload-preview/**' }).as('importPreviewUploadExtra')
		cy.get('.modal__content button').contains('Preview').click()
		cy.wait('@importPreviewUploadExtra')
		cy.get('.file_import__preview tbody tr', { timeout: 20000 }).should('have.length', 5)

		cy.intercept({ method: 'POST', url: '**/apps/tables/v2/importupload/table/*' }).as('importUploadReqExtra')
		cy.get('.modal__content button').contains('Import').click()
		cy.wait('@importUploadReqExtra')
		cy.get('[data-cy="importResultColumnsFound"]', { timeout: 20000 }).should('contain.text', '4')
		cy.get('[data-cy="importResultColumnsMatch"]').should('contain.text', '4')
		cy.get('[data-cy="importResultColumnsCreated"]').should('contain.text', '0')
		cy.get('[data-cy="importResultRowsInserted"]').should('contain.text', '3')
		cy.get('[data-cy="importResultParsingErrors"]').should('contain.text', '0')
		cy.get('[data-cy="importResultRowErrors"]').should('contain.text', '0')
	})

})

describe('Import csv from Files file action', () => {
	const importToTablesAction = '[data-cy-files-list-row-action="import-to-tables"]'
	const openImportToTablesAction = (file, retries = 8) => {
		const rowActionsButton = `[data-cy-files-list-row-name="${file}"] [data-cy-files-list-row-actions] .action-item button`
		cy.get(rowActionsButton).click()
		cy.get('body').then($body => {
			if ($body.find(importToTablesAction).length > 0) {
				cy.get(importToTablesAction).first().click()
				return
			}

			if (retries <= 0) {
				throw new Error(`Import to Tables action did not become available in file actions menu for file: ${file}`)
			}

			cy.get('body').click(0, 0)
			cy.wait(1000)
			openImportToTablesAction(file, retries - 1)
		})
	}

	before(function() {
		cy.createRandomUser().then(user => {
			localUser = user
			cy.login(localUser)
			cy.uploadFile('test-import-small.csv', 'text/csv')
			cy.uploadFile('test-import-large.csv', 'text/csv')
			cy.visit('apps/tables')
			cy.get('[data-cy="navigationTableItem"] a[title="Welcome to Nextcloud Tables!"]', { timeout: 20000 })
				.should('exist')
		})
	})

	beforeEach(function() {
		cy.login(localUser)
		cy.visit('apps/files/files', { timeout: 120000 })
		cy.get('[data-cy-files-list-row-name="test-import-small.csv"]', { timeout: 120000 }).should('be.visible')
		cy.get('[data-cy-files-list-row-name="test-import-large.csv"]', { timeout: 120000 }).should('be.visible')
	})

	it('Import small file to new table', () => {
		openImportToTablesAction('test-import-small.csv')

		cy.intercept({
			method: 'POST',
			url: '**/apps/tables/v2/import/table/*',
		}).as('importNewTableReq')
		cy.get('[data-cy="fileActionImportButton"]').click({ force: true })
		cy.wait('@importNewTableReq').its('response.statusCode').should('equal', 200)

		cy.get('[data-cy="importResultColumnsFound"]').should('contain.text', '4')
		cy.get('[data-cy="importResultColumnsMatch"]').should('contain.text', '0')
		cy.get('[data-cy="importResultColumnsCreated"]').should('contain.text', '4')
		cy.get('[data-cy="importResultRowsInserted"]').should('contain.text', '3')
		cy.get('[data-cy="importResultParsingErrors"]').should('contain.text', '0')
		cy.get('[data-cy="importResultRowErrors"]').should('contain.text', '0')
	})

	it('Import large file to new table', () => {
		openImportToTablesAction('test-import-large.csv')

		cy.intercept({
			method: 'POST',
			url: '**/apps/tables/v2/import/table/*',
		}).as('importNewTableReq')
		cy.get('[data-cy="fileActionImportButton"]').click({ force: true })
		cy.wait('@importNewTableReq').its('response.statusCode').should('equal', 200)
	})

	it('Import small file to existing table', () => {
		openImportToTablesAction('test-import-small.csv')

		cy.get('.modal__content [data-cy="importAsNewTableSwitch"] input').uncheck({ force: true })
		cy.get('[data-cy="selectExistingTableDropdown"]').type('Welcome to Nextcloud Tables!')
		cy.get('.vs__dropdown-menu li')
			.contains('Welcome to Nextcloud Tables!', { timeout: 20000 })
			.click()

		cy.intercept({
			method: 'POST',
			url: '**/apps/tables/v2/import/table/*',
		}).as('importExistingTableReq')
		cy.get('[data-cy="fileActionImportButton"]').click({ force: true })
		cy.wait('@importExistingTableReq').its('response.statusCode').should('equal', 200)

		cy.get('[data-cy="importResultColumnsFound"]').should('contain.text', '4')
		cy.get('[data-cy="importResultColumnsMatch"]').should('contain.text', '4')
		cy.get('[data-cy="importResultColumnsCreated"]').should('contain.text', '0')
		cy.get('[data-cy="importResultRowsInserted"]').should('contain.text', '3')
		cy.get('[data-cy="importResultParsingErrors"]').should('contain.text', '0')
		cy.get('[data-cy="importResultRowErrors"]').should('contain.text', '0')
	})

})
