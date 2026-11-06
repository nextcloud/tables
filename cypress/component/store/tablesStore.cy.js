/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createPinia, setActivePinia } from 'pinia'
import { useTablesStore } from '../../../src/store/store.js'

const hasStrayIndex = collection => Object.hasOwn(collection, '-1')

describe('tables store setters', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
	})

	it('setTable replaces in place and leaves an unlisted table out', () => {
		const store = useTablesStore()
		store.setTables([{ id: 1, title: 'A' }, { id: 3, title: 'C' }])

		store.setTable({ id: 3, title: 'C renamed' })
		expect(store.tables.map(table => table.title)).to.deep.equal(['A', 'C renamed'])

		store.setTable({ id: 2, title: 'removed in the meantime' })
		expect(store.tables.map(table => table.id)).to.deep.equal([1, 3])
		expect(hasStrayIndex(store.tables)).to.equal(false)
	})

	it('setView replaces in place and leaves an unlisted view out', () => {
		const store = useTablesStore()
		store.setViews([{ id: 1, title: 'A' }, { id: 3, title: 'C' }])

		store.setView({ id: 3, title: 'C renamed' })
		expect(store.views.map(view => view.title)).to.deep.equal(['A', 'C renamed'])

		store.setView({ id: 2, title: 'removed in the meantime' })
		expect(store.views.map(view => view.id)).to.deep.equal([1, 3])
		expect(hasStrayIndex(store.views)).to.equal(false)
	})

	it('setContext appends an unlisted application so a load by URL stays visible', () => {
		const store = useTablesStore()
		store.setContexts([{ id: 1, name: 'A' }])

		store.setContext({ id: 2, name: 'B' })
		expect(store.contexts.map(context => context.id)).to.deep.equal([1, 2])
		expect(hasStrayIndex(store.contexts)).to.equal(false)
	})
})
