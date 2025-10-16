/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getCapabilities } from '@nextcloud/capabilities'

const capabilities = getCapabilities()

export default {
	computed: {
		isActivityEnabled() {
			return capabilities && capabilities.activity
		},
	},
}
