<template>
	<div style="width: 100%">
		<!-- default -->
		<div class="row space-T">
			<div class="fix-col-4">
				{{ t('tables', 'Default') }}
			</div>
			<div class="fix-col-2">
				<input v-model="defaultNum"
					type="number"
					min="0"
					max="5">
			</div>
		</div>
	</div>
</template>

<script>

export default {
	name: 'NumberStarsForm',

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
