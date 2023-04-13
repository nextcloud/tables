<template>
	<RowFormWrapper :title="column.title" :mandatory="column.mandatory" :description="column.description">
		<NcMultiselect v-model="localValues" :tag-width="80" :options="getAllNonDeletedOrSelectedOptions" track-by="id" label="label" :multiple="true" />
	</RowFormWrapper>
</template>

<script>
import { NcMultiselect } from '@nextcloud/vue'
import RowFormWrapper from './RowFormWrapper.vue'

export default {
	name: 'SelectionMultiForm',
	components: {
		NcMultiselect,
		RowFormWrapper,
	},
	props: {
		column: {
			type: Object,
			default: null,
		},
		value: {
			type: Array,
			default: null,
		},
	},
	computed: {
		localValues: {
			get() {
				if (this.value !== null) {
					return this.getValueObjects
				} else {
					this.$emit('update:value', this.getDefaultIds)
					return this.getDefaultObjects
				}
			},
			set(v) {
				this.$emit('update:value', this.getIdArrayFromObjects(v))
			},
		},
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
		getValueObjects() {
			const objects = []
			this.value?.forEach(id => {
				const o = this.getOptionObject(id)
				if (o) {
					objects.push(o)
				}
			})
			return objects
		},
		getOptions() {
			return this.column.selectionOptions.map(item => Object.assign({}, item)) || null
		},
		getAllNonDeletedOrSelectedOptions() {
			const options = this.getOptions?.filter(item => {
				return !item.deleted || this.optionIdIsSelected(item.id)
			}) || []

			options.forEach(opt => {
				if (opt.deleted) {
					opt.label += ' ⚠️'
				}
			})
			return options
		},
	},
	methods: {
		optionIdIsSelected(id) {
			// check if the given id is selected (in the value array)
			const result = this.getValueObjects.findIndex(item => item.id === id)
			return result !== -1
		},
		getOptionObject(id) {
			const i = this.getOptions?.findIndex(obj => {
				return obj.id === id
			})
			if (i !== -1) {
				return this.getOptions[i]
			}
		},
		getIdArrayFromObjects(objects) {
			const ids = []
			objects.forEach(o => {
				ids.push(o.id)
			})
			return ids
		},
	},
}
</script>
<style scoped>

.hint-padding-left {
	padding-left: 20px;
}

@media only screen and (max-width: 641px) {
	.hint-padding-left {
		padding-left: 0;
	}
}

.multiselect {
	width: 100%;
}

</style>
