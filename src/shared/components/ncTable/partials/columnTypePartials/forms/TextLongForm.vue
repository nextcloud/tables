<template>
	<div style="width: 100%">
		<!-- default -->
		<div class="row">
			<div class="fix-col-4">
				{{ t('tables', 'Default') }}
			</div>
			<div class="fix-col-4 space-B">
				<textarea v-model="defaultText" />
			</div>
		</div>

		<!-- max text length -->
		<div class="row">
			<div class="fix-col-4">
				{{ t('tables', 'Maximum text length') }}
			</div>
			<div class="fix-col-4">
				<input v-model="maxLength"
					type="number"
					step="1"
					min="0">
			</div>
		</div>
	</div>
</template>

<script>

export default {
	name: 'TextLongForm',
	props: {
		textDefault: {
			type: String,
			default: '',
		},
		textMaxLength: {
			type: Number,
			default: null,
		},
	},
	computed: {
		defaultText: {
			get() { return this.textDefault },
			set(defaultText) { this.$emit('update:textDefault', defaultText) },
		},
		maxLength: {
			get() { return (this.textMaxLength && this.textMaxLength === -1) ? '' : this.textMaxLength },
			set(maxLength) { this.$emit('update:textMaxLength', parseFloat(maxLength)) },
		},
	},
}
</script>
