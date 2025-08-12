/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
// ***********************************************
// This example commands.js shows you how to
// create various custom commands and overwrite
// existing commands.
//
// For more comprehensive examples of custom
// commands please read more here:
// https://on.cypress.io/custom-commands
// ***********************************************
//
//
// -- This is a parent command --
// Cypress.Commands.add('login', (email, password) => { ... })
//
//
// -- This is a child command --
// Cypress.Commands.add('drag', { prevSubject: 'element'}, (subject, options) => { ... })
//
//
// -- This is a dual command --
// Cypress.Commands.add('dismiss', { prevSubject: 'optional'}, (subject, options) => { ... })
//
//
// -- This will overwrite an existing command --
// Cypress.Commands.overwrite('visit', (originalFn, url, options) => { ... })
import { addCommands } from '@nextcloud/cypress'
import { emit } from '@nextcloud/event-bus'
import 'cypress-downloadfile/lib/downloadFileCommand'

const url = Cypress.config('baseUrl').replace(/\/index.php\/?$/g, '')
Cypress.env('baseUrl', url)
const silent = { log: false }

addCommands()

// Copy of the new login command as long as we are blocked to upgrade @nextcloud/cypress by cypress crashes
const login = function(user) {
	cy.session(user, function() {
		cy.request('/csrftoken').then(({ body }) => {
			const requestToken = body.token
			cy.request({
				method: 'POST',
				url: '/login',
				body: {
					user: user.userId,
					password: user.password,
					requesttoken: requestToken,
				},
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
					// Add the Origin header so that the request is not blocked by the browser.
					Origin: (Cypress.config('baseUrl') ?? '').replace('index.php/', ''),
				},
				followRedirect: false,
			})
		})
	}, {
		validate() {
			cy.request('/apps/files').its('status').should('eq', 200)
		},
	})
}

// Prepare the csrf-token for axios
Cypress.Commands.overwrite('login', (_login, user) => {
	cy.window(silent).then((win) => {
		win.location.href = 'about:blank'
	})
	login(user)
	cy.request('/csrftoken', silent)
		.then(({ body }) => emit('csrf-token-update', body))
	cy.wrap(user, silent).as('currentUser')
})

Cypress.Commands.add('createTable', (title) => {
	cy.get('.icon-loading').should('not.exist')
	cy.get('[data-cy="navigationCreateTableIcon"]').click({ force: true })
	cy.get('[data-cy="createTableModal"]').should('be.visible')
	// should type before selecting the table type tile
	cy.get('[data-cy="createTableModal"] input[type="text"]').clear().type(title)
	cy.get('.tile').contains('Custom table').click({ force: true })
	cy.contains('button', 'Create table').click()
	cy.contains('h1', title).should('be.visible')
})

Cypress.Commands.add('deleteTable', (title) => {
	cy.get('[data-cy="navigationTableItem"]').contains(title).click({ force: true })
	cy.get('[data-cy="customTableAction"] button').click()
	cy.get('[data-cy="dataTableEditTableBtn"]').click()
	cy.get('[data-cy="editTableModal"] [data-cy="editTableDeleteBtn"]').click()
	cy.get('[data-cy="editTableModal"] [data-cy="editTableConfirmDeleteBtn"]').click()
	cy.wait(10).get('.toastify.toast-success').should('be.visible')
	cy.get('[data-cy="navigationTableItem"]').contains(title).should('not.exist')
})


Cypress.Commands.add('deleteRow', (rowIndex) => {
	cy.get('[data-cy="ncTable"] [data-cy="editRowBtn"]').eq(rowIndex).click()
	cy.get('[data-cy="editRowDeleteButton"]').click({ force: true })
	cy.get('[data-cy="editRowDeleteConfirmButton"]').click({ force: true })
})

Cypress.Commands.add('createView', (title) => {
	cy.get('[data-cy="customTableAction"] button').click()
	cy.get('[data-cy="dataTableCreateViewBtn"]').contains('Create view').click({ force: true })

	cy.get('[data-cy="viewSettingsDialogTitleInput"]').should('be.visible').should('not.be.disabled').type(title)

	cy.intercept({ method: 'POST', url: '**/apps/tables/view' }).as('createView')
	cy.intercept({ method: 'PUT', url: '**/apps/tables/view/*' }).as('updateView')
	cy.contains('button', 'Create View').click()
	cy.wait('@createView')
	cy.wait('@updateView')

	cy.contains('.app-navigation-entry-link span', title).should('exist')
})

