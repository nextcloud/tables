<template>
	<Popover v-if="table">
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
					<UserBubble :user="table.lastEditBy" :display-name="table.lastEditBy" />
				</td>
			</tr>
			<tr>
				<td>
					{{ t('tables', 'Create') }}
				</td>
				<td>
					{{ createTime }}<br>
					<UserBubble :user="table.createdBy" :display-name="table.createdBy" />
				</td>
			</tr>
			<tr>
				<td>
					{{ t('tables', 'Table ID') }}
				</td>
				<td>
					{{ table.id }}
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
	name: 'TableInfoPopover',
	components: {
		Popover,
		UserBubble,
	},
	props: {
		table: {
			type: Object,
			default: null,
		},
	},
	computed: {
		createTime() {
			return (this.table && this.table.createdAt) ? this.relativeDateTime(this.table.createdAt) : ''
		},
		updateTime() {
			return (this.table && this.table.lastEditAt) ? this.relativeDateTime(this.table.lastEditAt) : ''
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
