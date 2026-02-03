/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
// ***********************************************************
// This example support/e2e.js is processed and
// loaded automatically before your test files.
//
// This is a great place to put global configuration and
// behavior that modifies Cypress.
//
// You can change the location of this file or turn off
// automatically serving support files with the
// 'supportFile' configuration option.
//
// You can read more here:
// https://on.cypress.io/configuration
// ***********************************************************

import './commands.js'

Cypress.on('uncaught:exception', (err) => {
	return !err.message.includes('ResizeObserver loop limit exceeded') &&
		!err.message.includes('ResizeObserver loop completed with undelivered notifications') &&
		!err.message.includes("Cannot read properties of undefined (reading 'from')") &&
		!err.message.includes("Cannot read properties of undefined (reading 'createEditor')")
})

// Handle unsupported browser dialog that appears on page load
Cypress.Commands.overwrite('visit', (originalVisit, url, options) => {
	return originalVisit(url, options).then(() => {
		// Wait a moment for the dialog to appear if needed
		cy.get('body', { timeout: 1000 }).then(($body) => {
			const button = $body.find('button:contains("Continue with this unsupported browser")')
			if (button.length > 0) {
				cy.contains('button', 'Continue with this unsupported browser').click()
			}
		})
	})
})