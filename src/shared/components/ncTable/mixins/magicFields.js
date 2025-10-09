/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { getCurrentUser } from '@nextcloud/auth'
import Moment from '@nextcloud/moment'
import { ColumnTypes } from './columnHandler.js'
import { translate as t } from '@nextcloud/l10n'

class BaseMagicField {

	constructor() {
		this.source = 'magic-fields'
		this.subline = t('tables', 'Magic field')
	}

}

class MagicField extends BaseMagicField {

	constructor({ id, label, icon, goodFor, replace, additionalInput, additionalInputLabel } = {}) {
		super()
		this.id = id
		this.label = label
		this.icon = icon
		this.goodFor = goodFor
		this.replace = replace
		this.additionalInput = additionalInput
		this.additionalInputLabel = additionalInputLabel
	}

}

export function getMagicFieldWithId(id) {
	return Object.values(MagicFields).find(mf => mf.id === id)
}

export const AdditionalInputTypes = {
	DATE: 'date',
	NUMBER: 'number',
}

export const MagicFields = {
	Me: new MagicField({
		id: 'me',
		label: t('tables', 'Me (user ID)'),
		icon: 'icon-user',
		goodFor: [ColumnTypes.TextLine, ColumnTypes.Selection, ColumnTypes.SelectionMulti, ColumnTypes.TextRich, ColumnTypes.TextLink, ColumnTypes.Usergroup],
		replace: getCurrentUser()?.uid,
	}),
	MyName: new MagicField({
		id: 'my-name',
		label: t('tables', 'Me (name)'),
		icon: 'icon-user',
		goodFor: [ColumnTypes.TextLine, ColumnTypes.Selection, ColumnTypes.SelectionMulti, ColumnTypes.TextRich, ColumnTypes.TextLink, ColumnTypes.Usergroup],
		replace: getCurrentUser()?.displayName,
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
		goodFor: [ColumnTypes.DatetimeDate, ColumnTypes.Datetime],
		replace: new Moment().format('YYYY-MM-DD'),
	}),
	DatetimeDateStartOfYear: new MagicField({
		id: 'datetime-date-start-of-year',
		label: t('tables', 'This year'),
		icon: 'icon-history',
		goodFor: [ColumnTypes.DatetimeDate],
		replace: new Moment().startOf('year').format('YYYY-MM-DD'),
	}),
	DatetimeDateStartOfMonth: new MagicField({
		id: 'datetime-date-start-of-month',
		label: t('tables', 'This month'),
		icon: 'icon-history',
		goodFor: [ColumnTypes.DatetimeDate],
		replace: new Moment().startOf('month').format('YYYY-MM-DD'),
	}),
	DatetimeDateStartOfWeek: new MagicField({
		id: 'datetime-date-start-of-week',
		label: t('tables', 'This week'),
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
	DatetimeExactDate: new MagicField({
		id: 'datetime-exact-date',
		label: t('tables', 'Exact date'),
		icon: 'icon-calendar-dark',
		goodFor: [ColumnTypes.DatetimeDate, ColumnTypes.Datetime],
		replace: null,
		additionalInput: AdditionalInputTypes.DATE,
		additionalInputLabel: t('tables', 'Select a date'),
	}),
	DatetimeDaysAhead: new MagicField({
		id: 'datetime-days-ahead',
		label: t('tables', 'Number of days ahead'),
		icon: 'icon-calendar-dark',
		goodFor: [ColumnTypes.DatetimeDate, ColumnTypes.Datetime],
		replace: null,
		additionalInput: AdditionalInputTypes.NUMBER,
		additionalInputLabel: t('tables', 'Enter number of days'),
	}),
	DatetimeDaysAgo: new MagicField({
		id: 'datetime-days-ago',
		label: t('tables', 'Number of days ago'),
		icon: 'icon-calendar-dark',
		goodFor: [ColumnTypes.DatetimeDate, ColumnTypes.Datetime],
		replace: null,
		additionalInput: AdditionalInputTypes.NUMBER,
		additionalInputLabel: t('tables', 'Enter number of days'),
	}),
}
