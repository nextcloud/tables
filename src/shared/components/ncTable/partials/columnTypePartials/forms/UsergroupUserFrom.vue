<template>
	<div style="width: 100%">
		<!-- default -->
		<div class="row space-T">
			<div class="fix-col-4 title">
				{{ t('tables', 'Show user status') }}
			</div>
			<div class="fix-col-4 margin-bottom">
				<NcCheckboxRadioSwitch type="switch" :checked.sync="userStatus" data-cy="usergroupUserFormStatusSwitch" />
			</div>
		</div>
	</div>
</template>

<script>
import { NcCheckboxRadioSwitch } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'

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
		canSave: {
			type: Boolean,
			default: true,
		},
	},
	data() {
		return {
			mutableColumn: this.column,
			userStatus: this.column.datetimeDefault === 'today',
		}
	},
	watch: {
		column() {
			this.mutableColumn = this.column
			if (this.column.datetimeDefault === 'today') {
				this.todayAsDefault = true
			}
		},
		todayAsDefault() {
			if (this.todayAsDefault) {
				this.mutableColumn.datetimeDefault = 'today'
			} else {
				this.mutableColumn.datetimeDefault = ''
			}
		},
	},
	methods: {
		t,
	},
}
</script>
