/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import TabsEditor from '../../src/modules/grid/TabsEditor.vue'

/**
 * Mounts the editor with v-model: every update becomes its new value and is kept in `@tabs`.
 *
 * @param {Array<{key: string, title: string}>} tabs the tabs to start with
 * @param {object} props other props of the editor
 */
function mountEditor(tabs, props = {}) {
	const current = { tabs }
	let mounted = null
	cy.wrap(current).as('current')
	cy.mount(TabsEditor, {
		props: {
			modelValue: tabs,
			label: 'Tabs',
			required: true,
			...props,
			'onUpdate:modelValue': value => {
				current.tabs = value
				mounted.setProps({ modelValue: value })
			},
		},
	}).then(({ wrapper }) => {
		mounted = wrapper
	})
}

/**
 * @return {Cypress.Chainable} the current tabs
 */
function currentTabs() {
	return cy.get('@current').its('tabs')
}

describe('TabsEditor', () => {
	it('adds, renames, moves and removes tabs and keeps the key of each tab', () => {
		mountEditor([{ key: 'open', title: 'Open' }, { key: 'closed', title: 'Closed' }])

		cy.get('[data-cy="tabsEditorAdd"]').click()
		cy.focused().should('have.attr', 'type', 'text').type('Later')
		currentTabs().then(tabs => {
			expect(tabs.map(tab => tab.title)).to.deep.equal(['Open', 'Closed', 'Later'])
			expect(tabs[2].key).to.match(/^[0-9a-f]{12}$/)
		})

		cy.get('[data-cy="tabsEditorTitle"]').eq(0).clear().type('Doing')
		cy.get('[data-cy="tabsEditorDown"]').eq(0).click()
		currentTabs().then(tabs => {
			expect(tabs.slice(0, 2)).to.deep.equal([{ key: 'closed', title: 'Closed' }, { key: 'open', title: 'Doing' }])
		})

		cy.get('[data-cy="tabsEditorRemove"]').eq(0).click()
		currentTabs().then(tabs => expect(tabs.map(tab => tab.key).slice(0, 1)).to.deep.equal(['open']))
		cy.get('[data-cy="tabsEditorAnnouncement"]').should('have.text', 'Tab Closed removed.')
		cy.focused().should('have.value', 'Doing')
	})

	it('names each button after its tab, announces a move and keeps the focus on a button that still works', () => {
		mountEditor([{ key: 'a', title: 'First' }, { key: 'b', title: 'Second' }, { key: 'c', title: 'Third' }])

		cy.get('[data-cy="tabsEditorTab"]').eq(0).find('label').should('contain.text', 'Title of tab 1')
		cy.get('[data-cy="tabsEditorUp"]').eq(0).should('be.disabled')
		cy.get('[data-cy="tabsEditorDown"]').eq(1).should('have.attr', 'aria-label', 'Move tab Second down').click()
		cy.get('[data-cy="tabsEditorAnnouncement"]').should('have.text', 'Tab Second moved to position 3 of 3.')
		// The tab is last now, so its down button is disabled and the focus goes to its up button
		cy.focused().should('have.attr', 'aria-label', 'Move tab Second up')
		cy.focused().click()
		cy.get('[data-cy="tabsEditorAnnouncement"]').should('have.text', 'Tab Second moved to position 2 of 3.')
		cy.focused().should('have.attr', 'aria-label', 'Move tab Second up')
		currentTabs().then(tabs => expect(tabs.map(tab => tab.key)).to.deep.equal(['a', 'b', 'c']))
	})

	it('marks the list as required without the mark in its name', () => {
		mountEditor([])

		cy.get('legend').should('contain.text', 'Tabs *')
		cy.get('legend [aria-hidden="true"]').should('have.text', ' *')
	})

	it('stops at the most tabs the schema allows', () => {
		mountEditor([{ key: 'a', title: 'First' }], { maxItems: 2 })

		cy.get('[data-cy="tabsEditorHint"]').should('contain.text', '1 of at most 2 tabs')
		cy.get('[data-cy="tabsEditorAdd"]').should('be.enabled').click()
		cy.get('[data-cy="tabsEditorAdd"]').should('be.disabled')
		cy.get('[data-cy="tabsEditorHint"]').should('contain.text', '2 of at most 2 tabs')
	})

	it('marks a title of blanks only, no-break and zero width spaces too, once it was left', () => {
		mountEditor([{ key: 'a', title: '\u00A0\u200B\u3000' }])

		cy.get('[data-cy="tabsEditor"]').should('not.contain.text', 'Give this tab a title.')
		cy.get('[data-cy="tabsEditorTitle"]').focus().blur()
		cy.get('[data-cy="tabsEditor"]').should('contain.text', 'Give this tab a title.')
		cy.get('[data-cy="tabsEditorRemove"]').should('have.attr', 'aria-label', 'Remove tab 1')
	})
})
