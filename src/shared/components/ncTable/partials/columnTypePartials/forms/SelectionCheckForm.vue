<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div style="width: 100%">
		<!-- options -->
		<div class="row space-T">
			<div class="fix-col-4">
				{{ t('tables', 'Default') }}
			</div>
			<div class="fix-col-4 space-L-small">
				<NcCheckboxRadioSwitch type="switch" :checked.sync="mutableColumn.selectionDefault" data-cy="selectionCheckFormDefaultSwitch" />
			</div>
		</div>
	</div>
</template>

<script>
import { NcCheckboxRadioSwitch } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'SelectionCheckForm',
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
		}
	},
	watch: {
		column() {
			this.mutableColumn = this.column
		},
	},
	created() {
		if (this.mutableColumn.selectionDefault === 'true') {
			this.mutableColumn.selectionDefault = true
			return
		}
		if (typeof this.mutableColumn.selectionDefault !== 'boolean') {
			this.mutableColumn.selectionDefault = false
		}
	},
	methods: {
		t,
	},
}
</script>
