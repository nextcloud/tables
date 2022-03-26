<template>
	<div class="row">
		<div class="fix-col-1" :class="{ mandatory: column.mandatory }">
			{{ column.title }}
		</div>
		<div class="fix-col-2" :class="{ 'margin-bottom': !column.description }">
			<input v-model="localValue">
		</div>
		<div class="fix-col-1 hide-s">
			&nbsp;
		</div>
		<div v-if="column.description" class="fix-col-1 p span margin-bottom">
			<div class="hint-padding-left">
				{{ column.description }}
			</div>
		</div>
		<div v-if="!column.description" class="fix-col-1 p span margin-bottom hide-s">
			&nbsp;
		</div>
	</div>
</template>

<script>

export default {
	name: 'TextLinkForm',
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
