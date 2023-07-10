<template>
	<div style="width: 100%">
		<!-- default -->
		<div class="row space-T">
			<div class="fix-col-4 title">
				{{ t('tables', 'Set today as default') }}
			</div>
			<div class="fix-col-4 margin-bottom">
				<NcCheckboxRadioSwitch type="switch" :checked.sync="mutableColumn.datetimeDefault" />
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
		column: {
			type: Object,
			default: null,
		},
	},
	data() {
		return {
			mutableColumn: this.column,
		}
	},
	watch: {
		column() {
			this.mutableColumn = this.column
		},
	},
}
</script>
