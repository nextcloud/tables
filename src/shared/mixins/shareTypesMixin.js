/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getCapabilities } from '@nextcloud/capabilities'

const capabilities = getCapabilities()

export default {
	data() {
		return {
			SHARE_TYPES: {
				SHARE_TYPE_USER: OC.Share.SHARE_TYPE_USER,
				SHARE_TYPE_GROUP: OC.Share.SHARE_TYPE_GROUP,
				SHARE_TYPE_LINK: OC.Share.SHARE_TYPE_LINK,
				SHARE_TYPE_EMAIL: OC.Share.SHARE_TYPE_EMAIL,
				SHARE_TYPE_REMOTE: OC.Share.SHARE_TYPE_REMOTE,
				SHARE_TYPE_CIRCLE: OC.Share.SHARE_TYPE_CIRCLE,
				SHARE_TYPE_GUEST: OC.Share.SHARE_TYPE_GUEST,
				SHARE_TYPE_REMOTE_GROUP: OC.Share.SHARE_TYPE_REMOTE_GROUP,
				SHARE_TYPE_ROOM: OC.Share.SHARE_TYPE_ROOM,
			},
		}
	},
	computed: {
		isCirclesEnabled() {
			return capabilities && capabilities.tables && capabilities.tables.isCirclesEnabled
		},
	},
}
