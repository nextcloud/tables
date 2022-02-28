<template>
	<div class="row">
		<div class="fix-col-1" :class="{ mandatory: column.mandatory }">
			{{ column.title }}
		</div>
		<div class="fix-col-3" :class="{ 'margin-bottom': !column.description }">
			<input v-model="localValue">
		</div>
		<div v-if="column.description" class="fix-col-1 hide-s">
&nbsp;
		</div>
		<div v-if="column.description" class="fix-col-3 p span margin-bottom">
			{{ column.description }}
		</div>
	</div>
</template>

<script>

export default {
	name: 'TextLineForm',
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
