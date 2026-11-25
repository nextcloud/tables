/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import AddWidget from '../../src/modules/modals/AddWidget.vue'
import { useTablesStore } from '../../src/store/store.js'

const widgetTypes = [
	{
		type: 'header',
		title: 'Header',
		defaultWidth: 12,
		defaultHeight: 2,
		hasSource: false,
		settings: {
			heading: { type: 'string', title: 'Heading', default: '', required: true, hint: 'The large text at the top' },
			textAlign: { type: 'enum', title: 'Text alignment', default: 'start', options: [{ value: 'start', label: 'Start' }, { value: 'center', label: 'Centered' }] },
		},
	},
	{ type: 'data', title: 'Table or view', defaultWidth: 12, defaultHeight: 5, hasSource: true, settings: {} },
	{ type: 'unrendered', title: 'Not shown yet', defaultWidth: 12, defaultHeight: 2, hasSource: false, settings: {} },
]

const nodes = { 4: { id: 4, node_type: 0, node_id: 40, permissions: 1 } }

/**
 * @param {object} props props of the dialog besides showModal
 */
function mountDialog(props = {}) {
	cy.intercept('GET', '**/apps/tables/api/2/widget-types', { ocs: { data: widgetTypes } }).as('widgetTypes')
	cy.mount(AddWidget, { props: { showModal: false, nodes, ...props } }).then(({ wrapper }) => {
		const store = useTablesStore()
		store.widgetTypes = []
		store.tables = [{ id: 40, title: 'Tasks', emoji: '' }]
		cy.wrap(wrapper).as('wrapper')
		cy.wrap(wrapper.setProps({ showModal: true }))
	})
}

