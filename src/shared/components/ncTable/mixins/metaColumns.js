/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import DatetimeColumn from './columnsTypes/datetime.js'
import NumberColumn from './columnsTypes/number.js'
import TextLineColumn from './columnsTypes/textLine.js'
import { translate as t } from '@nextcloud/l10n'
import {
	TYPE_META_CREATED_AT,
	TYPE_META_CREATED_BY,
	TYPE_META_ID,
	TYPE_META_UPDATED_AT,
	TYPE_META_UPDATED_BY,
} from '../../../constants.ts'

export const MetaColumns = [
	new NumberColumn({ id: TYPE_META_ID, title: t('tables', 'ID') }),
	new TextLineColumn({ id: TYPE_META_CREATED_BY, title: t('tables', 'Creator') }),
	new TextLineColumn({ id: TYPE_META_UPDATED_BY, title: t('tables', 'Last editor') }),
	new DatetimeColumn({ id: TYPE_META_CREATED_AT, title: t('tables', 'Created at') }),
	new DatetimeColumn({ id: TYPE_META_UPDATED_AT, title: t('tables', 'Last edited at') }),
]
