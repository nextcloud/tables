<template>
	<div class="menu" :class="{showOnHover: getSortMode === null}">
		<NcPopover :auto-hide="!isDropDownOpen" :focus-trap="false" :shown.sync="showPopover">
			<template #trigger>
				<NcButton
					:aria-label="t('tables', 'Column menu')"
					type="tertiary">
					<template #icon>
						<SortDesc v-if="getSortMode === 'DESC'" :size="20" />
						<SortAsc v-else-if="getSortMode === 'ASC'" :size="20" />
						<DotsHorizontal v-else :size="20" />
					</template>
				</NcButton>
			</template>
			<div class="column-option-wrapper">
				<div v-if="canSort" class="order-mode">
					{{ t('tables', 'Sorting') }}
				</div>
				<div v-if="canSort" class="mode-switch">
					<NcCheckboxRadioSwitch
						:button-variant="true"
						:checked.sync="sortMode"
						value="asc"
						type="checkbox"
						button-variant-grouped="horizontal"
						class="mode-checkbox"
						:class="{'mode-selected': sortMode === 'ASC'}"
						@update:checked="sort('ASC')">
						<SortAsc :size="20" class="mode-icon" />
					</NcCheckboxRadioSwitch>
					<NcCheckboxRadioSwitch
						:button-variant="true"
						:checked.sync="sortMode"
						value="desc"
						type="checkbox"
						button-variant-grouped="horizontal"
						class="mode-checkbox"
						:class="{'mode-selected': sortMode === 'DESC'}"
						@update:checked="sort('DESC')">
						<SortDesc :size="20" class="mode-icon" />
					</NcCheckboxRadioSwitch>
				</div>
				<div class="inter-header">
					{{ t('tables', 'Filtering') }}
				</div>

				<NcSelect
					v-model="selectedOperator"
					class="select-field"
					:options="getOperators"
					:get-option-key="(option) => option.id"
					:placeholder="t('tables', 'Operator')"
					label="label"
					@search:focus="isDropDownOpen = true"
					@search:blur="isDropDownOpen = false"
					@option:selected="changeFilterOperator" />
				<NcSelect
					v-if="selectedOperator && !selectedOperator.noSearchValue"
					v-model="searchValue"
					class="select-field"
					:options="magicFieldsArray"
					:placeholder="t('tables', 'Search Value')"
					@search="v => term = v"
					@search:focus="isDropDownOpen = true"
					@search:blur="isDropDownOpen = false"
					@option:selected="submitFilterInput" />
				<div class="inter-header">
					{{ t('tables', 'Manage column') }}
				</div>
				<div class="bottom-buttons">
					<NcButton
						type="secondary"
						class="column-button"
						:aria-label="t('tables', 'Hide column')"
						:disabled="!canHide"
						@click="hideColumn()">
						<template #icon>
							<EyeOff :size="25" />
						</template>
					</NcButton>
					<NcButton v-if="column.id >= 0 && canManageTable(activeView)" :aria-label="t('tables', 'Edit Column')" type="secondary" class="column-button" @click="editColumn()">
						<template #icon>
							<Pencil :size="25" />
						</template>
					</NcButton>
					<NcButton v-if="column.id >= 0 && canManageTable(activeView)" type="error" :aria-label="t('tables', 'Delete Column')" class="column-button" @click="deleteColumn()">
						<template #icon>
							<Delete :size="25" />
						</template>
					</NcButton>
				</div>
			</div>
		</NcPopover>
	</div>
</template>

<script>
import generalHelper from '../../../mixins/generalHelper.js'
import SortAsc from 'vue-material-design-icons/SortAscending.vue'
import SortDesc from 'vue-material-design-icons/SortDescending.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import EyeOff from 'vue-material-design-icons/EyeOff.vue'
import DotsHorizontal from 'vue-material-design-icons/DotsHorizontal.vue'
import permissionsMixin from '../mixins/permissionsMixin.js'
import { NcPopover, NcCheckboxRadioSwitch, NcButton, NcSelect } from '@nextcloud/vue'
import { mapState, mapGetters } from 'vuex'
import { AbstractColumn } from '../mixins/columnClass.js'
import { ColumnTypes } from '../mixins/columnHandler.js'
import { FilterIds } from '../mixins/filter.js'
import { emit } from '@nextcloud/event-bus'

