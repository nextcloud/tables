<template>
	<Popover v-if="column">
		<template #trigger>
			<button class="icon-details" />
		</template>
		<table>
			<tr>
				<td>
					{{ t('tables', 'Last edit') }}
				</td>
				<td>
					{{ updateTime }}<br>
					<UserBubble :user="column.lastEditBy" :display-name="column.lastEditBy" />
				</td>
			</tr>
			<tr>
				<td>
					{{ t('tables', 'Create') }}
				</td>
				<td>
					{{ createTime }}<br>
					<UserBubble :user="column.createdBy" :display-name="column.createdBy" />
				</td>
			</tr>
			<tr>
				<td>
					{{ t('tables', 'Column ID') }}
				</td>
				<td>
					{{ column.id }}
				</td>
			</tr>
			<tr>
				<td>
					{{ t('tables', 'Table ID') }}
				</td>
				<td>
					{{ column.tableId }}
				</td>
			</tr>
			<tr>
				<td>
					{{ t('tables', 'Ownership') }}
				</td>
				<td>
					{{ column.ownership }}
				</td>
			</tr>
		</table>
	</Popover>
</template>

<script>
import Popover from '@nextcloud/vue/dist/Components/Popover'
import moment from '@nextcloud/moment'
import UserBubble from '@nextcloud/vue/dist/Components/UserBubble'

export default {
	name: 'ColumnInfoPopover',
	components: {
		Popover,
		UserBubble,
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

</style>
