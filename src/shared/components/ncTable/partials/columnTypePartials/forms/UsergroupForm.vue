<template>
	<div style="width: 100%">
		<!-- default -->
		<div class="row">
			<div class="fix-col-4">
				{{ t('tables', 'Default') }}
			</div>
			<div class="fix-col-4 space-B">
				<NcUserAndGroupPicker :select-users="true" :select-groups="false" />
			</div>
		</div>
	</div>
</template>

<script>
import NcUserAndGroupPicker from '../../../../ncUserAndGroupPicker/NcUserAndGroupPicker.vue'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'UsergroupForm',
	components: {
		NcUserAndGroupPicker,
	},
	props: {
		column: {
			type: Object,
			default: null,
		},
		canSave: {
			type: Boolean,
			default: true,
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
	methods: {
		t,
	},

}
</script>