Cypress.Commands.add('openCreateColumnModal', (isFirstColumn) => {
	if (isFirstColumn) {
		cy.get('.button-vue__text').contains('Create column').click({ force: true })
	} else {
		cy.get('[data-cy="customTableAction"] button').click()
		cy.get('[data-cy="dataTableCreateColumnBtn"]').contains('Create column').click({ force: true })
	}
})

Cypress.Commands.add('createContext', (title, showInNav = false) => {
	cy.get('ul:nth-of-type(2) [data-cy="createContextIcon"]').click({ force: true })
	cy.get('[data-cy="createContextModal"]').should('be.visible')
	cy.get('[data-cy="createContextTitle"]').clear().type(title)
	if (showInNav) {
		cy.get('[data-cy="createContextShowInNavSwitch"]').click()
	}
	cy.get('[data-cy="createContextSubmitBtn"]').click()

	// verify context was created properly
	cy.get('[data-cy="navigationContextItem"]').contains(title).should('exist')
	cy.contains('h1', title).should('exist')
})

Cypress.Commands.add('openContextEditModal', (title) => {
	cy.get(`[data-cy="navigationContextItem"]:contains("${title}")`).find('button').click({ force: true })
	cy.wait(1000)
	cy.get('[data-cy="navigationContextEditBtn"]').contains('Edit application').click({ force: true })
	cy.get('[data-cy="editContextModal"]').should('be.visible')
})

Cypress.Commands.add('clickOnTableThreeDotMenu', (optionName) => {
	cy.get('[data-cy="customTableAction"] button').click()
	cy.get('[data-cy="dataTableExportBtn"]').contains(optionName).click({ force: true })
})

Cypress.Commands.add('sortTableColumn', (columnTitle, mode = 'ASC') => {
	cy.get('th').contains(columnTitle).click()
	if (mode === 'ASC') {
		cy.get('ul li.action button[aria-label="Sort asc"]').click()
	} else {
		cy.get('ul li.action button[aria-label="Sort desc"]').click()
	}
})

Cypress.Commands.add('loadTable', (name) => {
	cy.get('[data-cy="navigationTableItem"] a[title="' + name + '"]').last().click({ force: true })
})

Cypress.Commands.add('getTutorialTableName', () => {
	return cy.get('[data-cy="navigationTableItem"] a[title^="Welcome to"]').invoke('attr', 'title').then((title) => {
		return cy.wrap(title)
	})
})

Cypress.Commands.add('loadView', (name) => {
	cy.get('[data-cy="navigationViewItem"] a[title="' + name + '"]').click({ force: true })
})

Cypress.Commands.add('loadContext', (title) => {
	cy.get('[data-cy="navigationContextItem"]').contains(title).click({ force: true })
})

Cypress.Commands.add('unifiedSearch', (term) => {
	cy.get('#unified-search').click()
	cy.get('#unified-search__input').type(term)
	cy.get('.unified-search__results .unified-search__result-line-one span').contains(term, { matchCase: false }).should('exist')
})

Cypress.Commands.add('createUsergroupColumn', (title, selectUsers, selectGroups, selectCircles, hasMultipleValues, defaultValue, isFirstColumn) => {
	cy.openCreateColumnModal(isFirstColumn)
	cy.get('[data-cy="columnTypeFormInput"]').clear().type(title)
	cy.get('.columnTypeSelection .vs__open-indicator').click({ force: true })
	cy.get('.multiSelectOptionLabel').contains('Users and groups').click({ force: true })

	if (hasMultipleValues) {
		cy.get('[data-cy="usergroupMultipleSwitch"] .checkbox-content').click({ force: true })
	}

	if (selectGroups) {
		cy.get('[data-cy="groupsSwitch"] input').click({ force: true })
	}
	if (selectCircles) {
		cy.get('[data-cy="teamsSwitch"] input').click({ force: true })
	}
	// Users is always checked by default, and we can only disable it if some other option is already enabled
	if (!selectUsers & (selectGroups || selectCircles)) {
		cy.get('[data-cy="usersSwitch"] input').click({ force: true })
	}

	defaultValue.forEach((value) => {
		cy.get('[data-cy="usergroupDefaultSelect"] input').type(value)
		cy.get(`.vs__dropdown-menu [id="${value}"]`).click()
	})

	cy.get('[data-cy="createColumnSaveBtn"]').click()
	cy.wait(10).get('.toastify.toast-success').should('be.visible')
	cy.get('[data-cy="ncTable"] table tr th').contains(title).should('exist')
})

