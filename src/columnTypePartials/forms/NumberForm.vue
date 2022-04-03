<template>
	<div>
		<div class="fix-col-4 title">
			{{ t('tables', 'Default') }}
		</div>
		<div class="fix-col-4 space-B">
			<input v-model="defaultNum" type="number">
		</div>

		<!-- decimals -->
		<div class="fix-col-4 title">
			{{ t('tables', 'Decimals') }}
		</div>
		<div class="fix-col-4 space-B">
			<input v-model="decimals" type="number">
		</div>

		<!-- min -->
		<div class="fix-col-4 title">
			{{ t('tables', 'Minimum') }}
		</div>
		<div class="fix-col-4 space-B">
			<input v-model="min" type="number">
		</div>

		<!-- max -->
		<div class="fix-col-4 title">
			{{ t('tables', 'Maximum') }}
		</div>
		<div class="fix-col-4 space-B">
			<input v-model="max" type="number">
		</div>

		<!-- prefix -->
		<div class="fix-col-4 title">
			{{ t('tables', 'Prefix') }}
		</div>
		<div class="fix-col-4 space-B">
			<input v-model="localPrefix">
		</div>

		<!-- suffix -->
		<div class="fix-col-4 title" style="display: flex;">
			{{ t('tables', 'Suffix') }}
			<Popover>
				<template #trigger>
					<button class="icon-details" />
				</template>
				<p>
					{{ t('tables', 'Here is a good place for a unit.') }}
				</p>
			</Popover>
		</div>
		<div class="fix-col-4">
			<input v-model="localSuffix">
		</div>
	</div>
</template>

<script>
// import { showError, showInfo, showSuccess } from '@nextcloud/dialogs'
import Popover from '@nextcloud/vue/dist/Components/Popover'

export default {
	name: 'NumberForm',
	components: {
		Popover,
	},
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
