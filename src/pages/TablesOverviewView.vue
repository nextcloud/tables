<template>
	<div class="row padding-left">
		<div class="col-4 row-with-margin">
			<h2>{{ t('tables', 'All tables') }}</h2>
		</div>
		<div class="col-4">
			<Box v-for="table in tables" :key="table.id" :header="table.title">
				{{ t('tables', 'Ownership: {ownerName}', { ownerName: table.ownership }) }}
			</Box>
		</div>
	</div>
</template>

<script>
import { mapState, mapGetters } from 'vuex'
import Box from '../partials/Box'

export default {
	name: 'TablesOverviewView',
	components: {
		Box,
	},
	data() {
		return {
		}
	},
	computed: {
		...mapState(['tables']),
		...mapGetters(['activeTable']),
	},
	methods: {
	},
}
</script>