Cypress.Commands.add('createTextLinkColumn', (title, ressourceProvider, isFirstColumn) => {
	cy.openCreateColumnModal(isFirstColumn)

	cy.get('[data-cy="columnTypeFormInput"]').clear().type(title)
	cy.get('.columnTypeSelection .vs__open-indicator').click({ force: true })
	cy.get('.multiSelectOptionLabel').contains('Link').click({ force: true })
	// deactivate unwanted provider
	cy.get('.typeSelection span').contains('Url', { matchCase: false }).click()
	cy.get('.typeSelection span').contains('Files').click()

	ressourceProvider.forEach(provider =>
		cy.get('.typeSelection span').contains(provider, { matchCase: false }).click(),
	)
	cy.get('.modal-container button').contains('Save').click()

	cy.wait(10).get('.toastify.toast-success').should('be.visible')
	cy.get('.custom-table table tr th .cell').contains(title).should('exist')
})

Cypress.Commands.add('createSelectionColumn', (title, options, defaultOption, isFirstColumn) => {
	cy.openCreateColumnModal(isFirstColumn)

	cy.get('[data-cy="columnTypeFormInput"]').clear().type(title)
	cy.get('.columnTypeSelection .vs__open-indicator').click({ force: true })
	cy.get('.multiSelectOptionLabel').contains('Selection').click({ force: true })
	// remove default option
	cy.get('[data-cy="selectionOption"] button').first().click()
	cy.get('[data-cy="selectionOption"] button').first().click()

	// add wanted option
	options.forEach(option => {
		cy.get('button').contains('Add option').click()
		cy.get('[data-cy="selectionOptionLabel"]').last().type(option)
		if (defaultOption === option) {
			cy.get('[data-cy="selectionOption"] .checkbox-content').last().click()
		}
	})
	cy.get('.modal-container button').contains('Save').click()

	cy.wait(10).get('.toastify.toast-success').should('be.visible')
	cy.get('.custom-table table tr th .cell').contains(title).should('exist')
})


Cypress.Commands.add('createSelectionMultiColumn', (title, options, defaultOptions, isFirstColumn) => {
	cy.openCreateColumnModal(isFirstColumn)

	cy.get('[data-cy="columnTypeFormInput"]').clear().type(title)
	cy.get('.columnTypeSelection .vs__open-indicator').click({ force: true })
	cy.get('.multiSelectOptionLabel').contains('Selection').click({ force: true })
	cy.get('[data-cy="createColumnMultipleSelectionSwitch"]').contains('Multiple selection').click()

	// remove default option
	cy.get('[data-cy="selectionOption"] button').first().click()
	cy.get('[data-cy="selectionOption"] button').first().click()

	// add wanted option
	options?.forEach(option => {
		cy.get('button').contains('Add option').click()
		cy.get('[data-cy="selectionOptionLabel"]').last().type(option)
		if (defaultOptions?.includes(option)) {
			cy.get('[data-cy="selectionOption"] .checkbox-content').last().click()
		}
	})
	cy.get('.modal-container button').contains('Save').click()

	cy.wait(10).get('.toastify.toast-success').should('be.visible')
	cy.get('.custom-table table tr th .cell').contains(title).should('exist')
})

Cypress.Commands.add('createTextLineColumn', (title, defaultValue, maxLength, isFirstColumn) => {
	cy.openCreateColumnModal(isFirstColumn)
	cy.get('[data-cy="columnTypeFormInput"]').clear().type(title)
	if (defaultValue) {
		cy.get('[data-cy="TextLineForm"] input').first().type(defaultValue)
	}
	if (maxLength) {
		cy.get('[data-cy="TextLineForm"] input').eq(1).type(maxLength)
	}
	cy.get('.modal-container button').contains('Save').click()
	cy.wait(10).get('.toastify.toast-success').should('be.visible')
	cy.get('.custom-table table tr th .cell').contains(title).should('exist')
})

Cypress.Commands.add('createDatetimeColumn', (title, setNow, isFirstColumn) => {
	cy.openCreateColumnModal(isFirstColumn)
	cy.get('[data-cy="columnTypeFormInput"]').clear().type(title)
	cy.get('.columnTypeSelection .vs__open-indicator').click({ force: true })
	cy.get('.multiSelectOptionLabel').contains('Date and time').click({ force: true })

	if (setNow) {
		cy.get('[data-cy="datetimeFormNowSwitch"]').click()
	}

	cy.get('.modal-container button').contains('Save').click()
	cy.wait(10).get('.toastify.toast-success').should('be.visible')
	cy.get('.custom-table table tr th .cell').contains(title).should('exist')
})

