<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="filter-section">
		<div v-if="hasHiddenSortingRules">
			ℹ {{ t('tables', 'Among the sorting rules are some to which you have no permissions. However, if you like, you can override the sorting.') }}
			<NcButton
				:close-after-click="true"
				:aria-label="t('tables', 'Override sorting rules')"
				type="tertiary"
				@click="overrideRules">
				{{ t('tables', 'Override sorting rules') }}
				<template #icon>
					<Plus :size="25" />
				</template>
			</NcButton>
		</div>
		<div v-else>
			<div v-for="(sortingRule, i) in removedSortingRules" :key="'deleted'+sortingRule.columnId+i">
				<DeletedSortEntry
					:sort-entry="sortingRule"
					:columns="columns"
					class="locallyRemoved"
					@reactive-sorting-rule="reactiveSortingRule(sortingRule)" />
			</div>
			<div v-for="(sortingRule, i) in mutableSort" :key="sortingRule.columnId ?? '' + i">
				<SortEntry
					:sort-entry="sortingRule"
					:columns="eligibleColumns(sortingRule.columnId)"
					:class="{'locallyAdded': isLocallyAdded(sortingRule)}"
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
			<p class="span">
				{{ t('tables', 'The sorting rules are applied sequentially, meaning that if there are rows with the same priority to the first rule, the second rule determines the order among those rows.') }}
			</p>
		</div>
	</div>
</template>

<script>
import DeletedSortEntry from './DeletedSortEntry.vue'
import SortEntry from './SortEntry.vue'
import { NcButton } from '@nextcloud/vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import { ColumnTypes } from '../../../../../shared/components/ncTable/mixins/columnHandler.js'

export default {
	name: 'SortForm',
	components: {
		DeletedSortEntry,
		SortEntry,
		NcButton,
		Plus,
	},
	props: {
		sort: {
			type: Array,
			default: null,
		},
		viewSort: {
			type: Array,
			default: null,
		},
		generatedSort: {
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
			mutableSort: this.sort,
		}
	},
	computed: {
		removedSortingRules() {
			if (this.hadHiddenSortingRules || !this.viewSort || !this.generatedSort) return []
			return this.viewSort.filter(entry => !this.generatedSort.some(e => this.isSameEntry(e, entry)) && !this.sort.some(e => this.isSameEntry(e, entry)))
		},
		hasHiddenSortingRules() {
			return this.mutableSort.includes(null)
		},
		hadHiddenSortingRules() {
			return this.viewSort && this.viewSort.includes(null)
		},
	},
	watch: {
		mutableSort() {
			this.$emit('update:sort', this.mutableSort)
		},
	},
	methods: {
		reactiveSortingRule(entry) {
			this.mutableSort.unshift(entry)
		},
		isLocallyAdded(entry) {
			if (this.hadHiddenSortingRules || !this.viewSort || !this.generatedSort) return false
			return this.generatedSort.some(e => this.isSameEntry(e, entry)) && !this.viewSort.some(e => this.isSameEntry(e, entry))
		},
		isSameEntry(object, searchObject) {
			return Object.keys(searchObject).every((key) => object[key] === searchObject[key])
		},
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
		eligibleColumns(selectedId) { // filter sortable and unused columns
			if (this.hadHiddenSortingRules || !this.viewSort) return this.columns?.filter(col => col.canSort() && this.canBeSorted(col))
			return this.columns.filter(col => col.canSort() && this.canBeSorted(col) && (!this.viewSort.map(entry => entry.columnId).includes(col.id) || col.id === selectedId))
		},
		deleteSortingRule(index) {
			this.mutableSort.splice(index, 1)
		},
		addSortingRule() {
			this.mutableSort.push({ columnId: null, mode: 'ASC' })
		},
		overrideRules() {
			this.mutableSort.splice(0, this.mutableSort.length)
			this.addSortingRule()
		},
	},
}
</script>

<style scoped>
.locallyAdded {
	background-color: var(--color-success-hover);
}

.locallyRemoved {
	background-color: var(--color-error-hover);
}
</style>
