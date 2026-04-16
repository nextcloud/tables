<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="filter-section">
		<p v-if="mutableSort.length >= 2" class="span">
			{{ t('tables', 'Rules are applied in order. The first rule sorts all rows, and any additional rules determine the order within any group of rows that share the same value.') }}
		</p>
		<div v-for="(sortingRule, i) in mutableSort" :key="sortingRule.columnId ?? '' + i">
			<SortEntry
				:sort-entry="sortingRule"
				:columns="eligibleColumns(sortingRule.columnId)"
				@delete-sorting-rule="deleteSortingRule(i)" />
		</div>
		<NcButton
			:close-after-click="true"
			:aria-label="t('tables', 'Add new sorting rule')"
			type="tertiary"
			@click="addSortingRule">
			{{ t('tables', 'Add new sorting rule') }}
			<template #icon>
				<Plus :size="25" />
			</template>
		</NcButton>
	</div>
</template>

<script>
import SortEntry from '../editViewPartials/sort/SortEntry.vue'
import { NcButton } from '@nextcloud/vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import { ColumnTypes } from '../../../../shared/components/ncTable/mixins/columnHandler.js'

export default {
	name: 'DefaultSortRules',
	components: {
		SortEntry,
		NcButton,
		Plus,
	},
	props: {
		sortRules: {
			type: Array,
			default: () => [],
		},
		columns: {
			type: Array,
			default: null,
		},
	},
	data() {
		return {
			mutableSort: [...(this.sortRules ?? [])],
		}
	},
	watch: {
		mutableSort() {
			this.$emit('update', this.mutableSort)
		},
	},
	methods: {
		/**
		 * The method rejects column types for which there is no sort support on the backend.
		 *
		 * Important:
		 * - Not all columns that are sortable on the front-end are sortable on the back-end.
		 * - Example: "selection" fields — the front-end can sort them by value (since it has it),
		 *   but the back-end only stores an ID and cannot easily JOIN the value for sorting.
		 * @param col {AbstractColumn} The column to check.
		 */
		canBeSorted(col) {
			return ![
				ColumnTypes.Selection,
				ColumnTypes.SelectionMulti,
				ColumnTypes.TextLink,
				ColumnTypes.Usergroup,
			].includes(col.type)
		},
		eligibleColumns(selectedId) {
			return this.columns?.filter(col => col.canSort() && this.canBeSorted(col) && (!this.mutableSort.map(e => e.columnId).includes(col.id) || col.id === selectedId))
		},
		deleteSortingRule(index) {
			this.mutableSort.splice(index, 1)
		},
		addSortingRule() {
			this.mutableSort.push({ columnId: null, mode: 'ASC' })
		},
	},
}
</script>
