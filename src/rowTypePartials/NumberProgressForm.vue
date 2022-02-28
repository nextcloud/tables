<template>
	<div class="row">
		<div class="fix-col-2" :class="{ mandatory: column.mandatory }">
			{{ column.title }}
		</div>
		<div class="fix-col-2" :class="{ 'margin-bottom': !column.description }">
			<input v-model="localValue"
				type="number"
				:min="column.numberMin"
				:max="column.numberMax">
		</div>
		<div v-if="column.description" class="fix-col-2 hide-s">
&nbsp;
		</div>
		<div v-if="column.description" class="fix-col-2 p span margin-bottom">
			{{ column.description }}
		</div>
	</div>
</template>

<script>

export default {
	name: 'NumberProgressForm',
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
				return (this.value && true)
					? this.value
					: ((this.column.numberDefault !== undefined)
						? this.column.numberDefault
						: '')
			},
			set(v) { this.$emit('update:value', v) },
		},
	},
}
</script>
