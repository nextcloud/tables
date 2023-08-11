<template>
	<div class="filter-section">
		<div v-if="hasFilter" class="filter-text">
			{{ t('tables', 'Filtering rows') }}
		</div>
		<div v-for="(filterGroup, i) in mutableFilters" :key="i">
			<FilterGroup
				:filter-group="filterGroup"
				:view-filter-group="viewFilters ? viewFilters[i] ?? [] : null"
				:generated-filter-group="generatedFilters ? generatedFilters[i] ?? [] : null"
				:columns="columns"
				@delete-filter-group="deleteFilterGroup(i)" />
			<div v-if="i < filters.length - 1" class="filter-text">
				{{ t('tables', 'OR') }}
			</div>
		</div>
		<div v-if="filters.length > 0" class="filter-text">
			{{ t('tables', 'OR') }}
		</div>
		<NcButton
			:close-after-click="true"
			:aria-label="t('tables', 'Add new filter group')"
			type="tertiary"
			@click="addFilterGroup">
			{{ t('tables', 'Add new filter group') }}
			<template #icon>
				<Plus :size="25" />
			</template>
		</NcButton>
	</div>
</template>

<script>
import FilterGroup from './FilterGroup.vue'
import { NcButton } from '@nextcloud/vue'
import Plus from 'vue-material-design-icons/Plus.vue'

export default {
	name: 'FilterForm',
	components: {
		FilterGroup,
		NcButton,
		Plus,
	},
	props: {
		filters: {
			type: Array,
			default: null,
		},
		viewFilters: {
			type: Array,
			default: null,
		},
		generatedFilters: {
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
			mutableFilters: this.filters,
		}
	},

	computed: {
		hasFilter() {
			return (this.mutableFilters !== null && this.mutableFilters.length > 0)
		},
	},

	methods: {
		deleteFilterGroup(index) {
			this.mutableFilters.splice(index, 1)
		},
		addFilterGroup() {
			this.mutableFilters.push([{ columnId: null, operator: null, value: '' }])
		},
	},
}
</script>

<style scoped>
.filter-section {
	display: flex;
	flex-direction: column;
}

.filter-text {
	padding-top: 8px;
	padding-bottom: 8px;
}

</style>
