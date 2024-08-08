<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcPopover v-if="column">
		<template #trigger>
			<button class="icon-details" />
		</template>
		<table>
			<tr>
				<td class="key">
					{{ t('tables', 'Last edit') }}
				</td>
				<td class="value">
					{{ updateTime }}&nbsp;
					<NcUserBubble :user="column.lastEditBy" :display-name="column.lastEditByDisplayName ? column.lastEditByDisplayName : column.lastEditBy" />
				</td>
			</tr>
			<tr>
				<td class="key">
					{{ t('tables', 'Create') }}
				</td>
				<td class="value">
					{{ createTime }}&nbsp;
					<NcUserBubble :user="column.createdBy" :display-name="column.createdByDisplayName ? column.createdByDisplayName : column.createdBy" />
				</td>
			</tr>
			<tr>
				<td class="key">
					{{ t('tables', 'Column ID') }}
				</td>
				<td class="value">
					{{ column.id }}
				</td>
			</tr>
			<tr>
				<td class="key">
					{{ t('tables', 'Table ID') }}
				</td>
				<td class="value">
					{{ column.tableId }}
				</td>
			</tr>
		</table>
	</NcPopover>
</template>

<script>
import { NcPopover, NcUserBubble } from '@nextcloud/vue'
import moment from '@nextcloud/moment'

export default {
	name: 'ColumnInfoPopover',
	components: {
		NcPopover,
		NcUserBubble,
	},
	props: {
		column: {
			type: Object,
			default: null,
		},
	},
	computed: {
		createTime() {
			return (this.column && this.column.createdAt) ? this.relativeDateTime(this.column.createdAt) : ''
		},
		updateTime() {
			return (this.column && this.column.lastEditAt) ? this.relativeDateTime(this.column.lastEditAt) : ''
		},
	},
	methods: {
		relativeDateTime(v) {
			return moment(v).format('L') === moment().format('L') ? t('tables', 'Today') + ' ' + moment(v).format('LT') : moment(v).format('LLLL')
		},
	},
}
</script>
<style scoped>

.popover__wrapper table, .popover__wrapper td {
	padding: 8px;
	vertical-align: text-top;
}

td {
	vertical-align: top;
}

td.value {
	text-align: end;
}

</style>
