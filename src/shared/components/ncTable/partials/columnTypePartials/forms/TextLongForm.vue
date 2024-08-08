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
				<textarea v-model="mutableColumn.textDefault" />
			</div>
		</div>

		<!-- max text length -->
		<div class="row">
			<div class="fix-col-4">
				{{ t('tables', 'Maximum text length') }}
			</div>
			<div class="fix-col-4">
				<input v-model="mutableColumn.textMaxLength"
					type="number"
					step="1"
					min="0">
			</div>
		</div>
	</div>
</template>

<script>

import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'TextLongForm',
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
