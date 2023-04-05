<template>
	<RowFormWrapper :width="2" :title="column.title" :mandatory="column.mandatory" :description="column.description">
		<div class="prefix">
			{{ column.numberPrefix }}
		</div>
		<input v-model="localValue"
			type="number"
			:min="column.numberMin"
			:max="column.numberMax"
			:step="column.numberDecimals === 0 ? '' : 'any'">
		<div class="suffix">
			{{ column.numberSuffix }}
		</div>
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
				if (this.value) {
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
			set(v) { this.$emit('update:value', parseFloat(v)) },
		},
	},
}
</script>
<style lang="scss" scoped>

.prefix {
	padding-right: calc(var(--default-grid-baseline) * 2);
}

.suffix {
	padding-left: calc(var(--default-grid-baseline) * 2);
}

</style>
