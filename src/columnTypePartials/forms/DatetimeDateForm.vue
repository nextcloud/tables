<template>
	<div style="width: 100%">
		<!-- default -->
		<div class="row">
			<div class="fix-col-4 title">
				{{ t('tables', 'Set today as default') }}
			</div>
			<div class="fix-col-4 margin-bottom">
				<CheckboxRadioSwitch type="switch" :checked.sync="localDefault" />
			</div>
		</div>
	</div>
</template>

<script>
import CheckboxRadioSwitch from '@nextcloud/vue/dist/Components/CheckboxRadioSwitch'

export default {
	name: 'DatetimeDateForm',
	components: {
		CheckboxRadioSwitch,
	},
	props: {
		datetimeDefault: {
			type: String,
			default: 'false',
		},
	},
	computed: {
		localDefault: {
			get() { return this.datetimeDefault === 'today' },
			set(def) { this.$emit('update:datetimeDefault', def ? 'today' : '') },
		},
	},
}
</script>
