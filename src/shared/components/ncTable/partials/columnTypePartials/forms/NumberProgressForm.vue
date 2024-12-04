<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div style="width: 100%">
		<!-- default -->
		<div class="row space-T">
			<div class="fix-col-4">
				{{ t('tables', 'Default') }}
			</div>
			<div class="fix-col-2">
				<input v-model="mutableColumn.numberDefault"
					type="number"
					min="0"
					max="100"
					@input="enforceBounds">
			</div>
		</div>
	</div>
</template>

<script>

import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'NumberProgressForm',
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
		enforceBounds(event) {
			const value = parseInt(event.target.value)
			if (isNaN(value)) {
				this.mutableColumn.numberDefault = null
				return
			}
			this.mutableColumn.numberDefault = Math.min(Math.max(0, value), 100)
		},
	},
}
</script>
