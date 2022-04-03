<template>
	<div class="row">
		<div class="fix-col-4 title">
			{{ t('tables', 'Set now as default') }}
		</div>
		<div class="fix-col-4">
			<CheckboxRadioSwitch type="switch" :checked.sync="localDefault" />
		</div>
	</div>
</template>

<script>
import CheckboxRadioSwitch from '@nextcloud/vue/dist/Components/CheckboxRadioSwitch'

export default {
	name: 'DatetimeTimeForm',
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
			get() { return this.datetimeDefault === 'now' },
			set(def) { this.$emit('update:datetimeDefault', def ? 'now' : '') },
		},
	},
}
</script>
