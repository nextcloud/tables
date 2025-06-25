/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { downloadFile } from 'cypress-downloadfile/lib/addPlugin'
export default (on, config) => {
  on('task', { downloadFile })
}