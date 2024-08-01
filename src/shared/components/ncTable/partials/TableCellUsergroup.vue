<template>
	<!-- eslint-disable-next-line vue/no-v-html -->
	<div v-if="value">
		<div v-for="item in value" :key="item.id" class="inline">
			<div class="fix-col-2">
				<div style="display:flex; align-items: center;">
					<NcAvatar :display-name="item.id" :user="item.id" :is-no-user="item.type !== 0"
						:show-user-status="column.showUserStatus" />
				</div>
				<div class="userInfo">
					<div class="high-line-height">
						{{ item.id }}
					</div>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import { NcAvatar } from '@nextcloud/vue'
export default {
	name: 'TableCellUsergroup',
	components: {
		NcAvatar,
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

<style lang="scss" scoped>
	.userInfo {
		padding-left: 5px;
		padding-right: 10px;
		font-size: 100%;
		display: flex;
		flex-direction: column;
	}

	.high-line-height {
		line-height: 35px;
	}
</style>
