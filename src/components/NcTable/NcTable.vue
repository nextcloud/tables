<template>
	<div class="NcTable">
		<div class="options row">
			<Options />
		</div>
		<div class="custom-table row">
			<CustomTable :columns="columns" :rows="rows" />
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
