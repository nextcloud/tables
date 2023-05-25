<template>
	<div class="menu" :class="{showOnHover: getSortMode === null}">
		<NcActions :open.sync="localOpenState" :force-menu="true">
			<template v-if="getSortMode !== null" #icon>
				<SortDesc v-if="getSortMode === 'desc'" :size="20" />
				<SortAsc v-else-if="getSortMode === 'asc'" :size="20" />
			</template>
			<NcActionButtonGroup v-if="canSort" :title="t('tables', 'Sorting')">
				<NcActionButton :class="{ selected: getSortMode === 'asc' }" :aria-label="t('tables', 'Sort asc')" @click="sort('asc')">
					<template #icon>
						<SortAsc :size="20" />
					</template>
				</NcActionButton>
				<NcActionButton :class="{ selected: getSortMode === 'desc' }" :aria-label="t('tables', 'Sort desc')" @click="sort('desc')">
					<template #icon>
						<SortDesc :size="20" />
					</template>
				</NcActionButton>
			</NcActionButtonGroup>
			<NcActionSeparator v-if="canSort && haveOperators" />
			<NcActionCaption v-if="haveOperators" :title="t('tables', 'Filtering')" />
			<NcActionRadio
				v-for="(op, index) in getOperators"
				:key="index"
				:name="'filter-operators-column-' + column.id"
				:value="op.id"
				:checked="operator === op.id"
				@change="changeFilterOperator">
				{{ op.label }}
			</NcActionRadio>
			<NcActionInput
				v-if="canFilterWithTextInput && haveOperators"
				:label-visible="false"
				:label="t('tables', 'Keyword and submit')"
				:value.sync="filterValue"
				:show-trailing-button="true"
				@submit="submitFilterInput">
				<template #icon>
					<Pencil :size="20" />
				</template>
			</NcActionInput>
			<NcActionCaption
				v-if="getPossibleMagicFields(column).length > 0 && canFilterWithTextInput && haveOperators"
				:title="t('tables', 'Or use magic values')" />
			<NcActionCaption
				v-if="getPossibleMagicFields(column).length > 0 && !canFilterWithTextInput && haveOperators"
				:title="t('tables', 'Choose value')" />
			<NcActionButton
				v-for="(magicField, index) in getMagicFields"
				:key="'magic-field-' + index"
				:value="magicField.id"
				:checked="index === 0"
				:icon="magicField.icon"
				@click="submitMagicField(magicField.id)">
				{{ magicField.label }}
			</NcActionButton>
		</NcActions>
	</div>
</template>

<script>
import textLineMixin from '../mixins/columnsTypes/textLineMixin.js'
import textLinkMixin from '../mixins/columnsTypes/textLinkMixin.js'
import selectionMixin from '../mixins/columnsTypes/selectionMixin.js'
import numberMixin from '../mixins/columnsTypes/numberMixin.js'
import selectionCheckMixin from '../mixins/columnsTypes/selectionCheckMixin.js'
import numberStarsMixin from '../mixins/columnsTypes/numberStarsMixin.js'
import numberProgressMixin from '../mixins/columnsTypes/numberProgressMixin.js'
import datetimeDateMixin from '../mixins/columnsTypes/datetimeDateMixin.js'
import datetimeTimeMixin from '../mixins/columnsTypes/datetimeTimeMixin.js'
import datetimeMixin from '../mixins/columnsTypes/datetimeMixin.js'
import generalHelper from '../../../mixins/generalHelper.js'
import SortAsc from 'vue-material-design-icons/SortAscending.vue'
import SortDesc from 'vue-material-design-icons/SortDescending.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import { NcActions, NcActionButton, NcActionInput, NcActionButtonGroup, NcActionSeparator, NcActionCaption, NcActionRadio } from '@nextcloud/vue'
import { mapState } from 'vuex'
import searchAndFilterMixin from '../mixins/searchAndFilterMixin.js'

