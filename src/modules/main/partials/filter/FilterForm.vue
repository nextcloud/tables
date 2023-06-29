<template>
	<div>
		<div class="col-4 mandatory">
			{{ t('tables', 'Filtering rows') }}
		</div>
		<div v-for="(filterGroup, i) in filters" :key="i">
			{{ t('tables', 'Group') }} {{ i + 1 }}
			<FilterGroup
				:filter-group="filterGroup"
				:columns="columns"
				@delete-filter-group="deleteFilterGroup(i)" />
			<div v-if="i < filters.length - 1">
				{{ t('tables', 'OR') }}
			</div>
		</div>
		<NcActions>
			<NcActionButton
				icon="icon-add"
				@click="addFilterGroup">
				{{ t('tables', 'Add new filter') }}
			</NcActionButton>
		</NcActions>
	</div>
</template>

<script>
import FilterGroup from './FilterGroup.vue'
import { NcActions, NcActionButton } from '@nextcloud/vue'

export default {
	name: 'FilterForm',
	components: {
		FilterGroup,
		NcActions,
		NcActionButton,
	},
	props: {
		filters: {
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
	},
	methods: {
		deleteFilterGroup(index) {
			console.debug('Delete filter group at index ', index)
			this.mutableFilters.splice(index, 1)
		},
		addFilterGroup() {
			this.mutableFilters.push([{ columnId: null, operator: null, value: '' }])
		},
	},
}
</script>
