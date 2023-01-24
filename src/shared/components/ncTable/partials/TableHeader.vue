<template>
	<tr>
		<th><NcCheckboxRadioSwitch :checked="allRowsAreSelected" @update:checked="value => $emit('select-all-rows', value)" /> </th>
		<th v-for="col in columns" :key="col.id">
			{{ col.title }}
		</th>
		<th>
			<NcActions>
				<NcActionButton :close-after-click="true"
					icon="icon-add"
					@click="$emit('create-row')">
					{{ t('tables', 'Create row') }}
				</NcActionButton>
				<NcActionSeparator />
				<NcActionButton :close-after-click="true" @click="$emit('create-column')">
					<template #icon>
						<TableColumnPlusAfter :size="20" decorative title="" />
					</template>
					{{ t('tables', 'Create column') }}
				</NcActionButton>
				<NcActionButton :close-after-click="true" @click="$emit('edit-columns')">
					<template #icon>
						<TableEdit :size="20" decorative title="" />
					</template>
					{{ t('tables', 'Edit columns') }}
				</NcActionButton>
				<NcActionSeparator />
				<NcActionButton :close-after-click="true"
					icon="icon-share"
					@click="toggleShare">
					{{ t('tables', 'Share') }}
				</NcActionButton>
				<NcActionButton :close-after-click="true"
					icon="icon-download"
					@click="downloadCSV">
					{{ t('tables', 'Export as CSV') }}
				</NcActionButton>
			</NcActions>
		</th>
	</tr>
</template>

<script>
import { NcCheckboxRadioSwitch, NcActions, NcActionButton, NcActionSeparator } from '@nextcloud/vue'
import { emit } from '@nextcloud/event-bus'
import TableEdit from 'vue-material-design-icons/TableEdit.vue'
import TableColumnPlusAfter from 'vue-material-design-icons/TableColumnPlusAfter.vue'

export default {
	name: 'TableHeader',
	components: {
		NcCheckboxRadioSwitch,
		NcActions,
		NcActionButton,
		NcActionSeparator,
		TableEdit,
		TableColumnPlusAfter,
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
			if (Array.isArray(this.rows) && Array.isArray(this.selectedRows) && this.rows.length !== 0) {
				return this.rows.length === this.selectedRows.length
			} else {
				return false
			}
		},
	},
	methods: {
		downloadCSV() {
			this.$emit('download-csv', this.rows)
		},
		toggleShare() {
			emit('toggle-sidebar', { open: true, tab: 'sharing' })
		},
	},
}
</script>

<style scoped>

</style>
