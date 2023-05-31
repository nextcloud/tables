import { ColumnTypes } from './columnHandler.js'

class Filter {

	constructor({ id, label, icon, source, subline, goodFor, shortLabel = null, replace = null } = {}) {
		this.id = id
		this.label = label
		this.shortLabel = shortLabel
		this.icon = icon
		this.source = source
		this.subline = subline
		this.goodFor = goodFor
		this.replace = replace
	}

	getOperatorLabel() {
		return this.shortLabel ?? this.label
	}

}

export function getFilterWithId(id) {
	return Object.values(Filters).find(fil => fil.id === id)
}

export const Filters = {
	Contains: new Filter({
		id: 'contains',
		label: t('tables', 'Contains'),
		icon: 'icon-add',
		source: 'operators',
		subline: t('tables', 'Filter operator'),
		goodFor: [ColumnTypes.TextLine, ColumnTypes.TextLong, ColumnTypes.Selection, ColumnTypes.SelectionMulti, ColumnTypes.TextLink, ColumnTypes.TextRich],
	}),
	BeginsWith: new Filter({
		id: 'begins-with',
		label: t('tables', 'Begins with'),
		icon: 'icon-add',
		source: 'operators',
		subline: t('tables', 'Filter operator'),
		goodFor: [ColumnTypes.TextLine, ColumnTypes.Selection, ColumnTypes.TextLink],
	}),
	EndsWith: new Filter({
		id: 'ends-with',
		label: t('tables', 'Ends with'),
		icon: 'icon-add',
		source: 'operators',
		subline: t('tables', 'Filter operator'),
		goodFor: [ColumnTypes.TextLine, ColumnTypes.Selection, ColumnTypes.TextLink],
	}),
	IsEqual: new Filter({
		id: 'is-equal',
		label: t('tables', 'Is equal'),
		shortLabel: '=',
		icon: 'icon-add',
		source: 'operators',
		subline: t('tables', 'Filter operator'),
		goodFor: [ColumnTypes.TextLine, ColumnTypes.Selection, ColumnTypes.SelectionMulti, ColumnTypes.Number, ColumnTypes.SelectionCheck, ColumnTypes.TextLink, ColumnTypes.NumberStars, ColumnTypes.NumberProgress, ColumnTypes.DatetimeDate, ColumnTypes.DatetimeTime, ColumnTypes.Datetime],
	}),
	IsGreaterThan: new Filter({
		id: 'is-greater-than',
		label: t('tables', 'Is greater than'),
		shortLabel: '>',
		icon: 'icon-add',
		source: 'operators',
		subline: t('tables', 'Filter operator'),
		goodFor: [ColumnTypes.Number, ColumnTypes.NumberStars, ColumnTypes.NumberProgress, ColumnTypes.DatetimeDate, ColumnTypes.DatetimeTime, ColumnTypes.Datetime],
	}),
	IsGreaterThanOrEqual: new Filter({
		id: 'is-greater-than-or-equal',
		label: t('tables', 'Is greater than or equal'),
		shortLabel: '>=',
		icon: 'icon-add',
		source: 'operators',
		subline: t('tables', 'Filter operator'),
		goodFor: [ColumnTypes.Number, ColumnTypes.NumberStars, ColumnTypes.NumberProgress, ColumnTypes.DatetimeDate, ColumnTypes.DatetimeTime, ColumnTypes.Datetime],
	}),
	IsLowerThan: new Filter({
		id: 'is-lower-than',
		label: t('tables', 'Is lower than'),
		shortLabel: '<',
		icon: 'icon-add',
		source: 'operators',
		subline: t('tables', 'Filter operator'),
		goodFor: [ColumnTypes.Number, ColumnTypes.NumberStars, ColumnTypes.NumberProgress, ColumnTypes.DatetimeDate, ColumnTypes.DatetimeTime, ColumnTypes.Datetime],
	}),
	IsLowerThanOrEqual: new Filter({
		id: 'is-lower-than-or-equal',
		label: t('tables', 'Is lower than or equal'),
		shortLabel: '<=',
		icon: 'icon-add',
		source: 'operators',
		subline: t('tables', 'Filter operator'),
		goodFor: [ColumnTypes.Number, ColumnTypes.NumberStars, ColumnTypes.NumberProgress, ColumnTypes.DatetimeDate, ColumnTypes.DatetimeTime, ColumnTypes.Datetime],
	}),
}
