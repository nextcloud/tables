/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import ViewSettings from '../../src/modules/modals/ViewSettings.vue'

const COLUMNS = [
	{ id: 1, title: 'Name' },
	{ id: 2, title: 'Image' },
]

describe('View settings card sources', () => {
	it('clears a card source when its column leaves the view', () => {
		mountDialog().then(({ wrapper }) => {
			cy.wrap(null)
				.then(() => wrapper.setData({
					columns: COLUMNS,
					selectedColumns: [1, 2],
					mutableView: { viewSettings: { cardBackgroundSource: 2, cardTitleSource: 1 } },
				}))
				// Deselecting the image column must clear the source, not merely hide it from the
				// select: a stale id is still saved and the backend rejects the whole update.
				.then(() => wrapper.setData({ selectedColumns: [1] }))
				.then(() => {
					expect(wrapper.vm.mutableView.viewSettings.cardBackgroundSource).to.equal(null)
					expect(wrapper.vm.mutableView.viewSettings.cardTitleSource).to.equal(1)
				})
		})
	})

	it('clears a source whose column is only hidden locally when the modified view is saved', () => {
		// "Save modified View" opens the dialog with the local setting: the hidden column is
		// stripped from the column selection, so a source pointing at it must go as well.
		cy.mount(ViewSettings, {
			props: {
				showModal: false,
				view: {
					id: 1,
					tableId: 1,
					title: 'A view',
					columnSettings: [{ columnId: 1, order: 0 }, { columnId: 2, order: 1 }],
					viewSettings: { cardBackgroundSource: 2, cardTitleSource: 1 },
					sort: [],
					filter: [],
				},
				createView: false,
				viewSetting: { hiddenColumns: [2] },
			},
		}).then(({ wrapper }) => {
			cy.wrap(null)
				.then(() => wrapper.vm.reset())
				.then(() => wrapper.setData({ columns: COLUMNS }))
				.then(() => {
					expect(wrapper.vm.selectedColumns).to.deep.equal([1])
					expect(wrapper.vm.mutableView.viewSettings.cardBackgroundSource).to.equal(null)
					expect(wrapper.vm.mutableView.viewSettings.cardTitleSource).to.equal(1)
				})
		})
	})

	it('keeps a source whose column is still selected', () => {
		mountDialog().then(({ wrapper }) => {
			cy.wrap(null)
				.then(() => wrapper.setData({
					columns: COLUMNS,
					selectedColumns: [1, 2],
					mutableView: { viewSettings: { cardBackgroundSource: 2, cardTitleSource: 1 } },
				}))
				.then(() => wrapper.setData({ selectedColumns: [2, 1] }))
				.then(() => {
					expect(wrapper.vm.mutableView.viewSettings.cardBackgroundSource).to.equal(2)
				})
		})
	})
})

/**
 * Mounts the view settings dialog closed, so only its data and watchers are exercised.
 *
 * @return {Cypress.Chainable} the mount result
 */
function mountDialog() {
	return cy.mount(ViewSettings, {
		props: {
			showModal: false,
			view: { id: 1, tableId: 1, title: 'A view', columnSettings: [], sort: [], filter: [] },
			createView: false,
			viewSetting: null,
		},
	})
}
