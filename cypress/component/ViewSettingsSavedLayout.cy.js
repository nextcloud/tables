/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import ViewSettings from '../../src/modules/modals/ViewSettings.vue'

const VIEW = {
	id: 1,
	tableId: 1,
	title: 'A view',
	layout: 'table',
	columnSettings: [{ columnId: 1, order: 0 }],
	viewSettings: { cardBackgroundSource: null, cardTitleSource: null },
	sort: [],
	filter: [],
}

describe('View settings layout', () => {
	it('offers the layout a viewer switched to when the modified view is saved', () => {
		// Every other local adjustment is merged into "Save modified View", so the layout
		// the viewer is actually looking at has to be the one it offers to save.
		mountDialog({ layout: 'gallery' }).then(({ wrapper }) => {
			cy.wrap(null)
				.then(() => wrapper.vm.reset())
				.then(() => expect(wrapper.vm.layout).to.equal('gallery'))
		})
	})

	it('offers the saved layout while the viewer has not switched', () => {
		mountDialog({ hiddenColumns: [] }).then(({ wrapper }) => {
			cy.wrap(null)
				.then(() => wrapper.vm.reset())
				.then(() => expect(wrapper.vm.layout).to.equal('table'))
		})
	})
})

/**
 * Mounts the view settings dialog for an existing view with a local view setting.
 *
 * @param {object} viewSetting the local adjustments the viewer made
 * @return {Cypress.Chainable} the mount result
 */
function mountDialog(viewSetting) {
	return cy.mount(ViewSettings, {
		props: {
			showModal: false,
			view: VIEW,
			createView: false,
			viewSetting,
		},
	})
}
