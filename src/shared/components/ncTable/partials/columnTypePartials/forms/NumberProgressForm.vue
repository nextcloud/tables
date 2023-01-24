<template>
	<div style="width: 100%">
		<!-- default -->
		<div class="row">
			<div class="fix-col-1">
				{{ t('tables', 'Default') }}
			</div>
			<div class="fix-col-1">
      &nbsp;
			</div>
			<div class="fix-col-2 margin-bottom">
				<input v-model="defaultNum"
					type="number"
					min="0"
					max="100">
			</div>
		</div>
	</div>
</template>

<script>

export default {
	name: 'NumberProgressForm',

	props: {
		numberDefault: {
			type: Number,
			default: 0,
		},
	},
	computed: {
		defaultNum: {
			get() { return this.numberDefault },
			set(defaultNum) { this.$emit('update:numberDefault', parseFloat(defaultNum)) },
		},
	},
}
</script>
