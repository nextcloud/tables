<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div style="width: 100%">
		<!-- default -->
		<div class="row">
			<div class="fix-col-4">
				{{ t('tables', 'Default') }}
			</div>
			<div class="fix-col-4 space-B">
				<NcEditor :text.sync="mutableColumn.textDefault" height="small" />
			</div>
		</div>
	</div>
</template>

<script>
import NcEditor from '../../../../ncEditor/NcEditor.vue'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'TextRichForm',
	components: {
		NcEditor,
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
