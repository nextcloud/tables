<!--

This is a reusable component. There are no outside dependency's.

Emitted events:
edit-row                -> click on edit button in a row
update-selected-rows    -> send an array with selected row IDs <<< not yet

-->

<template>
	<div class="NcTable">
		<div class="options row">
			<Options />
		</div>
		<div class="custom-table row">
			<CustomTable :columns="columns" :rows="rows" @edit-row="rowId => $emit('edit-row', rowId)" />
		</div>
	</div>
</template>

<script>
import Options from './sections/Options.vue'
import CustomTable from './sections/CustomTable.vue'

export default {
	name: 'NcTable',
	components: { CustomTable, Options },
	props: {
		rows: {
			type: Array,
			default: () => [],
		},
		columns: {
			type: Array,
			default: () => [],
		},
	},
}
</script>

<style scoped>

</style>
