<template>
	<div style="width: 100%">
		<!-- default -->
		<div class="row space-T">
			<div class="fix-col-4 title">
				{{ t('tables', 'Set today as default') }}
			</div>
			<div class="fix-col-4 margin-bottom">
				<NcCheckboxRadioSwitch type="switch" :checked.sync="localDefault" />
			</div>
		</div>
	</div>
</template>

<script>
import { NcCheckboxRadioSwitch } from '@nextcloud/vue'

export default {
	name: 'DatetimeDateForm',
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
			get() { return this.datetimeDefault === 'today' },
			set(def) { this.$emit('update:datetimeDefault', def ? 'today' : '') },
		},
	},
}
</script>
