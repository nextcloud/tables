/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import TableCellLink from '../../src/shared/components/ncTable/partials/TableCellLink.vue'

const PREVIEW = 'img.cell-link__preview'

describe('Link cell preview', () => {
	it('resolves the file id of a canonical file link, and renders it', () => {
		mountCell(filesValue(`${window.location.origin}/index.php/f/123`)).then(({ wrapper }) => {
			expect(wrapper.vm.fileId).to.equal('123')
			expect(wrapper.vm.imagePreviewSrc).to.match(/fileId=123&x=64&y=64&a=1$/)
		})

		cy.get(PREVIEW).should('exist')
	})

	// The matcher used to anchor the id to the end of the path.
	it('resolves the file id when the path continues after it', () => {
		expectFileId(`${window.location.origin}/index.php/f/123/preview`, '123')
	})

	// The matcher used to look for a lowercase fileid only, which is not the spelling the
	// server writes, so a preview URL never resolved through it.
	it('resolves the file id parameter the server itself writes', () => {
		expectFileId(`${window.location.origin}/index.php/core/preview?fileId=7`, '7')
	})

	it('resolves the file id a Files deep link carries', () => {
		expectFileId(`${window.location.origin}/index.php/apps/files/files/9?openfile=9`, '9')
	})

	it('prefers the id the picker stored over the link it also carries', () => {
		mountCell(filesValue(`${window.location.origin}/index.php/f/123`, { attributes: { fileId: 456 } }))
			.then(({ wrapper }) => expect(wrapper.vm.fileId).to.equal('456'))
	})

	// An instance reachable under several trusted domains stores the link with whichever
	// host the row was filled in on, so a viewer on another one sees a different origin.
	// The id the picker recorded still resolves, which is what keeps that case working.
	it('still resolves a picked file whose link names another trusted domain', () => {
		mountCell(filesValue('https://other-domain.example/index.php/f/123', { attributes: { fileId: 3979 } }))
			.then(({ wrapper }) => {
				expect(wrapper.vm.fileId).to.equal('3979')
				expect(wrapper.vm.imagePreviewSrc).to.match(/fileId=3979&/)
			})
	})

	// A link to another server used to yield a preview of the local file with that id.
	// Asserted on the computed rather than the markup: a preview that merely fails to load
	// also removes its image, so an empty cell would prove nothing on its own.
	it('resolves no file id for a link to another server', () => {
		expectFileId('https://evil.example/index.php/f/42', null)
	})

	it('resolves no file id for a link that is not a file reference', () => {
		mountCell(JSON.stringify({
			providerId: 'url',
			title: 'Just a link',
			resourceUrl: `${window.location.origin}/index.php/f/123`,
		})).then(({ wrapper }) => {
			expect(wrapper.vm.fileId).to.equal(null)
			expect(wrapper.vm.imagePreviewSrc).to.equal(null)
		})
	})

	it('builds no preview while the column does not ask for one', () => {
		mountCell(filesValue(`${window.location.origin}/index.php/f/123`), { showPreview: false })
			.then(({ wrapper }) => {
				expect(wrapper.vm.fileId).to.equal('123', 'the id still resolves')
				expect(wrapper.vm.imagePreviewSrc).to.equal(null, 'but nothing is requested')
			})
	})
})

/**
 * Mounts a link cell and asserts the file id it resolves.
 *
 * @param {string} url the link the files reference carries
 * @param {string|null} expected the id that must come out of it
 */
function expectFileId(url, expected) {
	mountCell(filesValue(url)).then(({ wrapper }) => expect(wrapper.vm.fileId).to.equal(expected))
}

/**
 * A files reference carrying the given link.
 *
 * @param {string} url the link
 * @param {object} extra further fields of the stored value
 * @return {string} the raw cell value
 */
function filesValue(url, extra = {}) {
	return JSON.stringify({
		providerId: 'files',
		title: 'A picked file',
		resourceUrl: url,
		value: url,
		...extra,
	})
}

/**
 * Mounts a link cell holding the given value.
 *
 * @param {string} value the raw cell value
 * @param {object} customSettings the column's link settings
 * @return {Cypress.Chainable} the mount result
 */
function mountCell(value, customSettings = { showPreview: true, imagePreviewSize: 64 }) {
	return cy.mount(TableCellLink, {
		props: {
			column: { id: 1, title: 'Link', customSettings },
			rowId: 1,
			value,
		},
	})
}
