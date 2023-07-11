<template>
	<div class="filter-entry">
		<div class="selection-fields">
			<NcSelect
				v-model="selectedColumn"
				class="select-field"
				:options="columns"
				:get-option-key="(option) => option.id"
				:placeholder="t('tables', 'Column')"
				label="title" />
			<NcSelect
				v-if="selectedColumn"
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
				:placeholder="valuePlaceHolder"
				@search="v => term = v" />
		</div>
		<NcButton
			:close-after-click="true"
			type="tertiary"
			class="delete-button"
			:aria-label="t('tables', 'Delete filter')"
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
import Delete from 'vue-material-design-icons/Delete.vue'
import { ColumnTypes } from '../../../../../shared/components/ncTable/mixins/columnHandler.js'

export default {
	name: 'FilterEntry',
	components: {
		NcSelect,
		NcButton,
		Delete,
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
		valuePlaceHolder() {
			if (this.selectedColumn.type === ColumnTypes.Datetime) {
				return t('tables', 'JJJJ-MM-DD hh:mm')
			} else if (this.selectedColumn.type === ColumnTypes.DatetimeDate) {
				return t('tables', 'JJJJ-MM-DD')
			} else if (this.selectedColumn.type === ColumnTypes.DatetimeTime) {
				return t('tables', 'hh:mm')
			}
			return t('tables', 'Search Value')
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
				this.mutableFilterEntry.value = typeof this.searchValue === 'object' ? '@' + this.searchValue.id : this.searchValue
			} else {
				this.mutableFilterEntry.value = ''
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
			this.searchValue = this.filterEntry.value && this.filterEntry.value.startsWith('@') ? getMagicFieldWithId(this.filterEntry.value.substring(1)) : this.filterEntry.value
		},
	},
}
</script>

<style>
.filter-entry {
	display: flex;
	justify-content: space-between;
}

.select-field {
	width: 30%;
	/* flex: 1; */
	padding: 8px;
	min-width: auto !important;
}

.selection-fields {
	flex: 1;
	display: flex;
}
.delete-button {
	margin-left: auto;
}

</style>
