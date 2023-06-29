<template>
	<div class="filter-entry">
		<NcSelect
			v-model="selectedColumnId"
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
		<!-- <NcSelect
			v-model="searchValue"
			class="select-field"
			:options="magicFieldsArray"
			:placeholder="t('tables', 'Search Value')" /> -->
		<FilterValueField
			:search-string="filterEntry.value" />
		<NcActions>
			<NcActionButton
				icon="icon-delete"
				@click="$emit('delete-filter')" />
		</NcActions>
	</div>
</template>

<script>
import debounce from 'debounce'
import { NcActions, NcActionButton, NcSelect } from '@nextcloud/vue'
import searchAndFilterMixin from '../../../../shared/components/ncTable/mixins/searchAndFilterMixin.js'
import FilterValueField from './FilterValueField.vue'

export default {
	name: 'FilterEntry',
	components: {
		NcSelect,
		NcActions,
		NcActionButton,
		FilterValueField,
	},
	mixins: [searchAndFilterMixin],
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
			selectedColumnId: null,
			selectedOperator: null,
			searchValue: '',
		}
	},
	computed: {
		operatorArray() {
			return Object.values(this.operators)
		},
		magicFieldsArray() {
			return [this.searchValue, ...Object.values(this.magicFields)]
		},
	},
	mounted() {
		this.reset()
	},
	methods: {

		clearValue() {
			this.searchValue = ''
		},
		debounceSubmit: debounce(function() {
			this.submit()
		}, 500),
		reset() {
			this.selectedColumnId = this.columns.find(col => col.id === this.filterEntry.columnId)
			this.selectedOperator = Object.values(this.operators).find(op => op.id.substring(9) === this.filterEntry.operator)
			this.searchValue = this.filterEntry.value
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
