<template>
	<div>
		<div v-for="(filter, index) in filterGroup"
			:key="filter.columnId + filter.operator + filter.value + index">
			<FilterEntry
				:filter-entry="filter"
				:columns="columns"
				@delete-filter="deleteFilter(index)" />
		</div>
		<NcActions>
			<NcActionButton
				icon="icon-add"
				@click="addFilter">
				{{ t('tables', 'Add new filter') }}
			</NcActionButton>
		</NcActions>
	</div>
</template>

<script>
import FilterEntry from './FilterEntry.vue'
import { NcActions, NcActionButton } from '@nextcloud/vue'

export default {
	name: 'FilterGroup',
	components: {
		FilterEntry,
		NcActions,
		NcActionButton,
	},
	props: {
		filterGroup: {
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
			mutableFilterGroup: this.filterGroup,
		}
	},
	computed: {
	},
	watch: {
		filterGroup() {
			this.mutableFilterGroup = this.filterGroup
		},
	},
	methods: {
		addFilter() {
			this.mutableFilterGroup.push({ columnId: null, operator: null, value: '' })
		},
		deleteFilter(index) {
			console.debug('Delete filter at index ', index)
			this.mutableFilterGroup.splice(index, 1)
			if (this.mutableFilterGroup.length === 0) {
				this.$emit('delete-filter-group')
			}
			console.debug(this.mutableFilterGroup[index])
		},
	},
}
</script>
