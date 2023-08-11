<template>
	<div class="menu" :class="{showOnHover: getSortMode === null}">
		<NcActions :open.sync="localOpenState" :force-menu="true">
			<template v-if="getSortMode !== null" #icon>
				<SortDesc v-if="getSortMode === 'DESC'" :size="20" />
				<SortAsc v-else-if="getSortMode === 'ASC'" :size="20" />
			</template>
			<template v-if="selectOperator">
				<NcActionButton @click="selectOperator = false">
					<template #icon>
						<ChevronLeft :size="25" />
					</template>
					{{ t('tables', 'Back') }}
				</NcActionButton>
				<NcActionCaption :title="t('tables', 'Select operator')" />
				<NcActionRadio
					v-for="(op, index) in getOperators"
					:key="index"
					:name="'filter-operators-column-' + column.id"
					:value="op.id"
					:checked="selectedOperator.id === op.id"
					:disabled="isDisabled(op.id)"
					@change="changeFilterOperator(op)">
					{{ op.label }}
				</NcActionRadio>
			</template>
			<template v-else-if="selectValue">
				<NcActionButton @click="selectValue = false">
					<template #icon>
						<ChevronLeft :size="25" />
					</template>
					{{ t('tables', 'Back') }}
				</NcActionButton>
				<NcActionCaption :title="t('tables', 'Search for value')" />
				<NcActionInput
					:label-visible="false"
					:label="t('tables', 'Keyword and submit')"
					:value.sync="searchValue"
					:show-trailing-button="true"
					@submit="submitFilterInput">
					<template #icon>
						<Magnify :size="20" />
					</template>
				</NcActionInput>
				<NcActionCaption
					v-if="getMagicFields.length > 0"
					:title="t('tables', 'Or use magic values')" />
				<NcActionButton
					v-for="(magicField, index) in getMagicFields"
					:key="'magic-field-' + index"
					:value="magicField.id"
					:checked="index === 0"
					:icon="magicField.icon"
					@click="submitMagicField(magicField)">
					{{ magicField.label }}
				</NcActionButton>
			</template>
			<template v-else>
				<NcActionCaption v-if="canSort" :title="t('tables', 'Sorting')" />
				<NcActionButtonGroup v-if="canSort">
					<NcActionButton :class="{ selected: getSortMode === 'ASC' }" :aria-label="t('tables', 'Sort asc')" @click="sort('ASC')">
						<template #icon>
							<SortAsc :size="20" />
						</template>
					</NcActionButton>
					<NcActionButton :class="{ selected: getSortMode === 'DESC' }" :aria-label="t('tables', 'Sort desc')" @click="sort('DESC')">
						<template #icon>
							<SortDesc :size="20" />
						</template>
					</NcActionButton>
				</NcActionButtonGroup>
				<NcActionCaption v-if="hasOperators" :title="t('tables', 'Filtering')" />
				<NcActionButton
					v-if="hasOperators"
					:title="selectedOperator.label"
					@click="selectOperator = true">
					<template #icon>
						<FilterCog :size="25" />
					</template>
					{{ t('tables', 'Select Operator') }}
				</NcActionButton>
				<NcActionButton
					v-if="hasOperators"
					@click="selectValue = true">
					<template #icon>
						<Magnify :size="25" />
					</template>
					{{ t('tables', 'Select value') }}
				</NcActionButton>
				<NcActionCaption :title="t('tables', 'Manage column')" />
				<NcActionButtonGroup :name="t('tables', 'Column manage actions')">
					<NcActionButton
						:disabled="!canHide"
						@click="hideColumn()">
						<template #icon>
							<EyeOff :size="25" />
						</template>
					</NcActionButton>
					<NcActionButton v-if="column.id >= 0 && config.canEditColumns" @click="editColumn()">
						<template #icon>
							<Pencil :size="25" />
						</template>
					</NcActionButton>
					<NcActionButton v-if="column.id >= 0 && config.canDeleteColumns" @click="deleteColumn()">
						<template #icon>
							<Delete :size="25" />
						</template>
					</NcActionButton>
				</NcActionButtonGroup>
			</template>
		</NcActions>
	</div>
</template>

<script>
import generalHelper from '../../../mixins/generalHelper.js'
import SortAsc from 'vue-material-design-icons/SortAscending.vue'
import SortDesc from 'vue-material-design-icons/SortDescending.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import EyeOff from 'vue-material-design-icons/EyeOff.vue'
import ChevronLeft from 'vue-material-design-icons/ChevronLeft.vue'
import FilterCog from 'vue-material-design-icons/FilterCog.vue'
import Magnify from 'vue-material-design-icons/Magnify.vue'
import { NcActions, NcActionButton, NcActionInput, NcActionButtonGroup, NcActionCaption, NcActionRadio } from '@nextcloud/vue'
import { AbstractColumn } from '../mixins/columnClass.js'
import { FilterIds } from '../mixins/filter.js'
import { translate as t } from '@nextcloud/l10n'

