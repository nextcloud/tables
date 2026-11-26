<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		<div v-if="lookup.status === 'loading'" class="icon-loading-small" :aria-label="t('tables', 'Loading lookup value')" />
		<span v-else-if="lookup.status === 'unavailable'" :title="t('tables', 'The linked row or lookup column is no longer available.')">
			{{ t('tables', 'Unavailable') }}
		</span>
		<component :is="getTableCellComponent(targetColumn)"
			v-else-if="lookup.status === 'ready' && targetColumn"
			:key="relatedRow?.id"
			:column="targetColumn"
			:row-id="rowId"
			:value="targetValue"
			:can-edit="false" />
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { getTableCellComponent } from './TableCell.js'

export default {
	name: 'TableCellLookup',
	props: {
		column: {
			type: Object,
			required: true,
		},
		rowId: {
			type: Number,
			default: null,
		},
		value: {
			type: [String, Number, Object],
			default: null,
		},
	},
	computed: {
		lookup() {
			return this.column.resolveValue(this.value)
		},
		targetColumn() {
			return this.lookup.column
		},
		relatedRow() {
			// Resolve the related row through the column's shared lookup resolver
			return this.lookup.row
		},
		targetValue() {
			return this.lookup.value
		},
	},
	methods: {
		t,
		getTableCellComponent,
	},
}
</script>
