<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="row space-T">
		<div class="fix-col-4 title">
			{{ t('tables', 'Set now as default') }}
		</div>
		<div class="fix-col-4 space-L-small">
			<NcCheckboxRadioSwitch type="switch" :checked.sync="nowAsDefault" data-cy="datetimeTimeFormNowSwitch" />
		</div>
	</div>
</template>

<script>
import { NcCheckboxRadioSwitch } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'

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
		canSave: {
			type: Boolean,
			default: true,
		},
	},
	data() {
		return {
			mutableColumn: this.column,
			nowAsDefault: this.column.datetimeDefault === 'now',
		}
	},
	watch: {
		column() {
			this.mutableColumn = this.column
			if (this.column.datetimeDefault === 'now') {
				this.nowAsDefault = true
			}
		},
		nowAsDefault() {
			if (this.nowAsDefault) {
				this.mutableColumn.datetimeDefault = 'now'
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