export default {
	components: {
		EyeOff,
		Delete,
		Pencil,
		ChevronLeft,
		FilterCog,
		Magnify,
		NcActionInput,
		NcActionRadio,
		NcActionCaption,
		NcActionButton,
		NcActions,
		SortAsc,
		SortDesc,
		NcActionButtonGroup,
	},
	mixins: [generalHelper],
	props: {
		column: {
		      type: AbstractColumn,
		      default: null,
		    },
		openState: {
		      type: Boolean,
		      default: false,
		},
		canHide: {
			type: Boolean,
			default: false,
		},
		config: {
			type: Object,
			default: null,
		},
		viewSetting: {
			type: Object,
			default: null,
		},
	},
	data() {
		return {
			searchValue: '',
			operator: null,
			sortMode: null,
			term: '',
			selectOperator: false,
			selectValue: false,
			localViewSetting: this.viewSetting,
		}
	},
	computed: {
		getOperators() {
			const possibleOperators = this.column.getPossibleOperators()
			return possibleOperators
		},
		getDisabledOperators() {
			// filter filters that cannot be combined
			const filters = this.getFilterForColumn(this.column)
			if (filters && filters.length > 0) {
				const incompatibleFilters = new Set()
				filters.forEach(fil => {

					fil.operator.incompatibleWith.forEach(item => incompatibleFilters.add(item))
				})
				return this.getOperators.filter(op => incompatibleFilters.has(op.id))
			}
			return []
		},
		getEnabledOperators() {
			// filter filters that cannot be combined
			const filters = this.getFilterForColumn(this.column)
			if (filters && filters.length > 0) {
				const incompatibleFilters = new Set()
				filters.forEach(fil => {

					fil.operator.incompatibleWith.forEach(item => incompatibleFilters.add(item))
				})
				return this.getOperators.filter(op => !incompatibleFilters.has(op.id))
			}
			return this.getOperators
		},
		hasOperators() {
			return this.getEnabledOperators.length > 0
		},
		getMagicFields() {
			return this.column.getPossibleMagicFields()
		},
		canSort() {
			return this.column.canSort()
		},
		getSortMode() {
			const sortObject = this.localViewSetting?.sorting?.find(item => item.columnId === this.column?.id)
			if (sortObject) {
				return sortObject.mode
			}
			return null
		},
		selectedOperator: {
			get() {
				if (this.operator === null) {
					return this.getEnabledOperators[0]
				} else {
					return this.operator
				}
			},
			set(v) {
				this.operator = v
			},
		},
		localOpenState: {
			get() {
				return this.openState
			},
			set(v) {
				this.$emit('update:open-state', !!v)
			},
		},
	},
	watch: {
		localOpenState() {
			this.reset()
		},
		localViewSetting() {
			this.$emit('update:viewSetting', this.localViewSetting)
		},
		viewSetting() {
			this.localViewSetting = this.viewSetting
		},
	},
	created() {
		this.reset()
	},
	methods: {
		t,
		isDisabled(op) {
			return this.getDisabledOperators.map(o => o.id).includes(op)
		},
		submitMagicField(magicField) {
			this.searchValue = '@' + magicField.id
			this.createFilter()
			this.localOpenState = false
		},
		close() {
			this.localOpenState = false
		},
		changeFilterOperator(op) {
			this.selectedOperator = op
			this.selectOperator = false
			if (op.id === FilterIds.IsEmpty) {
				this.createFilter()
			} else {
				this.selectValue = true
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
			this.localOpenState = false
		},
		getFilterForColumn(column) {
			return this.localViewSetting?.filter?.filter(item => item.columnId === column.id)
		},
		createFilter() {
			const filterObject = {
				columnId: this.column.id,
				operator: this.selectedOperator,
				value: this.searchValue,
			}
			if (!this.localViewSetting.filter) {
				this.localViewSetting.filter = []
			}
			this.localViewSetting.filter.push(filterObject)
			this.localViewSetting = JSON.parse(JSON.stringify(this.localViewSetting))
			this.close()
		},
		reset() {
			this.operator = null
			this.searchValue = ''
			this.sortMode = this.getSortMode
			this.selectOperator = false
			this.selectValue = false
		},
		sort(mode) {
			if (mode === this.getSortMode) {
				this.sortMode = null
				this.localViewSetting.sorting = null
			} else {
				this.sortMode = mode
				if (mode !== 'ASC' && mode !== 'DESC') {
					return
				}
				this.localViewSetting.sorting = [{
					columnId: this.column.id,
					mode,
				}]
			}
			this.localViewSetting = JSON.parse(JSON.stringify(this.localViewSetting))
			this.close()
		},
		hideColumn() {
			this.close()
			if (!this.localViewSetting.hiddenColumns) {
				this.localViewSetting.hiddenColumns = [this.column.id]
			} else {
				this.localViewSetting.hiddenColumns.push(this.column.id)
			}
			this.localViewSetting = JSON.parse(JSON.stringify(this.localViewSetting))
		},
		editColumn() {
			this.close()
			this.$emit('edit-column', this.column)
		},
		deleteColumn() {
			this.close()
			this.$emit('delete-column', this.column)
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

.selected-option {
	width: 100%;
}
</style>
