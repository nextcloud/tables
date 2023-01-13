<template>
	<tr>
		<th><NcCheckboxRadioSwitch /> </th>
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
	},
}
</script>

<style scoped>

</style>
