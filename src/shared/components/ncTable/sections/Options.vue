<template>
	<div class="options">
		<div v-if="showOptions" class="fix-col-4" style="justify-content: space-between;">
			<div>
				<NcButton v-if="!isSmallMobile"
					:close-after-click="true"
					type="tertiary"
					@click="$emit('create-row')">
					{{ t('tables', 'Create row') }}
					<template #icon>
						<Plus :size="25" />
					</template>
				</NcButton>
				<NcButton v-if="isSmallMobile"
					:close-after-click="true"
					type="tertiary"
					@click="$emit('create-row')">
					<template #icon>
						<Plus :size="25" />
					</template>
				</NcButton>
			</div>

			<div v-if="selectedRows.length > 0" class="selected-rows-option">
				<div style="padding: 10px; color: var(--color-text-maxcontrast);">
					{{ n('tables', '%n selected row', '%n selected rows', selectedRows.length, {}) }}
				</div>
      &nbsp;&nbsp;
				<NcButton v-if="!isSmallMobile"
					icon="icon-download"
					:title="t('tables', 'Export as CSV')"
					@click="exportCsv">
					<template #icon>
						<Export :size="20" />
					</template>
					{{ t('tables', 'Export CSV') }}
				</NcButton>
				<NcButton v-if="isSmallMobile"
					icon="icon-download"
					:title="t('tables', 'Export as CSV')"
					@click="exportCsv">
					<template #icon>
						<Export :size="20" />
					</template>
				</NcButton>
      &nbsp;&nbsp;
				<NcButton v-if="!isSmallMobile"
					icon="icon-delete"
					:title="t('tables', 'Delete')"
					@click="deleteSelectedRows">
					{{ t('tables', 'Delete') }}
					<template #icon>
						<Delete :size="20" />
					</template>
				</NcButton>
				<NcButton v-if="isSmallMobile"
					icon="icon-delete"
					:title="t('tables', 'Delete')"
					@click="deleteSelectedRows">
					<template #icon>
						<Delete :size="20" />
					</template>
				</NcButton>
			</div>
		</div>
	</div>
</template>

<script>
import { NcButton } from '@nextcloud/vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import Export from 'vue-material-design-icons/Export.vue'
import viewportHelper from '../../../mixins/viewportHelper.js'

export default {
	name: 'Options',
	components: {
		NcButton,
		Plus,
		Delete,
		Export,
	},
	mixins: [viewportHelper],
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

<style scoped lang="scss">

.sticky {
  position: -webkit-sticky; /* Safari */
  position: sticky;
  top: 90px;
  left: 0;
}

.selected-rows-option {
  justify-content: flex-end;
  display: inline-flex;
}

</style>
