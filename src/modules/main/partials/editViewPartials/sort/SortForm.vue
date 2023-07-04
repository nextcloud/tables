<template>
	<div class="filter-section">
		<div v-for="(sortingRule, i) in sort" :key="i">
			<SortEntry :sort-entry="sortingRule" :columns="columns"
				@delete-sorting-rule="deleteSortingRule(i)" />
		</div>
		<NcButton :close-after-click="true" type="tertiary"
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
</template>

<script>
import SortEntry from './SortEntry.vue'
import { NcButton } from '@nextcloud/vue'
import Plus from 'vue-material-design-icons/Plus.vue'

export default {
	name: 'SortForm',
	components: {
		SortEntry,
		NcButton,
		Plus,
	},
	props: {
		sort: {
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
	methods: {
		deleteSortingRule(index) {
			this.mutableSort.splice(index, 1)
		},
		addSortingRule() {
			this.mutableSort.push({ columnId: null, mode: 'ASC' })
		},
	},
}
</script>

<style scoped>
</style>
