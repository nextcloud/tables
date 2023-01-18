<template>
	<tr>
		<th><NcCheckboxRadioSwitch :checked="allRowsAreSelected" @update:checked="value => $emit('select-all-rows', value)" /> </th>
		<th v-for="col in columns" :key="col.id">
			{{ col.title }}
		</th>
		<th />
	</tr>
</template>

<script>
import { NcCheckboxRadioSwitch } from '@nextcloud/vue'

export default {
	name: 'TableHeader',
	components: {
		NcCheckboxRadioSwitch,
	},
	props: {
		columns: {
			type: Array,
			default: () => [],
		},
		rows: {
			type: Array,
			default: () => [],
		},
		selectedRows: {
			type: Array,
			default: () => [],
		},
	},
	computed: {
		allRowsAreSelected() {
			if (Array.isArray(this.rows) && Array.isArray(this.selectedRows)) {
				return this.rows.length === this.selectedRows.length
			} else {
				return false
			}
		},
	},
}
</script>

<style scoped>

</style>
