import { getCurrentUser } from '@nextcloud/auth'
import Moment from '@nextcloud/moment'

export default {

	data() {
		return {
			operators: {
				'operator-contains': {
					id: 'operator-contains',
					label: t('tables', 'Contains'),
					icon: 'icon-add',
					source: 'operators',
					subline: t('tables', 'Filter operator'),
					goodFor: ['text-line', 'text-long', 'selection', 'selection-multi', 'text-link', 'text-rich'],
					incompatibleWith: ['operator-is-empty', 'operator-is-equal'],
				},
				'operator-begins-with': {
					id: 'operator-begins-with',
					label: t('tables', 'Begins with'),
					icon: 'icon-add',
					source: 'operators',
					subline: t('tables', 'Filter operator'),
					goodFor: ['text-line', 'selection', 'text-link'],
					incompatibleWith: ['operator-is-empty', 'operator-is-equal', 'operator-begins-with'],
				},
				'operator-ends-with': {
					id: 'operator-ends-with',
					label: t('tables', 'Ends with'),
					icon: 'icon-add',
					source: 'operators',
					subline: t('tables', 'Filter operator'),
					goodFor: ['text-line', 'selection', 'text-link'],
					incompatibleWith: ['operator-is-empty', 'operator-is-equal', 'operator-ends-with'],
				},
				'operator-is-equal': {
					id: 'operator-is-equal',
					label: t('tables', 'Is equal'),
					shortLabel: '=',
					icon: 'icon-add',
					source: 'operators',
					subline: t('tables', 'Filter operator'),
					goodFor: ['text-line', 'selection', 'selection-multi', 'number', 'selection-check', 'text-link', 'number-stars', 'number-progress', 'datetime-date', 'datetime-time', 'datetime'],
					incompatibleWith: ['operator-is-empty', 'operator-is-equal', 'operator-begins-with', 'operator-ends-with', 'operator-contains', 'operator-is-greater-than', 'operator-is-greater-than-or-equal', 'operator-is-lower-than', 'operator-is-lower-than-or-equal'],
				},
				'operator-is-greater-than': {
					id: 'operator-is-greater-than',
					label: t('tables', 'Is greater than'),
					shortLabel: '>',
					icon: 'icon-add',
					source: 'operators',
					subline: t('tables', 'Filter operator'),
					goodFor: ['number', 'number-stars', 'number-progress', 'datetime-date', 'datetime-time', 'datetime'],
					incompatibleWith: ['operator-is-empty', 'operator-is-equal', 'operator-is-greater-than', 'operator-is-greater-than-or-equal'],
				},
				'operator-is-greater-than-or-equal': {
					id: 'operator-is-greater-than-or-equal',
					label: t('tables', 'Is greater than or equal'),
					shortLabel: '>=',
					icon: 'icon-add',
					source: 'operators',
					subline: t('tables', 'Filter operator'),
					goodFor: ['number', 'number-stars', 'number-progress', 'datetime-date', 'datetime-time', 'datetime'],
					incompatibleWith: ['operator-is-empty', 'operator-is-equal', 'operator-is-greater-than', 'operator-is-greater-than-or-equal'],
				},
				'operator-is-lower-than': {
					id: 'operator-is-lower-than',
					label: t('tables', 'Is lower than'),
					shortLabel: '<',
					icon: 'icon-add',
					source: 'operators',
					subline: t('tables', 'Filter operator'),
					goodFor: ['number', 'number-stars', 'number-progress', 'datetime-date', 'datetime-time', 'datetime'],
					incompatibleWith: ['operator-is-empty', 'operator-is-equal', 'operator-is-lower-than', 'operator-is-lower-than-or-equal'],
				},
				'operator-is-lower-than-or-equal': {
					id: 'operator-is-lower-than-or-equal',
					label: t('tables', 'Is lower than or equal'),
					shortLabel: '<=',
					icon: 'icon-add',
					source: 'operators',
					subline: t('tables', 'Filter operator'),
					goodFor: ['number', 'number-stars', 'number-progress', 'datetime-date', 'datetime-time', 'datetime'],
					incompatibleWith: ['operator-is-empty', 'operator-is-equal', 'operator-is-lower-than', 'operator-is-lower-than-or-equal'],
				},
				'operator-is-empty': {
					id: 'operator-is-empty',
					label: t('tables', 'Is empty'),
					icon: 'icon-add',
					source: 'operators',
					subline: t('tables', 'Filter operator'),
					goodFor: ['text-line', 'text-rich', 'selection', 'selection-multi', 'number', 'text-link', 'number-progress', 'datetime-date', 'datetime-time', 'datetime'],
					incompatibleWith: ['operator-contains', 'operator-begins-with', 'operator-ends-with', 'operator-is-equal', 'operator-is-greater-than', 'operator-is-greater-than-or-equal', 'operator-is-lower-than', 'operator-is-lower-than-or-equal', 'operator-is-empty'],
				},
			},
			magicFields: {
				'magic-field-me': {
					id: 'magic-field-me',
					label: t('tables', 'Me (user ID)'),
					icon: 'icon-user',
					source: 'magic-fields',
					subline: t('tables', 'Magic field'),
					goodFor: ['text-line', 'selection', 'selection-multi', 'text-rich'],
					replace: getCurrentUser().uid,
				},
				'magic-field-my-name': {
					id: 'magic-field-my-name',
					label: t('tables', 'Me (name)'),
					icon: 'icon-user',
					source: 'magic-fields',
					subline: t('tables', 'Magic field'),
					goodFor: ['text-line', 'selection', 'selection-multi', 'text-rich'],
					replace: getCurrentUser().displayName,
				},
				'magic-field-checked': {
					id: 'magic-field-checked',
					label: t('tables', 'Checked'),
					icon: 'icon-checkmark',
					source: 'magic-fields',
					subline: t('tables', 'Magic field'),
					goodFor: ['selection-check'],
					replace: 'yes',
				},
				'magic-field-unchecked': {
					id: 'magic-field-unchecked',
					label: t('tables', 'Unchecked'),
					icon: 'icon-close',
					source: 'magic-fields',
					subline: t('tables', 'Magic field'),
					goodFor: ['selection-check'],
					replace: 'no',
				},
				'magic-field-stars-0': {
					id: 'magic-field-stars-0',
					label: '☆☆☆☆☆',
					icon: 'icon-star',
					source: 'magic-fields',
					subline: t('tables', 'Magic field'),
					goodFor: ['number-stars'],
					replace: '0',
				},
				'magic-field-stars-1': {
					id: 'magic-field-stars-1',
					label: '★☆☆☆☆',
					icon: 'icon-star',
					source: 'magic-fields',
					subline: t('tables', 'Magic field'),
					goodFor: ['number-stars'],
					replace: '1',
				},
				'magic-field-stars-2': {
					id: 'magic-field-stars-2',
					label: '★★☆☆☆',
					icon: 'icon-star',
					source: 'magic-fields',
					subline: t('tables', 'Magic field'),
					goodFor: ['number-stars'],
					replace: '2',
				},
				'magic-field-stars-3': {
					id: 'magic-field-stars-3',
					label: '★★★☆☆',
					icon: 'icon-star',
					source: 'magic-fields',
					subline: t('tables', 'Magic field'),
					goodFor: ['number-stars'],
					replace: '3',
				},
				'magic-field-stars-4': {
					id: 'magic-field-stars-4',
					label: '★★★★☆',
					icon: 'icon-star',
					source: 'magic-fields',
					subline: t('tables', 'Magic field'),
					goodFor: ['number-stars'],
					replace: '4',
				},
				'magic-field-stars-5': {
					id: 'magic-field-stars-5',
					label: '★★★★★',
					icon: 'icon-star',
					source: 'magic-fields',
					subline: t('tables', 'Magic field'),
					goodFor: ['number-stars'],
					replace: '5',
				},
				'magic-field-datetime-date-today': {
					id: 'magic-field-datetime-date-today',
					label: t('tables', 'Today'),
					icon: 'icon-calendar-dark',
					source: 'magic-fields',
					subline: t('tables', 'Magic field'),
					goodFor: ['datetime-date'],
					replace: new Moment().format('YYYY-MM-DD'),
				},
				'magic-field-datetime-date-start-of-year': {
					id: 'magic-field-datetime-date-start-of-year',
					label: t('tables', 'Start of the year'),
					icon: 'icon-history',
					source: 'magic-fields',
					subline: t('tables', 'Magic field'),
					goodFor: ['datetime-date'],
					replace: new Moment().startOf('year').format('YYYY-MM-DD'),
				},
				'magic-field-datetime-date-start-of-month': {
					id: 'magic-field-datetime-date-start-of-month',
					label: t('tables', 'Start of the month'),
					icon: 'icon-history',
					source: 'magic-fields',
					subline: t('tables', 'Magic field'),
					goodFor: ['datetime-date'],
					replace: new Moment().startOf('month').format('YYYY-MM-DD'),
				},
				'magic-field-datetime-date-start-of-week': {
					id: 'magic-field-datetime-date-start-of-week',
					label: t('tables', 'Start of the week'),
					icon: 'icon-history',
					source: 'magic-fields',
					subline: t('tables', 'Magic field'),
					goodFor: ['datetime-date'],
					replace: new Moment().startOf('week').format('YYYY-MM-DD'),
				},
				'magic-field-datetime-time-now': {
					id: 'magic-field-datetime-time-now',
					label: t('tables', 'Now'),
					icon: 'icon-calendar-dark',
					source: 'magic-fields',
					subline: t('tables', 'Magic field'),
					goodFor: ['datetime-time'],
					replace: new Moment().format('HH:mm'),
				},
				'magic-field-datetime-now': {
					id: 'magic-field-datetime-now',
					label: t('tables', 'Now'),
					icon: 'icon-calendar-dark',
					source: 'magic-fields',
					subline: t('tables', 'Magic field'),
					goodFor: ['datetime'],
					replace: new Moment().format('YYYY-MM-DD HH:mm'),
				},
			},
			hideFilterInputForColumnTypes: [
				'selection-check',
				'number-stars',
			],
		}
	},

	methods: {
		getOperatorLabel(id) {
			if (id.substring(0, 9) !== 'operator-') {
				id = 'operator-' + id
			}
			return this.operators[id]?.shortLabel ?? this.operators[id]?.label
		},
		getPossibleOperators(column) {
			const columnType = column.type + (column.subtype ? '-' + column.subtype : '')
			return Object.values(this.operators).filter(item => item.goodFor.includes(columnType))
		},
		getPossibleMagicFields(column) {
			const columnType = column.type + (column.subtype ? '-' + column.subtype : '')
			return Object.values(this.magicFields).filter(item => item.goodFor.includes(columnType))
		},
		getIncompatibleFilters(id) {
			if (id.substring(0, 9) !== 'operator-') {
				id = 'operator-' + id
			}
			return this.operators[id]?.incompatibleWith
		},

	},

}
