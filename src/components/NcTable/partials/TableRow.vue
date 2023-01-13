<template>
	<tr v-if="row">
		<td><NcCheckboxRadioSwitch /></td>
		<td v-for="col in columns" :key="col.id">
			<TableCell :value="getCellValue(col.id)"
				:row-id="row.id"
				:column-id="col.id" />
		</td>
		<td>
			<NcButton type="tertiary">
				<template #icon>
					<Pencil :size="20" />
				</template>
			</NcButton>
		</td>
	</tr>
</template>

<script>
import { NcCheckboxRadioSwitch, NcButton } from '@nextcloud/vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import TableCell from './TableCell.vue'

export default {
	name: 'TableRow',
	components: {
		TableCell,
		NcButton,
		Pencil,
		NcCheckboxRadioSwitch,
	},
	props: {
		row: {
			type: Object,
			default: () => {},
		},
		columns: {
			type: Array,
			default: () => [],
		},
	},
	methods: {
		getCellValue(columnId) {
			if (!this.row) {
				return null
			}

			const cell = this.row.data.find(item => item.columnId === columnId)
			return cell ? '' + cell.value : null
		},
	},
}
</script>

<style scoped>

</style>
