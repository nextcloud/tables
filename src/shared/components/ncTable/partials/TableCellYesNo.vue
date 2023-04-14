<template>
	<div>
		<RadioboxBlankIcon v-if="!value" />
		<CheckCircleOutline v-if="value" />
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
			type: Boolean,
			default: false,
		},
	},
}
</script>

<style scoped>

.wrapper {
	padding-right: 10px;
}

</style>
