<template>
	<div class="filter-entry">
		<NcSelect
			v-model="selectedColumn"
			class="select-field"
			:options="columns"
			:get-option-key="(option) => option.id"
			:placeholder="t('tables', 'Column')"
			label="title" />
		<NcSelect
			v-model="selectedOperator"
			class="select-field"
			:options="operatorArray"
			:get-option-key="(option) => option.id"
			:placeholder="t('tables', 'Operator')"
			label="label" />
		<NcSelect
			v-if="selectedOperator && !selectedOperator.noSearchValue"
			v-model="searchValue"
			class="select-field"
			:options="magicFieldsArray"
			:placeholder="t('tables', 'Search Value')"
			@search="v => term = v" />
		<!-- <FilterValueField
			:search-string="filterEntry.value" /> -->
		<NcButton
			:close-after-click="true"
			type="tertiary"
			@click="$emit('delete-filter')">
			<template #icon>
				<Delete :size="25" />
			</template>
		</NcButton>
	</div>
</template>

<script>
import { NcButton, NcSelect } from '@nextcloud/vue'
import { getFilterWithId } from '../../../../../shared/components/ncTable/mixins/filter.js'
import { getMagicFieldWithId } from '../../../../../shared/components/ncTable/mixins/magicFields.js'
import FilterValueField from './FilterValueField.vue'
import Delete from 'vue-material-design-icons/Delete.vue'

export default {
	name: 'FilterEntry',
	components: {
		NcSelect,
		NcButton,
		Delete,
		FilterValueField,
	},
	props: {
		filterEntry: {
			type: Object,
			default: null,
		},
		columns: {
			type: Array,
			default: null,
		},
	},
	data() {
		return {
			selectedColumn: null,
			selectedOperator: null,
			mutableFilterEntry: this.filterEntry,
			searchValue: '',
			term: '',
		}
	},
	computed: {
		operatorArray() {
			if (this.selectedColumn) {
				return this.selectedColumn.getPossibleOperators()
			} else {
				return []
			}
		},
		magicFieldsArray() {
			if (this.selectedColumn) {
				if (this.term) {
					return [this.term, ...this.selectedColumn.getPossibleMagicFields()]
				}
				return this.selectedColumn.getPossibleMagicFields()
			} else {
				return []
			}
		},
	},
	watch: {
		selectedColumn() {
			if (!this.selectedOperator || (this.selectedOperator && !this.operatorArray.includes(this.selectedOperator))) {
				if (this.operatorArray.length === 1) {
					this.selectedOperator = this.operatorArray[0]
				} else {
					this.selectedOperator = null
				}
			}
			if (this.searchValue && typeof this.searchValue === 'object' && !this.magicFieldsArray.includes(this.searchValue)) {
				this.searchValue = null
			}
			this.mutableFilterEntry.columnId = this.selectedColumn?.id
		},
		selectedOperator() {
			this.mutableFilterEntry.operator = this.selectedOperator?.id
		},
		searchValue() {
			if (this.searchValue) {
				this.mutableFilterEntry.value = typeof this.searchValue === 'object' ? this.searchValue.id : this.searchValue
			} else {
				this.mutableFilterEntry.value = undefined
			}
		},
	},
	mounted() {
		this.reset()
	},
	methods: {

		clearValue() {
			this.searchValue = ''
		},
		reset() {
			this.selectedColumn = this.columns.find(col => col.id === this.filterEntry.columnId)
			this.selectedOperator = getFilterWithId(this.filterEntry.operator)
			this.searchValue = getMagicFieldWithId(this.filterEntry.value) ?? this.filterEntry.value
		},
	},
}
</script>

<style>
.filter-entry {
	display: flex
}

.select-field {
	width: 30%;
	padding: 8px;
	min-width: auto !important;
}

</style>
