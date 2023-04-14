<template>
	<div class="row space-T">
		<div class="fix-col-4 title">
			{{ t('tables', 'Set now as default') }}
		</div>
		<div class="fix-col-4 space-L-small">
			<NcCheckboxRadioSwitch type="switch" :checked.sync="localDefault" />
		</div>
	</div>
</template>

<script>
import { NcCheckboxRadioSwitch } from '@nextcloud/vue'

export default {
	name: 'DatetimeForm',
	components: {
		NcCheckboxRadioSwitch,
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
