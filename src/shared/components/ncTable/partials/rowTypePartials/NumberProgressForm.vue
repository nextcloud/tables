<template>
	<RowFormWrapper :title="column.title" :mandatory="column.mandatory" :description="column.description" :width="2">
		<input v-model="localValue"
			type="number"
			:min="column.numberMin"
			:max="column.numberMax">
	</RowFormWrapper>
</template>

<script>
import RowFormWrapper from './RowFormWrapper.vue'

export default {
	components: {
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
	data() {
		return {
		}
	},
	computed: {
		localValue: {
			get() {
				if (this.value !== null) {
					return this.value
				} else {
					if (this.column.numberDefault !== undefined) {
						this.$emit('update:value', this.column.numberDefault)
						return this.column.numberDefault
					} else {
						return null
					}
				}
			},
			set(v) { this.$emit('update:value', parseInt(v)) },
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
