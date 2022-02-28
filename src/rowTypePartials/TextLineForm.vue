<template>
	<div class="row">
		<div class="fix-col-1" :class="{ mandatory: column.mandatory }">
			<div class="row">
				<div class="fix-col-4">
					{{ column.title }}
				</div>
				<div v-if="column.textMaxLength === -1 || !column.textMaxLength" class="fix-col-4 p span" style="padding-bottom: 0; padding-top: 0;">
					{{ t('tables', 'length: {length}', { length }) }}
				</div>
				<div v-if="column.textMaxLength !== -1" class="fix-col-4 p span" style="padding-bottom: 0; padding-top: 0;">
					{{ t('tables', 'length: {length} / {maxLength}', { length, maxLength: column.textMaxLength }) }}
				</div>
			</div>
		</div>
		<div class="fix-col-3" :class="{ 'margin-bottom': !column.description }">
			<input v-model="localValue" :maxlength="column.textMaxLength">
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
		length() {
			return (this.localValue) ? this.localValue.length : 0
		},
	},
	beforeMount() {
		if (this.localValue === null) {
			this.localValue = this.column.textDefault
		}
	},
}
</script>
