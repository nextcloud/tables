import { getCurrentUser } from '@nextcloud/auth'
import Moment from '@nextcloud/moment'
import { ColumnTypes } from './columnHandler.js'

class BaseMagicField {

	constructor() {
		this.source = 'magic-fields'
		this.subline = t('tables', 'Magic field')
	}

}

class MagicField extends BaseMagicField {

	constructor({ id, label, icon, goodFor, replace } = {}) {
		super()
		this.id = id
		this.label = label
		this.icon = icon
		this.goodFor = goodFor
		this.replace = replace
	}

}

export const MagicFields = {
	Me: new MagicField({
		id: 'me',
		label: t('tables', 'Me (user ID)'),
		icon: 'icon-user',
		goodFor: [ColumnTypes.TextLine, ColumnTypes.Selection, ColumnTypes.SelectionMulti, ColumnTypes.TextRich],
		replace: getCurrentUser().uid,
	}),
	MyName: new MagicField({
		id: 'my-name',
		label: t('tables', 'Me (name)'),
		icon: 'icon-user',
		goodFor: [ColumnTypes.TextLine, ColumnTypes.Selection, ColumnTypes.SelectionMulti, ColumnTypes.TextRich],
		replace: getCurrentUser().displayName,
	}),
	Checked: new MagicField({
		id: 'checked',
		label: t('tables', 'Checked'),
		icon: 'icon-checkmark',
		goodFor: [ColumnTypes.SelectionCheck],
		replace: 'yes',
	}),
	Unchecked: new MagicField({
		id: 'unchecked',
		label: t('tables', 'Unchecked'),
		icon: 'icon-close',
		goodFor: [ColumnTypes.SelectionCheck],
		replace: 'no',
	}),
	Stars0: new MagicField({
		id: 'stars-0',
		label: '☆☆☆☆☆',
		icon: 'icon-star',
		goodFor: [ColumnTypes.NumberStars],
		replace: '0',
	}),
	Stars1: new MagicField({
		id: 'stars-1',
		label: '★☆☆☆☆',
		icon: 'icon-star',
		goodFor: [ColumnTypes.NumberStars],
		replace: '1',
	}),
	Stars2: new MagicField({
		id: 'stars-2',
		label: '★★☆☆☆',
		icon: 'icon-star',
		goodFor: [ColumnTypes.NumberStars],
		replace: '2',
	}),
	Stars3: new MagicField({
		id: 'stars-3',
		label: '★★★☆☆',
		icon: 'icon-star',
		goodFor: [ColumnTypes.NumberStars],
		replace: '3',
	}),
	Stars4: new MagicField({
		id: 'stars-4',
		label: '★★★★☆',
		icon: 'icon-star',
		goodFor: [ColumnTypes.NumberStars],
		replace: '4',
	}),
	Stars5: new MagicField({
		id: 'stars-5',
		label: '★★★★★',
		icon: 'icon-star',
		goodFor: [ColumnTypes.NumberStars],
		replace: '5',
	}),
	DatetimeDateToday: new MagicField({
		id: 'datetime-date-today',
		label: t('tables', 'Today'),
		icon: 'icon-calendar-dark',
		goodFor: [ColumnTypes.DatetimeDate],
		replace: new Moment().format('YYYY-MM-DD'),
	}),
	DatetimeDateStartOfYear: new MagicField({
		id: 'datetime-date-start-of-year',
		label: t('tables', 'Start of the year'),
		icon: 'icon-history',
		goodFor: [ColumnTypes.DatetimeDate],
		replace: new Moment().startOf('year').format('YYYY-MM-DD'),
	}),
	DatetimeDateStartOfMonth: new MagicField({
		id: 'datetime-date-start-of-month',
		label: t('tables', 'Start of the month'),
		icon: 'icon-history',
		goodFor: [ColumnTypes.DatetimeDate],
		replace: new Moment().startOf('month').format('YYYY-MM-DD'),
	}),
	DatetimeDateStartOfWeek: new MagicField({
		id: 'datetime-date-start-of-week',
		label: t('tables', 'Start of the week'),
		icon: 'icon-history',
		goodFor: [ColumnTypes.DatetimeDate],
		replace: new Moment().startOf('week').format('YYYY-MM-DD'),
	}),
	DatetimeTimeNow: new MagicField({
		id: 'datetime-time-now',
		label: t('tables', 'Now'),
		icon: 'icon-calendar-dark',
		goodFor: [ColumnTypes.DatetimeTime],
		replace: new Moment().format('HH:mm'),
	}),
	DatetimeNow: new MagicField({
		id: 'datetime-now',
		label: t('tables', 'Now'),
		icon: 'icon-calendar-dark',
		goodFor: [ColumnTypes.Datetime],
		replace: new Moment().format('YYYY-MM-DD HH:mm'),
	}),
}
