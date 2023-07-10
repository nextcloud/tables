<template>
	<div class="row space-T">
		<div class="fix-col-4 title">
			{{ t('tables', 'Set now as default') }}
		</div>
		<div class="fix-col-4 space-L-small">
			<NcCheckboxRadioSwitch type="switch" :checked.sync="mutableColumn.datetimeDefault" />
		</div>
	</div>
</template>

<script>
import { NcCheckboxRadioSwitch } from '@nextcloud/vue'

export default {
	name: 'DatetimeTimeForm',
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
