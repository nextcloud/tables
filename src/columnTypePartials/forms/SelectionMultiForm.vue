<template>
	<div style="width: 100%">
		<!-- options -->
		<div class="row">
			<div class="fix-col-1">
				{{ t('tables', 'Options') }}
			</div>
			<div class="fix-col-3 margin-bottom">
				<textarea v-model="localSelectionOptions" />
				{{ t('tables', 'Put an option name at each line.') }}
			</div>
		</div>
	</div>
</template>

<script>

export default {
	name: 'SelectionMultiForm',
	props: {
		selectionOptions: {
			type: Array,
			default: null,
		},
	},
	computed: {
		localSelectionOptions: {
			get() { return this.selectionOptions },
			set(optionsText) { this.$emit('update:selectionOptions', optionsText) },
		},
	},
}
</script>
