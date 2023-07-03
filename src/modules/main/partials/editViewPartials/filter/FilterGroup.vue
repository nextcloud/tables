<template>
	<div class="filter-group">
		<div class="group-text">
			{{ t('tables', '... that meet all of the following conditions') }}
		</div>
		<div v-for="(filter, index) in filterGroup"
			:key="filter.columnId + filter.operator + filter.value + index">
			<FilterEntry
				:filter-entry="filter"
				:columns="columns"
				@delete-filter="deleteFilter(index)" />
		</div>
		<NcButton
			:close-after-click="true"
			type="tertiary"
			@click="addFilter">
			{{ t('tables', 'Add new filter') }}
			<template #icon>
				<Plus :size="25" />
			</template>
		</NcButton>
	</div>
</template>

<script>
import FilterEntry from './FilterEntry.vue'
import { NcButton } from '@nextcloud/vue'
import Plus from 'vue-material-design-icons/Plus.vue'

export default {
	name: 'FilterGroup',
	components: {
		FilterEntry,
		NcButton,
		Plus,
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

<style>

.group-text {
	padding-left: calc(var(--default-grid-baseline) * 2);
}
.filter-group {
	border-left: 6px solid var(--color-primary) !important;
	padding-left: calc(var(--default-grid-baseline) * 2);
}
</style>
