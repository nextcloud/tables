<template>
	<div>
		<CreateRow :columns="columns"
			:show-modal="showCreateRow"
			@close="showCreateRow = false" />
		<EditRow :columns="columns"
			:row="getEditRow"
			:show-modal="editRowId !== null"
			@close="editRowId = null" />
	</div>
</template>

<script>
import CreateRow from '../modals/CreateRow.vue'
import EditRow from '../modals/EditRow.vue'
import { mapGetters, mapState } from 'vuex'

export default {
	// TODO: outdated?
	name: 'Modals',
	components: {
		CreateRow,
		EditRow,
	},
	props: {
		columns: {
			type: Array,
			default: () => [],
		},
	},
	data() {
		return {
			showCreateRow: false,
			editRowId: null, // null means no modal needed
		}
	},
	computed: {
		...mapState(['rows']),
		...mapGetters(['activeTable']),
		getEditRow(id) {
			return this.$store.dispatch('getRow', { id })
		},
	},
}
</script>
