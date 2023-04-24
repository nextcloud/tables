<template>
	<div :class="{ empty: localValue === '' }">
		<NcRichContenteditable
			:user-data="getAllAutocompleteOptions"
			:auto-complete="autoComplete"
			:value.sync="localValue"
			:placeholder="t('tables', 'Search and filter (type @)')"
			@submit="submit" />
	</div>
</template>

<script>
import { NcRichContenteditable } from '@nextcloud/vue'
import { showWarning } from '@nextcloud/dialogs'
import searchAndFilterMixin from '../mixins/searchAndFilterMixin.js'

export default {

	components: {
		NcRichContenteditable,
	},

	mixins: [searchAndFilterMixin],

	props: {
		searchString: {
		      type: String,
		      default: null,
		    },
		filters: {
		      type: Array,
		      default: null,
		    },
		columns: {
		      type: Array,
		      default: null,
		    },
	},

	data() {
		return {
			localValue: '',
		}
	},

	computed: {
		getColumnsForAutocompletion() {
			const cols = {}
			this.columns?.forEach(col => {
				cols['column-' + col.id] = {
					id: 'column-' + col.id,
					label: col.title,
					icon: 'icon-rename',
					source: 'columns',
					subline: t('tables', 'Column to filter for'),
				}
			})
			return cols
		},
		getAllAutocompleteOptions() {
			return { ...this.getColumnsForAutocompletion, ...this.operators, ...this.magicFields }
		},
		isColumnChosen() {
			return this.localValue?.includes('@column-')
		},
		getChosenColumn() {
			const parts = this.localValue.match(/.*column-([0-9]*).*/)
			console.debug('column id from parts', parts)
			const id = parseInt(parts[1])
			return this.columns.find(item => item.id === id)
		},
		isOperatorChosen() {
			return this.localValue?.includes('@operator-')
		},
		isSearch() {
			return !this.isColumnChosen && !this.isOperatorChosen
		},
		getNeededAutocompletionOptions() {
			if (this.isColumnChosen && this.isOperatorChosen) {
				return this.getPossibleMagicFields
			}
			if (this.isColumnChosen && !this.isOperatorChosen) {
				const column = this.getChosenColumn
				console.debug('try to load column for needed autocomplete', this.getChosenColumn)
				const columnType = column.type + (column.subtype ? '-' + column.subtype : '')
				return Object.values(this.operators).filter(item => item.goodFor.includes(columnType))
			}
			return this.getColumnsForAutocompletion
		},
		getPossibleMagicFields() {
			const column = this.getChosenColumn
			const columnType = column.type + (column.subtype ? '-' + column.subtype : '')
			return Object.values(this.magicFields).filter(item => item.goodFor.includes(columnType))
		},
	},

	methods: {
		autoComplete(search, callback) {
			console.debug('autocomplete search', search)
			callback(Object.values(this.getNeededAutocompletionOptions))
		},
		submit() {
			if (this.isSearch) {
				this.doSearch()
			} else {
				this.addFilter()
			}
		},
		addFilter() {
			const parts = this.localValue.match(/.*(@column-[0-9]*).*(@operator-[a-z-]*)(.*)/)
			console.debug('parts', parts)
			if (parts === null) {
				showWarning(t('tables', 'You try to add a filter, make sure to chose a column, operator and search string.'))
				return
			}
			const columnToFilter = parts[1]?.trim() || ''
			const operator = parts[2]?.trim() || ''
			const searchString = parts[3]?.trim() || ''
			if (searchString === '') {
				showWarning(t('tables', 'Please specify a search string.'))
				return
			}

			const newOperator = operator.split('-')
			newOperator.shift()

			const filterObject = {
				columnId: parseInt(columnToFilter.split('-')[1]),
				operator: newOperator.join('-'),
				value: searchString,
			}
			this.$emit('add-filter', filterObject)
			this.localValue = this.searchString ? this.searchString : ''
		},
		doSearch() {
			this.$emit('set-search-string', this.localValue)
		},
	},

}
</script>
<style lang="scss" scoped>

.rich-contenteditable__input {
	width: 30vw;
	border-color: var(--color-primary-element);
}

.empty .rich-contenteditable__input {
	border-color: transparent;
}

</style>
