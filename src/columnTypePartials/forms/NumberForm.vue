<template>
	<div>
		<!-- default -->
		<div class="row">
			<div class="fix-col-1">
				{{ t('tables', 'Default') }}
			</div>
			<div class="fix-col-1">
      &nbsp;
			</div>
			<div class="fix-col-2 margin-bottom">
				<input v-model="defaultNum" type="number">
			</div>
		</div>

		<!-- decimals -->
		<div class="row">
			<div class="fix-col-1">
				{{ t('tables', 'Decimals') }}
			</div>
			<div class="fix-col-1">
      &nbsp;
			</div>
			<div class="fix-col-2 margin-bottom">
				<input v-model="decimals" type="number">
			</div>
		</div>

		<!-- min -->
		<div class="row">
			<div class="fix-col-1">
				{{ t('tables', 'Minimum') }}
			</div>
			<div class="fix-col-1">
      &nbsp;
			</div>
			<div class="fix-col-2 margin-bottom">
				<input v-model="min" type="number">
			</div>
		</div>

		<!-- max -->
		<div class="row">
			<div class="fix-col-1">
				{{ t('tables', 'Maximum') }}
			</div>
			<div class="fix-col-1">
      &nbsp;
			</div>
			<div class="fix-col-2 margin-bottom">
				<input v-model="max" type="number">
			</div>
		</div>
	</div>
</template>

<script>
// import { showError, showInfo, showSuccess } from '@nextcloud/dialogs'

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
	},
}
</script>
