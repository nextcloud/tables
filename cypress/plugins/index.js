/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
const { downloadFile } = require('cypress-downloadfile/lib/addPlugin')
module.exports = (on, config) => {
	on('task', { downloadFile })
}
