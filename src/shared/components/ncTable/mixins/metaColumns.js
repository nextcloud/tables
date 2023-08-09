import DatetimeColumn from './columnsTypes/datetime.js'
import NumberColumn from './columnsTypes/number.js'
import TextLineColumn from './columnsTypes/textLine.js'
import { translate as t } from '@nextcloud/l10n'

export const MetaColumns = [
	new NumberColumn({ id: -1, title: t('tables', 'ID') }),
	new TextLineColumn({ id: -2, title: t('tables', 'Creator') }),
	new TextLineColumn({ id: -3, title: t('tables', 'Last editor') }),
	new DatetimeColumn({ id: -4, title: t('tables', 'Created at') }),
	new DatetimeColumn({ id: -5, title: t('tables', 'Last edited at') }),
]
