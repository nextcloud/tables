<template>
	<div class="space-LR-small sticky">
		<NcActions>
			<NcActionButton v-if="showOptions"
				:close-after-click="true"
				icon="icon-add"
				@click="$emit('create-row')">
				{{ t('tables', 'Create row') }}
			</NcActionButton>
		</NcActions>
		<NcActions v-if="selectedRows.length > 0"
			type="secondary"
			:menu-title="t('tables', 'Actions')"
			:force-menu="true">
			<NcActionCaption :title="t('tables', 'Selected rows')" />
			<NcActionButton :close-after-click="true"
				icon="icon-download"
				@click="exportCsv">
				{{ t('tables', 'Export as CSV') }}
			</NcActionButton>
			<NcActionButton :close-after-click="true"
				icon="icon-delete"
				@click="deleteSelectedRows">
				{{ t('tables', 'Delete selected rows') }}
			</NcActionButton>
		</NcActions>
	</div>
</template>

<script>
import { NcActions, NcActionButton, NcActionCaption } from '@nextcloud/vue'

export default {
	name: 'Options',
	components: {
		NcActions,
		NcActionButton,
		NcActionCaption,
	},
	props: {
		selectedRows: {
			type: Array,
			default: () => [],
		},
		rows: {
			type: Array,
			default: () => [],
		},
		showOptions: {
			type: Boolean,
			default: true,
		},
	},
	computed: {
		getSelectedRows() {
			const rows = []
			this.selectedRows.forEach(id => {
				rows.push(this.getRowById(id))
			})
			return rows
		},
	},
	methods: {
		exportCsv() {
			console.debug('export csv by selected rows', this.selectedRows)
			console.debug('selected rows', this.getSelectedRows)
			this.$emit('download-csv', this.getSelectedRows)
		},
		getRowById(rowId) {
			const index = this.rows.findIndex(row => row.id === rowId)
			return this.rows[index]
		},
		deleteSelectedRows() {
			this.$emit('delete-selected-rows', this.selectedRows)
		},
	},
}
</script>

<style scoped>

.sticky {
  position: -webkit-sticky; /* Safari */
  position: sticky;
  top: 10px;
}

</style>
