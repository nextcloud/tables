<template>
	<RowFormWrapper :title="column.title" :mandatory="column.mandatory" :description="column.description">
		<input v-model="localValue">
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
			type: String,
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
				return this.value
			},
			set(v) { this.$emit('update:value', v) },
		},
	},
	beforeMount() {
		if (this.localValue === null) {
			this.localValue = this.column.textDefault
		}
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
