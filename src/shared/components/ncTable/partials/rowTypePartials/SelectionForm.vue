<template>
	<RowFormWrapper :title="column.title" :mandatory="column.mandatory" :description="column.description">
		<NcMultiselect v-model="localValue" :options="getAllNonDeletedOptions" track-by="id" label="label" :close-on-select="true" />
	</RowFormWrapper>
</template>

<script>
import { NcMultiselect } from '@nextcloud/vue'
import RowFormWrapper from './RowFormWrapper.vue'

export default {
	name: 'SelectionForm',
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
			type: Number,
			default: null,
		},
	},
	computed: {
		localValue: {
			get() {
				if (this.value !== null) {
					return this.getOptionObject(parseInt(this.value))
				} else {
					this.$emit('update:value', this.getDefaultId)
					return this.getDefaultOptionObject
				}
			},
			set(v) { this.$emit('update:value', v.id) },
		},
		getOptions() {
			return this.column?.selectionOptions || null
		},
		getDefaultId() {
			return parseInt(this.column.selectionDefault) || null
		},
		getDefaultOptionObject() {
			return this.getOptionObject(this.getDefaultId) || null
		},
		getAllNonDeletedOptions() {
			return this.getOptions.filter(item => {
				return !item.deleted
			})
		},
	},
	methods: {
		getOptionObject(id) {
			return this.getOptions.find(e => e.id === id) || null
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

</style>
