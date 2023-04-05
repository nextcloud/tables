<template>
	<RowFormWrapper :title="column.title" :mandatory="column.mandatory" :description="column.description">
		<button :aria-label="t('tables', 'reduce stars')" @click="less">
			-
		</button>
		<div style="font-size: 1.4em; padding: 7px;">
			{{ getStars }}
		</div>
		<button :aria-label="t('tables', 'increase stars')" @click="more">
			+
		</button>
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
			set(v) { this.$emit('update:value', v) },
		},
		getStars() {
			return '★'.repeat(this.localValue) + '☆'.repeat(5 - this.localValue)
		},
	},
	methods: {
		more() {
			if (this.localValue < 5) {
				this.localValue++
			}
		},
		less() {
			if (this.localValue > 0) {
				this.localValue--
			}
		},
	},
}
</script>
