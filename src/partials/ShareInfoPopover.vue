<template>
	<Popover v-if="share">
		<template #trigger>
			<button class="icon-details" />
		</template>
		<table>
			<tr>
				<td>
					{{ t('tables', 'Last edit') }}
				</td>
				<td>
					{{ updateTime }}
				</td>
			</tr>
			<tr>
				<td>
					{{ t('tables', 'Create time') }}
				</td>
				<td>
					{{ createTime }}
				</td>
			</tr>
			<tr>
				<td>
					{{ t('tables', 'Share ID') }}
				</td>
				<td>
					{{ share.id }}
				</td>
			</tr>
		</table>
	</Popover>
</template>

<script>
import Popover from '@nextcloud/vue/dist/Components/Popover'
import moment from '@nextcloud/moment'

export default {
	name: 'ShareInfoPopover',
	components: {
		Popover,
	},
	props: {
		share: {
			type: Object,
			default: null,
		},
	},
	computed: {
		createTime() {
			return (this.share && this.share.createdAt) ? this.relativeDateTime(this.share.createdAt) : ''
		},
		updateTime() {
			return (this.share && this.share.lastEditAt) ? this.relativeDateTime(this.share.lastEditAt) : ''
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
