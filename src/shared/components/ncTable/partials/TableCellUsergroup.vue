<template>
	<!-- eslint-disable-next-line vue/no-v-html -->
	<div v-if="value">
		<div v-for="item in value" :key="item.id" class="inline">
			<!-- <NcAvatar :display-name="item.id" :user="item.id" :is-no-user="!item.type === 0"
				:show-user-status="column.showUserStatus" />
			<span>
				{{ item.id }}
			</span> -->
			<NcUserBubble :display-name="item.id" :show-user-status="column.showUserStatus" :user="item.type === 0 ? item.id: ''">
				@{{ item.id }}
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
	mounted() {
		console.log(value)
	}
}
</script>
