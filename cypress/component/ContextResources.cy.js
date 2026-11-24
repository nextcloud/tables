/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import EditContext from '../../src/modules/modals/EditContext.vue'
import NcContextResource from '../../src/shared/components/ncContextResource/NcContextResource.vue'
import { NODE_TYPE_TABLE, NODE_TYPE_VIEW } from '../../src/shared/constants.ts'
import { useTablesStore } from '../../src/store/store.js'

describe('Resources of an application', () => {
	it('lists a table that is stored twice once, with the permissions of both copies', () => {
		cy.mount(EditContext, { props: { showModal: false } }).then(({ wrapper }) => {
			useTablesStore().tables = [{ id: 5, title: 'Table A' }, { id: 6, title: 'Table B' }]

			const resources = wrapper.vm.getContextResources({
				nodes: {
					1: { id: 1, node_id: 5, node_type: NODE_TYPE_TABLE, permissions: 3 },
					2: { id: 2, node_id: 6, node_type: NODE_TYPE_TABLE, permissions: 1 },
					3: { id: 3, node_id: 5, node_type: NODE_TYPE_TABLE, permissions: 5 },
				},
				pages: {
					1: {
						id: 1,
						page_type: 'startpage',
						content: {
							1: { order: 10, node_rel_id: 1 },
							2: { order: 20, node_rel_id: 2 },
							3: { order: 30, node_rel_id: 3 },
						},
					},
				},
			})

			expect(resources.map(resource => resource.key)).to.deep.equal(['table-5', 'table-6'])
			expect(resources[0]).to.include({
				permissionRead: true,
				permissionCreate: true,
				permissionUpdate: true,
				permissionDelete: false,
			})
		})
	})

	it('adds a resource only once', () => {
		cy.mount(NcContextResource, { shallow: true }).then(({ wrapper }) => {
			const table = { title: 'Table A', key: 'table-5', nodeType: NODE_TYPE_TABLE, id: '5' }
			const viewWithTheSameId = { title: 'View A', key: 'view-5', nodeType: NODE_TYPE_VIEW, id: '5' }
			wrapper.vm.addResource({ ...table })
			wrapper.vm.addResource({ ...table })
			wrapper.vm.addResource(viewWithTheSameId)

			expect(wrapper.vm.localResources.map(listed => listed.key)).to.deep.equal(['table-5', 'view-5'])
		})
	})
})
