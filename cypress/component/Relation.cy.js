/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createPinia, setActivePinia } from 'pinia'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import { useDataStore } from '../../src/store/data.js'
import RelationColumn from '../../src/shared/components/ncTable/mixins/columnsTypes/relation.js'
import TableRow from '../../src/shared/components/ncTable/partials/TableRow.vue'

for (const relationType of ['table', 'view']) {
	describe(`Relation inline selection (${relationType})`, () => {
		for (const value of [270, '270']) {
			it(`shows the selected label for a ${typeof value} row ID`, () => {
				const pinia = createPinia()
				setActivePinia(pinia)
				const store = useDataStore()
				const column = new RelationColumn({
					id: 7,
					tableId: 1,
					title: 'Approver',
					customSettings: { relationType, targetId: 2, labelColumn: 20 },
				})
				store.relations[7] = {
					column: null,
					values: {
						270: { id: 270, value: 'Alice' },
						271: { id: 271, value: 'Bob' },
					},
					loading: false,
				}
				cy.mount(TableRow, {
					props: {
						row: { id: 1, data: [{ columnId: 7, value }] },
						columns: [column],
						config: { canEditRows: true, canSelectRows: false, showActions: false },
						isView: false,
						elementId: 1,
					},
					global: {
						plugins: [pinia],
						mixins: [{ methods: { t, n } }],
						config: { globalProperties: { OC: window.OC, OCA: window.OCA } },
					},
				})
				cy.contains('.cell-relation .non-edit-mode', 'Alice').click()
				cy.get('.cell-relation .edit-mode .vs__selected').should('contain', 'Alice').and('not.contain', '270')
				cy.get('[aria-label="Select relation value"]').click()
				cy.get('.vs__dropdown-menu').should('contain', 'Alice').and('contain', 'Bob')
			})
		}
	})
}