export default {

	components: {
		NcCheckboxRadioSwitch,
		EyeOff,
		Delete,
		Pencil,
		SortAsc,
		SortDesc,
		DotsHorizontal,
		NcSelect,
		NcPopover,
		NcButton,
	},

	mixins: [generalHelper, permissionsMixin],

	props: {
		column: {
		      type: AbstractColumn,
		      default: null,
		    },
		canHide: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			showPopover: false,
			isDropDownOpen: false,
			test: '',
			sortMode: null,
			searchValue: '',
			selectedOperator: null,
			hideFilterInputForColumnTypes: [
				ColumnTypes.SelectionCheck,
				ColumnTypes.NumberStars,
			],
			term: '',
		}
	},

	computed: {
		...mapState({
			viewSetting: state => state.data.viewSetting,
		}),
		...mapGetters(['activeView']),
		getOperators() {
			const possibleOperators = this.column.getPossibleOperators()
			// preselect first operator, even if it's not displayed
			if (this.selectedOperator === null) {
				// eslint-disable-next-line vue/no-side-effects-in-computed-properties
				this.selectedOperator = possibleOperators[0]
			}
			return possibleOperators
		},
		magicFieldsArray() {
			if (this.selectedOperator) {
				if (this.term) {
					return [this.term, ...this.column.getPossibleMagicFields()]
				}
				return this.column.getPossibleMagicFields()
			} else {
				return []
			}
		},
		canSort() {
			return this.column.canSort()
		},
		getSortMode() {
			const sortObject = this.viewSetting.sorting?.find(item => item.columnId === this.column?.id)
			if (sortObject) {
				return sortObject.mode
			}
			return null
		},
	},
	watch: {
		showPopover() {
			if (this.showPopover) {
				this.reset()
			}
		},
	},
	methods: {
		changeFilterOperator() {
			if (this.selectedOperator.id === FilterIds.IsEmpty) {
				this.createFilter()
			}
		},
		submitFilterInput() {
			// Ignore contains filter with the same value es old contain filters
			if (this.selectedOperator.id === FilterIds.Contains) {
				const columnFilters = this.getFilterForColumn(this.column)
				if (columnFilters && columnFilters.filter(fil => fil.operator.id === FilterIds.Contains).map(fil => fil.value).includes(this.searchValue)) {
					this.reset()
					return
				}
			}
			this.createFilter()
		},
		getFilterForColumn(column) {
			return this.viewSetting?.filter?.filter(item => item.columnId === column.id)
		},
		createFilter() {
			const filterObject = {
				columnId: this.column.id,
				operator: this.selectedOperator,
				value: typeof this.searchValue === 'object' ? '@' + this.searchValue.id : this.searchValue,
			}
			console.debug('emitting new filterObject', filterObject)
			this.$emit('add-filter', filterObject)
			this.close()
		},
		close() {
			this.reset()
			this.showPopover = false
		},
		reset() {
			this.selectedOperator = null
			this.searchValue = ''
			this.sortMode = this.getSortMode
		},
		sort(mode) {
			if (mode === this.getSortMode) {
				this.sortMode = null
				this.$store.dispatch('removeSorting', { columnId: this.column.id })
			} else {
				this.sortMode = mode
				this.$store.dispatch('setSorting', { columnId: this.column.id, mode })
			}
			this.close()
		},
		hideColumn() {
			this.close()
			this.$store.dispatch('hideColumn', { columnId: this.column.id })
		},
		editColumn() {
			this.close()
			emit('tables:column:edit', this.column)
		},
		deleteColumn() {
			this.close()
			emit('tables:column:delete', this.column)
		},
	},

}
</script>
<style lang="scss" scoped>

.column-option-wrapper {
	padding: calc(var(--default-grid-baseline) * 2);
	display: flex;
    flex-direction: column;
    align-items: center;
	width: 200px;
}

.menu {
	padding-left: calc(var(--default-grid-baseline) * 1);
}

.mode-selected {
	background-color: var(--color-primary-element-light) !important;
}

.select-field {
	width: 100%;
	padding: 8px;
}

.mode-switch {
	width: 100%;
	display: flex;
	padding: calc(var(--default-grid-baseline) * 2);
}

.mode-checkbox {
	width: 50%;
}

:deep(.checkbox-radio-switch--button-variant.checkbox-radio-switch--checked) {
	border: 2px solid var(--color-border-dark);
}

:deep(.checkbox-radio-switch__label) {
	display: flex;
    justify-content: center;
}

.inter-header {
	padding-top: calc(var(--default-grid-baseline) * 2);
}
.bottom-buttons {
	width: 100%;
	padding-top:  calc(var(--default-grid-baseline) * 2);
	display: flex;
	justify-content: space-between;
}

.menu {
	padding-left: calc(var(--default-grid-baseline) * 1);
}

.order-mode {
	width: 100%;
	display: flex;
	align-items: center;
	flex-direction: column;
}
</style>
