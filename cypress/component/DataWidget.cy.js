/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import DataWidget from '../../src/modules/grid/widgets/DataWidget.vue'
import { useTablesStore } from '../../src/store/store.js'
import { useDataStore } from '../../src/store/data.js'

const sortByName = [{ columnId: 1, mode: 'ASC' }]

/**
 * Mounts a data widget for table 40, with the loads it starts answered by the store as it is.
 *
 * @param {object} table the table in the store
 */
function mountWidget(table) {
	cy.mount(DataWidget, { props: { target: null } }).then(({ wrapper }) => {
		const tablesStore = useTablesStore()
		const dataStore = useDataStore()
		tablesStore.tables = [table]
		tablesStore.loadContextTable = cy.stub().as('loadTable').resolves()
		dataStore.loadColumnsFromBE = cy.stub().resolves()
		dataStore.loadRowsFromBE = cy.stub().resolves()
		dataStore.loadRelationsFromBE = cy.stub().resolves()
		cy.wrap(wrapper).as('wrapper')
		return wrapper.setProps({ target: { type: 'table', id: 40 } })
	})
}

describe('DataWidget', () => {
	it('starts a table in its saved sorting and keeps what the viewer did when it loads again', () => {
		mountWidget({ id: 40, title: 'Tasks', sort: sortByName })

		cy.get('@wrapper').then(wrapper => {
			expect(wrapper.vm.viewSetting).to.deep.equal({ presetSorting: sortByName })
			wrapper.vm.viewSetting = { ...wrapper.vm.viewSetting, searchString: 'open' }
			return wrapper.vm.load()
		})
		cy.get('@loadTable').should('have.been.calledTwice')
		cy.get('@wrapper').then(wrapper => {
			expect(wrapper.vm.viewSetting.searchString).to.equal('open')
			useTablesStore().tables = [{ id: 40, title: 'Tasks', sort: [...sortByName] }]
		})
		cy.get('@wrapper').should(wrapper => {
			expect(wrapper.vm.viewSetting).to.deep.equal({ presetSorting: sortByName, searchString: 'open' })
		})
	})

	it('takes over a new saved sorting and keeps the search', () => {
		mountWidget({ id: 40, title: 'Tasks', sort: [] })

		cy.get('@wrapper').then(wrapper => {
			expect(wrapper.vm.viewSetting).to.deep.equal({})
			wrapper.vm.viewSetting = { searchString: 'open' }
			useTablesStore().tables = [{ id: 40, title: 'Tasks', sort: sortByName }]
		})
		cy.get('@wrapper').should(wrapper => {
			expect(wrapper.vm.viewSetting).to.deep.equal({ searchString: 'open', presetSorting: sortByName })
		})
	})
})