Cypress.Commands.add('createDatetimeDateColumn', (title, setNow, isFirstColumn) => {
	cy.openCreateColumnModal(isFirstColumn)
	cy.get('[data-cy="columnTypeFormInput"]').clear().type(title)
	cy.get('.columnTypeSelection .vs__open-indicator').click({ force: true })
	cy.get('.multiSelectOptionLabel').contains('Date and time').click({ force: true })
	cy.get('[data-cy="createColumnDateSwitch"]').contains('Date').click()

	if (setNow) {
		cy.get('[data-cy="datetimeDateFormTodaySwitch"]').click()
	}

	cy.get('.modal-container button').contains('Save').click()
	cy.wait(10).get('.toastify.toast-success').should('be.visible')
	cy.get('.custom-table table tr th .cell').contains(title).should('exist')
})

Cypress.Commands.add('createDatetimeTimeColumn', (title, setNow, isFirstColumn) => {
	cy.openCreateColumnModal(isFirstColumn)
	cy.get('[data-cy="columnTypeFormInput"]').clear().type(title)
	cy.get('.columnTypeSelection .vs__open-indicator').click({ force: true })
	cy.get('.multiSelectOptionLabel').contains('Date and time').click({ force: true })
	cy.get('[data-cy="createColumnTimeSwitch"]').contains('Time').click()

	if (setNow) {
		cy.get('[data-cy="datetimeTimeFormNowSwitch"]').click()
	}

	cy.get('.modal-container button').contains('Save').click()
	cy.wait(10).get('.toastify.toast-success').should('be.visible')
	cy.get('.custom-table table tr th .cell').contains(title).should('exist')
})

Cypress.Commands.add('createNumberColumn', (title, defaultValue, decimals, min, max, prefix, suffix, isFirstColumn) => {
	cy.openCreateColumnModal(isFirstColumn)
	cy.get('[data-cy="columnTypeFormInput"]').clear().type(title)
	cy.get('.columnTypeSelection .vs__open-indicator').click({ force: true })
	cy.get('.multiSelectOptionLabel').contains('Number').click({ force: true })

	if (defaultValue) {
		cy.get('[data-cy="NumberForm"] input').eq(0).clear().type(defaultValue)
	}
	if (decimals) {
		cy.get('[data-cy="NumberForm"] input').eq(1).clear().type('' + decimals)
	}
	if (min) {
		cy.get('[data-cy="NumberForm"] input').eq(2).clear().type('' + min)
	}
	if (max) {
		cy.get('[data-cy="NumberForm"] input').eq(3).clear().type('' + max)
	}
	if (prefix) {
		cy.get('[data-cy="NumberForm"] input').eq(4).clear().type(prefix)
	}
	if (suffix) {
		cy.get('[data-cy="NumberForm"] input').eq(5).clear().type(suffix)
	}
	cy.get('.modal-container button').contains('Save').click()
	cy.wait(10).get('.toastify.toast-success').should('be.visible')
	cy.get('.custom-table table tr th .cell').contains(title).should('exist')
})

Cypress.Commands.add('createNumberProgressColumn', (title, defaultValue, isFirstColumn) => {
	cy.openCreateColumnModal(isFirstColumn)
	cy.get('[data-cy="columnTypeFormInput"]').clear().type(title)
	cy.get('.columnTypeSelection .vs__open-indicator').click({ force: true })
	cy.get('.multiSelectOptionLabel').contains('Progress').click({ force: true })

	if (defaultValue) {
		cy.get('[data-cy="NumberProgressForm"] input').eq(0).clear().type(defaultValue)
	}
	cy.get('.modal-container button').contains('Save').click()
	cy.wait(10).get('.toastify.toast-success').should('be.visible')
	cy.get('.custom-table table tr th .cell').contains(title).should('exist')
})

Cypress.Commands.add('createNumberStarsColumn', (title, defaultValue, isFirstColumn) => {
	cy.openCreateColumnModal(isFirstColumn)
	cy.get('[data-cy="columnTypeFormInput"]').clear().type(title)
	cy.get('.columnTypeSelection .vs__open-indicator').click({ force: true })
	cy.get('.multiSelectOptionLabel').contains('Stars rating').click({ force: true })

	if (defaultValue) {
		for (let n = 0; n < defaultValue; n++) {
			cy.get('[data-cy="NumberStarsForm"] button').last().click()
		}
	}
	cy.get('.modal-container button').contains('Save').click()
	cy.wait(10).get('.toastify.toast-success').should('be.visible')
	cy.get('.custom-table table tr th .cell').contains(title).should('exist')
})

