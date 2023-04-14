<template>
	<div>
		<div class="row space-T">
			<div class="col-2 space-R" style="display: block">
				<div class="fix-col-4 title">
					{{ t('tables', 'Default value') }}
				</div>
				<div class="fix-col-4">
					<input v-model="defaultNum" type="number">
				</div>
			</div>
			<div class="col-2 space-R" style="display: block">
				<!-- decimals -->
				<div class="fix-col-4 title">
					{{ t('tables', 'Decimals') }}
				</div>
				<div class="fix-col-4">
					<input v-model="decimals" type="number">
				</div>
			</div>
		</div>

		<div class="row space-T">
			<div class="col-2 space-R" style="display: block">
				<!-- min -->
				<div class="fix-col-4 title">
					{{ t('tables', 'Minimum') }}
				</div>
				<div class="fix-col-4">
					<input v-model="min" type="number">
				</div>
			</div>
			<div class="col-2 space-R" style="display: block">
				<!-- max -->
				<div class="fix-col-4 title">
					{{ t('tables', 'Maximum') }}
				</div>
				<div class="fix-col-4">
					<input v-model="max" type="number">
				</div>
			</div>
		</div>

		<div class="row space-T">
			<div class="col-2 space-R" style="display: block">
				<!-- prefix -->
				<div class="fix-col-4 title">
					{{ t('tables', 'Prefix') }}
				</div>
				<div class="fix-col-4 space-B">
					<input v-model="localPrefix">
				</div>
			</div>
			<div class="col-2 space-R" style="display: block">
				<!-- suffix -->
				<div class="fix-col-4 title" style="display: flex;">
					{{ t('tables', 'Suffix') }}
				</div>
				<div class="fix-col-4">
					<input v-model="localSuffix">
				</div>
			</div>
		</div>
	</div>
</template>

<script>

export default {
	name: 'NumberForm',
	props: {
		numberDefault: {
			type: Number,
			default: null,
		},
		numberMin: {
			type: Number,
			default: null,
		},
		numberMax: {
			type: Number,
			default: null,
		},
		numberDecimals: {
			type: Number,
			default: null,
		},
		numberPrefix: {
			type: String,
			default: null,
		},
		numberSuffix: {
			type: String,
			default: null,
		},
	},
	computed: {
		defaultNum: {
			get() { return this.numberDefault },
			set(defaultNum) { this.$emit('update:numberDefault', parseFloat(defaultNum)) },
		},
		min: {
			get() { return this.numberMin },
			set(min) { this.$emit('update:numberMin', parseFloat(min)) },
		},
		max: {
			get() { return this.numberMax },
			set(max) { this.$emit('update:numberMax', parseFloat(max)) },
		},
		decimals: {
			get() { return this.numberDecimals },
			set(decimals) { this.$emit('update:numberDecimals', parseFloat(decimals)) },
		},
		localPrefix: {
			get() { return this.numberPrefix },
			set(prefix) { this.$emit('update:numberPrefix', prefix) },
		},
		localSuffix: {
			get() { return this.numberSuffix },
			set(suffix) { this.$emit('update:numberSuffix', suffix) },
		},
	},
}
</script>
