<template>
	<div class="row">
		<div class="fix-col-1" :class="{ mandatory: column.mandatory }">
			{{ column.title }}
		</div>
		<div class="fix-col-1" :class="{ 'space-B': !column.description }" />
		<div class="fix-col-1 hide-s">
			&nbsp;<NcMultiselect v-model="localValue" :options="column.selectionOptions" track-by="id" label="label" :close-on-select="true" />
		</div>
		<div v-if="column.description" class="fix-col-1 p span space-B">
			<div class="space-L-small">
				{{ column.description }}
			</div>
		</div>
		<div v-if="!column.description" class="fix-col-1 p span space-B hide-s">
			&nbsp;
		</div>
	</div>
</template>

<script>
import { NcMultiselect } from '@nextcloud/vue'

export default {
	name: 'SelectionForm',
	components: {
		NcMultiselect,
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
	data() {
		return {
			localSelectedValue: null,
		}
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
