/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { buildPreviewUrl, findLocalFileId, isFileId } from '../../src/shared/utils/filePreview.js'

describe('Local file preview links', () => {
	const origin = () => window.location.origin

	it('takes the file id from a same origin link, whatever its spelling', () => {
		expect(findLocalFileId(`${origin()}/index.php/f/123`)).to.equal('123')
		expect(findLocalFileId(`${origin()}/index.php/f/123/`)).to.equal('123')
		expect(findLocalFileId('/index.php/f/456')).to.equal('456', 'a relative link resolves against this origin')
		expect(findLocalFileId(`${origin()}/index.php/f/123?dir=/x`)).to.equal('123')
		expect(findLocalFileId(`${origin()}/index.php/f/123#anchor`)).to.equal('123')
	})

	// The card layout anchored the id to the end of the path, the link cell did not.
	it('reads the id from a path that continues after it', () => {
		expect(findLocalFileId(`${origin()}/index.php/f/123/preview`)).to.equal('123')
	})

	// The link cell only matched a lowercase fileid, which is not the spelling the server
	// itself writes, so a preview URL never resolved through it.
	it('matches the file id parameter whatever its case', () => {
		expect(findLocalFileId(`${origin()}/core/preview?fileId=7&x=64`)).to.equal('7')
		expect(findLocalFileId(`${origin()}/core/preview?fileid=7`)).to.equal('7')
		expect(findLocalFileId(`${origin()}/apps/files/?FILEID=8`)).to.equal('8')
		expect(findLocalFileId(`${origin()}/s/token/download?fileId=5`)).to.equal('5')
	})

	// Files deep links carry the id in openfile; only the link cell understood those.
	it('reads the id a Files deep link carries', () => {
		expect(findLocalFileId(`${origin()}/apps/files/files/9?openfile=9`)).to.equal('9')
		expect(findLocalFileId(`${origin()}/apps/files/?dir=/&openfile=42`)).to.equal('42')
	})

	it('refuses anything that does not name a file on this server', () => {
		expect(findLocalFileId('https://evil.example/index.php/f/42')).to.equal(null, 'another host is never followed')
		expect(findLocalFileId('https://evil.example/core/preview?fileId=42')).to.equal(null)
		expect(findLocalFileId('https://evil.example/apps/files/?openfile=42')).to.equal(null)
		expect(findLocalFileId('not a link')).to.equal(null)
		expect(findLocalFileId(`${origin()}/index.php/f/abc`)).to.equal(null, 'the id has to be numeric')
		expect(findLocalFileId('')).to.equal(null)
		expect(findLocalFileId(null)).to.equal(null)
		expect(findLocalFileId(undefined)).to.equal(null)
	})

	it('unescapes the slashes a stored link may carry', () => {
		expect(findLocalFileId(`${origin()}\\/index.php\\/f\\/77`)).to.equal('77')
	})

	it('accepts an id the picker stored outright, and only a usable one', () => {
		expect(isFileId(12)).to.equal(true)
		expect(isFileId('12')).to.equal(true)
		expect(isFileId(0)).to.equal(false)
		expect(isFileId(-1)).to.equal(false)
		expect(isFileId(1.5)).to.equal(false)
		expect(isFileId('abc')).to.equal(false)
		expect(isFileId(null)).to.equal(false)
		expect(isFileId(undefined)).to.equal(false)
	})

	it('rebuilds the request rather than forwarding the stored one', () => {
		const url = buildPreviewUrl('7', 512)

		expect(url).to.match(/\/core\/preview\?fileId=7&x=512&y=512&a=1$/)
		expect(buildPreviewUrl(7, 64)).to.match(/\/core\/preview\?fileId=7&x=64&y=64&a=1$/)
	})
})
