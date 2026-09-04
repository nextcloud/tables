/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import ContentReferenceWidget from '../../src/views/ContentReferenceWidget.vue'

describe('ContentReferenceWidget - relation', () => {
	it('renders relation cell labels in an embedded table', () => {
		const richObject = {
			id: 42,
			type: 0,
			title: 'Relation target',
			emoji: '',
			ownership: 'admin',
			ownerDisplayName: 'admin',
			isShared: null,
			onSharePermissions: null,
			rowsCount: 1,
			link: 'http://nextcloud.local/index.php/apps/tables/#/table/42/content',
			columns: [
				{
					id: 1,
					tableId: 42,
					title: 'Name',
					createdBy: 'admin',
					createdByDisplayName: 'admin',
					createdAt: '2024-01-01 00:00:00',
					lastEditBy: 'admin',
					lastEditByDisplayName: 'admin',
					lastEditAt: '2024-01-01 00:00:00',
					type: 'text',
					subtype: 'line',
					mandatory: false,
					description: '',
					numberDefault: null,
					numberMin: null,
					numberMax: null,
					numberDecimals: 0,
					numberPrefix: '',
					numberSuffix: '',
					textDefault: '',
					textAllowedPattern: '',
					textMaxLength: -1,
					textUnique: false,
					selectionOptions: [],
					selectionDefault: '',
					datetimeDefault: '',
				},
				{
					id: 2,
					tableId: 42,
					title: 'Refers to',
					createdBy: 'admin',
					createdByDisplayName: 'admin',
					createdAt: '2024-01-01 00:00:00',
					lastEditBy: 'admin',
					lastEditByDisplayName: 'admin',
					lastEditAt: '2024-01-01 00:00:00',
					type: 'relation',
					subtype: '',
					mandatory: false,
					description: '',
					numberDefault: null,
					numberMin: null,
					numberMax: null,
					numberDecimals: 0,
					numberPrefix: '',
					numberSuffix: '',
					textDefault: '',
					textAllowedPattern: '',
					textMaxLength: -1,
					textUnique: false,
					selectionOptions: [],
					selectionDefault: '',
					datetimeDefault: '',
					customSettings: {
						relationType: 'table',
						targetId: 1,
						labelColumn: 1,
					},
				},
			],
			rows: [
				{
					id: 101,
					tableId: 42,
					createdBy: 'admin',
					createdAt: '2024-01-01 00:00:00',
					lastEditBy: 'admin',
					lastEditAt: '2024-01-01 00:00:00',
					data: [
						{ columnId: 1, value: 'Target row' },
						{ columnId: 2, value: 100 },
					],
				},
			],
		}

		// Mock relation data so the relation cell can resolve its label
		cy.reply('**/apps/tables/api/1/tables/42/relations', {
			2: {
				100: { id: '100', label: 'Alice' },
			},
		})

		cy.mount(ContentReferenceWidget, {
			props: {
				richObject,
			},
		})

		cy.get('[data-cy="contentReferenceWidget"] table').should('contain', 'Alice')
	})
})