export default {

	components: {
		Pencil,
		NcActionInput,
		NcActionRadio,
		NcActionCaption,
		NcActionButton,
		NcActions,
		SortAsc,
		SortDesc,
		NcActionButtonGroup,
		NcActionSeparator,
	},

	mixins: [
		textLineMixin,
		selectionMixin,
		numberMixin,
		generalHelper,
		selectionCheckMixin,
		textLinkMixin,
		numberStarsMixin,
		numberProgressMixin,
		datetimeDateMixin,
		datetimeTimeMixin,
		datetimeMixin,
		searchAndFilterMixin,
	],

	props: {
		column: {
		      type: Object,
		      default: null,
		    },
		openState: {
		      type: Boolean,
		      default: false,
		    },
	},

	data() {
		return {
			filterValue: '',
			operator: '',
		}
	},

	computed: {
		...mapState({
			view: state => state.data.view,
		}),
		haveOperators() {
			const columnOperators = this.getOperators
			return columnOperators && columnOperators.length > 0
		},
		getOperators() {
			console.debug('getOperators requested')
			const possibleOperators = this.getPossibleOperators(this.column)

			// preselect first operator, even if it's not displayed
			if (this.operator === '') {
				console.debug('operator is empty, try to set first option', possibleOperators)
				// eslint-disable-next-line vue/no-side-effects-in-computed-properties
				this.operator = possibleOperators[0]?.id ?? ''
				console.debug('operator', this.operator)
			}

			// filter filters that cannot be combined
			const filters = this.getFilterForColumn(this.column)
			if (filters && filters.length > 0) {
				const incompatibleFilters = new Set()
				filters.forEach(fil => {
					this.getIncompatibleFilters(fil.operator).forEach(item => incompatibleFilters.add(item))
				})
				return possibleOperators.filter(op => !incompatibleFilters.has(op.id))
			}
			return possibleOperators
		},
		getMagicFields() {
			if (!this.haveOperators) {
				return []
			}
			return this.getPossibleMagicFields(this.column)
		},
		canSort() {
			const sortFuncName = 'sorting' + this.ucfirst(this.column?.type) + this.ucfirst(this.column?.subtype)
			if (this[sortFuncName] instanceof Function) {
				return true
			}
			console.info('no sort function for column found', { columnId: this.column.id, expectedSortMethod: sortFuncName })
			return false
		},
		getSortMode() {
			const sortObject = this.view.sorting?.find(item => item.columnId === this.column?.id)
			if (sortObject) {
				return sortObject.mode
			}
			return null
		},
		localOpenState: {
			get() {
				return this.openState
			},
			set(v) {
				this.$emit('update:open-state', !!v)
			},
		},
		canFilterWithTextInput() {
			const columnType = this.column.type + (this.column.subtype ? '-' + this.column.subtype : '')
			return !this.hideFilterInputForColumnTypes.includes(columnType)
		},
		getFilterOperator() {
			const tmp = this.operator.split('-')
			tmp.shift()
			return tmp.join('-')
		},
	},

	methods: {
		submitMagicField(magicFieldId) {
			console.debug('submitted magic field', magicFieldId)
			this.filterValue = '@' + magicFieldId
			this.submitFilterInput()
		},
		changeFilterOperator(event) {
			console.debug('operator changed', event?.target?.value)
			this.operator = event?.target?.value
			if (this.operator === 'operator-is-empty') {
				this.submitFilter()
			}
		},
		submitFilterInput() {
			console.debug('submit clicked', this.filterValue)

			if (this.operator === 'operator-contains') {
				const columnFilters = this.getFilterForColumn(this.column)
				if (columnFilters && columnFilters.filter(fil => fil.operator === 'contains').map(fil => fil.value).includes(this.filterValue)) {
					this.localOpenState = false
					this.reset()
					return
				}
			}
			this.submitFilter()
		},
		submitFilter() {
			this.createFilter()
			this.localOpenState = false
		},
		getFilterForColumn(column) {
			return this.view?.filter?.filter(item => item.columnId === column.id)
		},
		createFilter() {
			const filterObject = {
				columnId: this.column.id,
				operator: this.getFilterOperator,
				value: this.filterValue,
			}
			console.debug('emitting new filterObject', filterObject)
			this.$emit('add-filter', filterObject)
			this.reset()
		},
		reset() {
			this.operator = ''
			this.filterValue = ''
		},
		sort(mode) {
			this.$store.dispatch('addSorting', { columnId: this.column.id, mode })
		},
	},

}
</script>
<style lang="scss" scoped>

.menu {
	padding-left: calc(var(--default-grid-baseline) * 1);
}

.selected {
	background-color: var(--color-primary-element-light) !important;
	border-radius: 6px;
}

</style>