describe('AddWidget', () => {
	it('offers only the widget types it can show', () => {
		mountDialog()

		cy.get('[data-cy="addWidgetType"] input').click()
		cy.get('.vs__dropdown-menu li').should('have.length', 2)
		cy.get('.vs__dropdown-menu').should('contain.text', 'Table or view').and('not.contain.text', 'Not shown yet')
	})

	it('names every option by its whole label, so no space shows up in the middle of it', () => {
		mountDialog()

		cy.get('[data-cy="addWidgetType"] input').click()
		cy.get('.vs__dropdown-menu li[role="option"]').eq(1).within(() => {
			cy.get('.name-parts__last').should('not.exist')
			cy.get('[title="Table or view"]').should('have.text', 'Table or view')
		})
		cy.get('.vs__dropdown-menu li[role="option"]').eq(1).click()
		cy.get('[data-cy="addWidgetType"] .vs__selected [title="Table or view"]').should('have.text', 'Table or view')
		cy.get('[data-cy="addWidgetNode"] input').click()
		cy.get('.vs__dropdown-menu li[role="option"] [title="Tasks"]').should('have.text', 'Tasks')
	})

	it('builds the settings form from the server schema and hands the widget to the parent', () => {
		mountDialog()

		cy.get('[data-cy="widgetFieldTextAlign"]').should('contain.text', 'Start')
		cy.get('[data-cy="addWidgetSubmit"]').should('be.disabled')
		cy.get('[data-cy="addWidgetTitle"]').type('Welcome')
		cy.get('[data-cy="addWidgetDescription"]').type('Read this first')
		cy.get('[data-cy="widgetFieldHeading"] input').type('Projects')
		cy.get('[data-cy="addWidgetSubmit"]').should('be.enabled').click()
		cy.get('@wrapper').then(wrapper => {
			expect(wrapper.emitted('submit')[0][0]).to.deep.equal({
				type: 'header',
				title: 'Welcome',
				icon: null,
				description: 'Read this first',
				settings: { heading: 'Projects', textAlign: 'start' },
				nodeRelId: null,
			})
		})
	})

	it('keeps the icon, description and settings of a widget being configured', () => {
		mountDialog({ editingWidget: { key: 'w1', id: 1, type: 'header', title: 'Hi', icon: 'star', description: 'About', settings: { heading: 'Old' }, nodeRelId: null } })

		cy.get('[data-cy="addWidgetDescription"]').should('have.value', 'About')
		cy.get('[data-cy="widgetFieldHeading"] input').should('have.value', 'Old')
		cy.get('[data-cy="iconFieldClear"]').should('exist')
		cy.get('[data-cy="addWidgetSubmit"]').click()
		cy.get('@wrapper').then(wrapper => {
			expect(wrapper.emitted('submit')[0][0]).to.deep.include({ icon: 'star', description: 'About', settings: { heading: 'Old', textAlign: 'start' } })
		})
	})

	it('asks for a table or view of the application for a data widget', () => {
		mountDialog({ editingWidget: { key: 'w2', id: 2, type: 'data', title: '', settings: {}, nodeRelId: null } })

		cy.get('[data-cy="addWidgetNode"]').should('exist')
		cy.get('[data-cy="addWidgetSubmit"]').should('be.disabled')
	})

	it('limits the title and description to what the server accepts', () => {
		mountDialog()

		cy.get('[data-cy="addWidgetTitle"]').should('have.attr', 'maxlength', '200')
		cy.get('[data-cy="addWidgetDescription"]').should('have.attr', 'maxlength', '2000')
	})

	it('marks required fields and says what is still missing, with blanks counting as empty', () => {
		mountDialog()

		cy.get('[data-cy="widgetFieldHeading"] input').should('have.attr', 'required')
		cy.get('[data-cy="widgetFieldHeading"]').should('contain.text', 'Heading *')
		cy.get('[data-cy="widgetFieldHeading"]').should('contain.text', 'The large text at the top')
		cy.get('[data-cy="addWidgetMissing"]').should('contain.text', 'Still needed: Heading')
		cy.get('[data-cy="widgetFieldHeading"] input').type('\u200B\u200B')
		cy.get('[data-cy="addWidgetSubmit"]').should('be.disabled')
		cy.get('[data-cy="addWidgetMissing"]').should('contain.text', 'Heading')
		cy.get('[data-cy="widgetFieldHeading"] input').type('Projects')
		cy.get('[data-cy="addWidgetMissing"]').should('not.exist')
		cy.get('[data-cy="addWidgetSubmit"]').should('be.enabled')
	})

	it('marks the table or view of a data widget as required', () => {
		mountDialog({ editingWidget: { key: 'w2', id: 2, type: 'data', title: '', settings: {}, nodeRelId: null } })

		cy.get('[data-cy="addWidgetNode"]').should('contain.text', 'Table or view *')
		cy.get('[data-cy="addWidgetNode"] input').should('have.attr', 'required')
		cy.get('[data-cy="addWidgetMissing"]').should('contain.text', 'Still needed: Table or view')
	})

	it('says what an empty title does for the type of the widget', () => {
		mountDialog()
		cy.get('[data-cy="addWidgetModal"]').should('contain.text', 'Leave empty to show no title and no icon')

		mountDialog({ editingWidget: { key: 'w2', id: 2, type: 'data', title: '', settings: {}, nodeRelId: 4 } })
		cy.get('[data-cy="addWidgetModal"]').should('contain.text', 'Leave empty to use the title of the table or view').and('not.contain.text', 'no title and no icon')
	})

	it('configures a widget of a type this frontend cannot show yet', () => {
		mountDialog({ editingWidget: { key: 'w3', id: 3, type: 'unrendered', title: 'Later', settings: {}, nodeRelId: null } })

		cy.get('[data-cy="addWidgetType"]').should('contain.text', 'Not shown yet')
		cy.get('[data-cy="addWidgetTitle"]').clear().type('Soon')
		cy.get('[data-cy="addWidgetSubmit"]').should('be.enabled').click()
		cy.get('@wrapper').then(wrapper => {
			expect(wrapper.emitted('submit')[0][0]).to.deep.include({ type: 'unrendered', title: 'Soon' })
		})
	})

	it('shows the form only once the widget types are there', () => {
		cy.intercept('GET', '**/apps/tables/api/2/widget-types', { delay: 800, body: { ocs: { data: widgetTypes } } }).as('slowTypes')
		cy.mount(AddWidget, { props: { showModal: false, nodes } }).then(({ wrapper }) => {
			useTablesStore().widgetTypes = []
			cy.wrap(wrapper.setProps({ showModal: true }))
		})

		cy.get('[data-cy="addWidgetLoading"]').should('exist')
		cy.get('[data-cy="addWidgetTitle"]').should('not.exist')
		cy.get('[data-cy="addWidgetSubmit"]').should('be.disabled')
		cy.wait('@slowTypes')
		cy.get('[data-cy="addWidgetTitle"]').should('exist')
		cy.get('[data-cy="addWidgetLoading"]').should('not.exist')
	})

	it('moves focus to the icon button after removing the icon', () => {
		mountDialog({ editingWidget: { key: 'w1', id: 1, type: 'header', title: 'Hi', icon: 'star', description: '', settings: { heading: 'Old' }, nodeRelId: null } })

		cy.get('[data-cy="iconFieldClear"]').focus()
		cy.focused().type('{enter}')
		cy.get('[data-cy="iconFieldClear"]').should('not.exist')
		cy.focused().should('have.attr', 'data-cy', 'iconFieldPick')
	})
})
