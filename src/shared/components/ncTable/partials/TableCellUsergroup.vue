<template>
	<!-- eslint-disable-next-line vue/no-v-html -->
	<div v-if="value">
		<div v-for="item in value" :key="item.id" class="inline">
			<!-- <NcAvatar :display-name="item.displayName" :user="item.id" :is-no-user="!item.isUser"
				:show-user-status="column.showUserStatus" />
			<span>
				{{ item.displayName }}
			</span> -->
			<NcUserBubble :display-name="item.displayName" :show-user-status="column.showUserStatus" :user="item.isUser ? item.id: ''">
				@{{ item.displayName }}
			</NcUserBubble>
		</div>
	</div>
</template>

<script>
import { NcAvatar, NcUserBubble } from '@nextcloud/vue'
export default {
	name: 'TableCellUsergroup',
	components: {
		NcAvatar,
		NcUserBubble,
	},
	props: {
		column: {
			type: Object,
			default: () => { },
		},
		rowId: {
			type: Number,
			default: null,
		},
		value: {
			type: Array,
			default: () => [],
		},
	},
}
</script>
