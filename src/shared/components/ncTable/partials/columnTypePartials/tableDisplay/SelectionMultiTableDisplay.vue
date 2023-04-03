<template>
	<table v-if="column" style="width: 100%;">
		<tr>
			<td>{{ t('tables', 'Defaults') }}</td>
			<td>
				<ul>
					<li v-for="value in getDefaultObjects" :key="value.id">
						{{ value.label }}
					</li>
				</ul>
			</td>
		</tr>
		<tr>
			<td>{{ t('tables', 'Options') }}</td>
			<td className="align-right">
				{{ getOptionsCount }}
			</td>
		</tr>
	</table>
</template>

<script>

export default {
	name: 'SelectionMultiTableDisplay',
	props: {
		column: {
			type: Object,
			default: null,
		},
	},
	computed: {
		getDefaultIds() {
			const ids = []
			if (this.column?.selectionDefault === null || this.column?.selectionDefault === '') {
				return ids
			}
			JSON.parse(this.column?.selectionDefault).forEach(def => {
				ids.push(parseInt(def))
			})
			return ids
		},
		getDefaultObjects() {
			const defaultObjects = []
			this.getDefaultIds.forEach(id => {
				const o = this.getOptionObject(id)
				if (o) {
					defaultObjects.push(o)
				}
			})
			return defaultObjects
		},
		getOptionsCount() {
			return this.column?.selectionOptions?.length || 0
		},
	},
	methods: {
		getOptionObject(id) {
			const i = this.column?.selectionOptions?.findIndex(obj => {
				return obj.id === id
			})
			console.debug('index is ', i)
			if (i != undefined) {
				return this.column?.selectionOptions[i]
			}

		},
	},
}
</script>
<style scoped>

	table td {
		padding-right: 10px;
	}

   ul {
	   list-style-type: square;
	   padding-left: calc(var(--default-grid-baseline) * 3);
   }

   td {
	   vertical-align: top;
   }

</style>
