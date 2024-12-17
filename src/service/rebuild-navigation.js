/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { emit } from '@nextcloud/event-bus'

export default () => {
	return axios.get(generateOcsUrl('apps/tables/navigation') + '?format=json')
		.then(({ data }) => {
			if (data.ocs.meta.statuscode !== 200) {
				return
			}

			emit('nextcloud:app-menu.refresh', { apps: data.ocs.data })
			window.dispatchEvent(new Event('resize'))
		})
}
