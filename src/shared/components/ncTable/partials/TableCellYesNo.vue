<template>
	<div>
		<RadioboxBlankIcon v-if="value !== null && value === 'false'" />
		<CheckCircleOutline v-if="value !== null && value === 'true'" />
	</div>
</template>

<script>
import RadioboxBlankIcon from 'vue-material-design-icons/RadioboxBlank.vue'
import CheckCircleOutline from 'vue-material-design-icons/CheckCircleOutline.vue'

export default {
	name: 'TableCellYesNo',
	components: {
		RadioboxBlankIcon,
		CheckCircleOutline,
	},
	props: {
		column: {
			type: Object,
			default: () => {},
		},
		rowId: {
			type: Number,
			default: null,
		},
		value: {
			type: String,
			default: 'false',
		},
	},
}
</script>

<style scoped>

.wrapper {
	padding-right: 10px;
}

</style>
