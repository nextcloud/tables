<template>
	<div>
		<ul>
			<li v-for="v in getObjects" :key="v.id">
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
			JSON.parse(this.column?.selectionDefault)?.forEach(id => {
				defaultObjects.push(this.getOptionObject(parseInt(id)))
			})
			return defaultObjects
		},
		getObjects() {
			if (this.value === null) {
				return this.getDefaultObjects
			}
			const objects = []
			this.value?.forEach(id => {
				objects.push(this.getOptionObject(parseInt(id)))
			})
			return objects
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
