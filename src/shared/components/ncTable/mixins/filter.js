import { ColumnTypes } from './columnHandler.js'
import { translate as t } from '@nextcloud/l10n'

class BaseFilter {

	constructor() {
		this.icon = 'icon-add'
		this.source = 'operators'
		this.subline = t('tables', 'Filter operator')
	}

}

export class Filter extends BaseFilter {

	constructor({ id, label, goodFor, incompatibleWith, shortLabel = null, noSearchValue = false } = {}) {
		super()
		this.id = id
		this.label = label
		this.shortLabel = shortLabel
		this.goodFor = goodFor
		this.incompatibleWith = incompatibleWith
		this.noSearchValue = noSearchValue
	}

	getOperatorLabel() {
		return this.shortLabel ?? this.label
	}

}

export function getFilterWithId(id) {
	return Object.values(Filters).find(fil => fil.id === id)
}

export const FilterIds = {
	Contains: 'contains',
	BeginsWith: 'begins-with',
	EndsWith: 'ends-with',
	IsEqual: 'is-equal',
	IsGreaterThan: 'is-greater-than',
	IsGreaterThanOrEqual: 'is-greater-than-or-equal',
	IsLowerThan: 'is-lower-than',
	IsLowerThanOrEqual: 'is-lower-than-or-equal',
	IsEmpty: 'is-empty',
}

export const Filters = {
	Contains: new Filter({
		id: FilterIds.Contains,
		label: t('tables', 'Contains'),
		goodFor: [ColumnTypes.TextLine, ColumnTypes.TextIPv4Address, ColumnTypes.TextIPv6Address, ColumnTypes.TextLong, ColumnTypes.TextLink, ColumnTypes.TextRich, ColumnTypes.SelectionMulti],
		incompatibleWith: [FilterIds.IsEmpty, FilterIds.IsEqual],
	}),
	BeginsWith: new Filter({
		id: FilterIds.BeginsWith,
		label: t('tables', 'Begins with'),
		goodFor: [ColumnTypes.TextLine, ColumnTypes.TextIPv4Address, ColumnTypes.TextIPv6Address, ColumnTypes.TextLink],
		incompatibleWith: [FilterIds.IsEmpty, FilterIds.IsEqual, FilterIds.BeginsWith],
	}),
	EndsWith: new Filter({
		id: FilterIds.EndsWith,
		label: t('tables', 'Ends with'),
		goodFor: [ColumnTypes.TextLine, ColumnTypes.TextIPv4Address, ColumnTypes.TextIPv6Address, ColumnTypes.TextLink],
		incompatibleWith: [FilterIds.IsEmpty, FilterIds.IsEqual, FilterIds.EndsWith],
	}),
	IsEqual: new Filter({
		id: FilterIds.IsEqual,
		label: t('tables', 'Is equal'),
		shortLabel: '=',
		goodFor: [ColumnTypes.TextLine, ColumnTypes.TextIPv4Address, ColumnTypes.TextIPv6Address, ColumnTypes.Number, ColumnTypes.SelectionCheck, ColumnTypes.TextLink, ColumnTypes.NumberStars, ColumnTypes.NumberProgress, ColumnTypes.DatetimeDate, ColumnTypes.DatetimeTime, ColumnTypes.Datetime, ColumnTypes.Selection, ColumnTypes.SelectionMulti],
		incompatibleWith: [FilterIds.IsEmpty, FilterIds.IsEqual, FilterIds.BeginsWith, FilterIds.EndsWith, FilterIds.Contains, FilterIds.IsGreaterThan, FilterIds.IsGreaterThanOrEqual, FilterIds.IsLowerThan, FilterIds.IsLowerThanOrEqual],
	}),
	IsGreaterThan: new Filter({
		id: FilterIds.IsGreaterThan,
		label: t('tables', 'Is greater than'),
		shortLabel: '>',
		goodFor: [ColumnTypes.Number, ColumnTypes.NumberStars, ColumnTypes.NumberProgress, ColumnTypes.DatetimeDate, ColumnTypes.DatetimeTime, ColumnTypes.Datetime, ColumnTypes.TextIPv4Address, ColumnTypes.TextIPv6Address],
		incompatibleWith: [FilterIds.IsEmpty, FilterIds.IsEqual, FilterIds.IsGreaterThan, FilterIds.IsGreaterThanOrEqual],
	}),
	IsGreaterThanOrEqual: new Filter({
		id: FilterIds.IsGreaterThanOrEqual,
		label: t('tables', 'Is greater than or equal'),
		shortLabel: '>=',
		goodFor: [ColumnTypes.Number, ColumnTypes.NumberStars, ColumnTypes.NumberProgress, ColumnTypes.DatetimeDate, ColumnTypes.DatetimeTime, ColumnTypes.Datetime, ColumnTypes.TextIPv4Address, ColumnTypes.TextIPv6Address],
		incompatibleWith: [FilterIds.IsEmpty, FilterIds.IsEqual, FilterIds.IsGreaterThan, FilterIds.IsGreaterThanOrEqual],
	}),
	IsLowerThan: new Filter({
		id: FilterIds.IsLowerThan,
		label: t('tables', 'Is lower than'),
		shortLabel: '<',
		goodFor: [ColumnTypes.Number, ColumnTypes.NumberStars, ColumnTypes.NumberProgress, ColumnTypes.DatetimeDate, ColumnTypes.DatetimeTime, ColumnTypes.Datetime, ColumnTypes.TextIPv4Address, ColumnTypes.TextIPv6Address],
		incompatibleWith: [FilterIds.IsEmpty, FilterIds.IsEqual, FilterIds.IsLowerThan, FilterIds.IsLowerThanOrEqual],
	}),
	IsLowerThanOrEqual: new Filter({
		id: FilterIds.IsLowerThanOrEqual,
		label: t('tables', 'Is lower than or equal'),
		shortLabel: '<=',
		goodFor: [ColumnTypes.Number, ColumnTypes.NumberStars, ColumnTypes.NumberProgress, ColumnTypes.DatetimeDate, ColumnTypes.DatetimeTime, ColumnTypes.Datetime, ColumnTypes.TextIPv4Address, ColumnTypes.TextIPv6Address],
		incompatibleWith: [FilterIds.IsEmpty, FilterIds.IsEqual, FilterIds.IsLowerThan, FilterIds.IsLowerThanOrEqual],
	}),
	IsEmpty: new Filter({
		id: FilterIds.IsEmpty,
		label: t('tables', 'Is empty'),
		goodFor: [ColumnTypes.TextLine, ColumnTypes.TextIPv4Address, ColumnTypes.TextIPv6Address, ColumnTypes.TextRich, ColumnTypes.Number, ColumnTypes.TextLink, ColumnTypes.NumberProgress, ColumnTypes.DatetimeDate, ColumnTypes.DatetimeTime, ColumnTypes.Datetime, ColumnTypes.SelectionCheck],
		incompatibleWith: [FilterIds.Contains, FilterIds.BeginsWith, FilterIds.EndsWith, FilterIds.IsEqual, FilterIds.IsGreaterThan, FilterIds.IsGreaterThanOrEqual, FilterIds.IsLowerThan, FilterIds.IsLowerThanOrEqual, FilterIds.IsEmpty],
		noSearchValue: true,
	}),
}
