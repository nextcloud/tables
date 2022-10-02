<template>
	<div style="width: 100%">
		<!-- options -->
		<div class="row">
			<div class="fix-col-1">
				{{ t('tables', 'Default') }}
			</div>
			<div class="fix-col-1">
				&nbsp;
			</div>
			<div class="fix-col-2 margin-bottom">
				<NcCheckboxRadioSwitch type="switch" :checked.sync="localSelectionDefault" />
			</div>
		</div>
	</div>
</template>

<script>
import { NcCheckboxRadioSwitch } from '@nextcloud/vue'

export default {
	name: 'SelectionCheckForm',
	components: {
		NcCheckboxRadioSwitch,
	},
	props: {
		selectionDefault: {
			type: String,
			default: '',
		},
	},
	computed: {
		localSelectionDefault: {
			get() { return (this.selectionDefault === 'true' || this.selectionDefault === true) },
			set(defaultValue) { this.$emit('update:selectionDefault', '' + defaultValue) },
		},
	},
}
</script>
