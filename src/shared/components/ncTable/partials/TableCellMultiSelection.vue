<template>
	<div>
		<ul>
			<li v-for="v in getDefaultObjects" :key="v.id">
				{{ v.label }}
			</li>
		</ul>
	</div>
</template>

<script>

export default {
	name: 'TableCellMultiSelection',

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
			type: Array,
			default: null,
		},
	},
	computed: {
		getDefaultObjects() {
			const defaultObjects = []
			this.value.forEach(id => {
				defaultObjects.push(this.getOptionObject(parseInt(id)))
			})
			return defaultObjects
		},
	},
	methods: {
		getOptionObject(id) {
			const i = this.column?.selectionOptions?.findIndex(obj => {
				return obj.id === id
			})
			if (i !== undefined) {
				return this.column?.selectionOptions[i] || null
			}
		},
	},
}
</script>
<style lang="scss" scoped>

ul {
	list-style-type: square;
	padding-left: calc(var(--default-grid-baseline) * 3);
}

</style>