Cypress.Commands.add('createSelectionCheckColumn', (title, defaultValue, isFirstColumn) => {
	cy.openCreateColumnModal(isFirstColumn)
	cy.get('[data-cy="columnTypeFormInput"]').clear().type(title)
	cy.get('.columnTypeSelection .vs__open-indicator').click({ force: true })
	cy.get('.multiSelectOptionLabel').contains('Selection').click({ force: true })
	cy.get('[data-cy="createColumnYesNoSwitch"').contains('Yes/No').click()

	if (defaultValue) {
		cy.get('[data-cy="selectionCheckFormDefaultSwitch"]').click()
	}
	cy.get('.modal-container button').contains('Save').click()
	cy.wait(10).get('.toastify.toast-success').should('be.visible')
	cy.get('.custom-table table tr th .cell').contains(title).should('exist')
})

Cypress.Commands.add('uploadFile', (fileName, mimeType, target) => {
	return cy.fixture(fileName, 'binary')
		.then(Cypress.Blob.binaryStringToBlob)
		.then(blob => {
			if (typeof target !== 'undefined') {
				fileName = target
			}
			cy.request('/csrftoken')
				.then(({ body }) => {
					return cy.wrap(body.token)
				})
				.then(async (requesttoken) => {
					return cy.request({
						url: `${url}/remote.php/webdav/${fileName}`,
						method: 'put',
						body: blob.size > 0 ? blob : '',
						// auth,
						headers: {
							requesttoken,
							'Content-Type': mimeType,
						},
					})
				}).then(response => {
					const fileId = Number(
						response.headers['oc-fileid']?.split('oc')?.[0],
					)
					cy.log(`Uploaded ${fileName}`,
						response.status,
						{ fileId },
					)
					return cy.wrap(fileId)
				})
		})
})

Cypress.Commands.add('ocsRequest', (user, options) => {
	const auth = { user: user.userId, password: user.password }
	return cy.request({
		form: true,
		auth,
		headers: {
			'OCS-ApiRequest': 'true',
			'Content-Type': 'application/x-www-form-urlencoded',
		},
		...options,
	})
})
Cypress.Commands.add('createGroup', (groupName) => {
	cy.ocsRequest({ userId: 'admin', password: 'admin' }, {
		method: 'POST',
		url: `${url}/ocs/v2.php/cloud/groups`,
		body: {
			groupid: groupName,
			displayname: groupName,
		},
	}).then(response => {
		cy.log(`Group ${groupName} created.`, response.status)
	})
})

Cypress.Commands.add('addUserToGroup', (userId, groupId) => {
	cy.ocsRequest({ userId: 'admin', password: 'admin' }, {
		method: 'POST',
		url: `${url}/ocs/v2.php/cloud/users/${userId}/groups`,
		body: {
			groupid: groupId,
		},
	}).then(response => {
		cy.log(`User ${userId} added to group ${groupId}.`, response.status)
	})
})

Cypress.Commands.add('removeColumn', (title) => {
	cy.get('.custom-table table tr th .cell').contains(title).click()
	cy.get('[data-cy="deleteColumnActionBtn"] button').click()
	cy.get('[data-cy="confirmDialog"] button').contains('Confirm').click()
})

// fill in a value in the 'create row' or 'edit row' model
Cypress.Commands.add('fillInValueTextLine', (columnTitle, value) => {
	cy.get('.modal__content [data-cy="' + columnTitle + '"] .slot input').type(value)
})
Cypress.Commands.add('fillInValueSelection', (columnTitle, optionLabel) => {
	cy.get('.modal__content [data-cy="' + columnTitle + '"] .slot input').click()
	cy.get('ul.vs__dropdown-menu li span[title="' + optionLabel + '"]').click()
})
Cypress.Commands.add('fillInValueSelectionMulti', (columnTitle, optionLabels) => {
	optionLabels.forEach(item => {
		cy.get('.modal__content [data-cy="' + columnTitle + '"] .slot input').click()
		cy.get('ul.vs__dropdown-menu li span[title="' + item + '"]').click()
	})
})
Cypress.Commands.add('fillInValueSelectionCheck', (columnTitle) => {
	cy.get('.modal__content [data-cy="' + columnTitle + '"] [data-cy="selectionCheckFormSwitch"]').click()
})
