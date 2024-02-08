/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

import type { getTablesResponse } from '../types/index.ts'

export const listTables = async (): Promise<getTablesResponse> => {
	return axios.get(generateUrl('/apps/tables/table'))
}
