<template>
	<div class="row first-row">
		<h1>
			{{ activeTable.emoji }}&nbsp;{{ activeTable.title }}
		</h1>
		<div class="light">
			<NcActions>
				<NcActionButton v-if="!activeTable.isShared || (activeTable.isShared && activeTable.onSharePermissions.manage)"
					icon="icon-rename"
					:close-after-click="true"
					@click="editTable">
					{{ t('tables', 'Edit table') }}
				</NcActionButton>
			</NcActions>
		</div>
		<div class="user-bubble">
			<NcUserBubble v-if="activeTable.isShared"
				:display-name="activeTable.ownerDisplayName"
				:show-user-status="true"
				:user="activeTable.ownership" />
		</div>
	</div>
</template>

<script>
import { mapGetters } from 'vuex'
import { NcActions, NcActionButton, NcUserBubble } from '@nextcloud/vue'
import { emit } from '@nextcloud/event-bus'

export default {
	name: 'TableDescription',
	components: {
		NcActions,
		NcActionButton,
		NcUserBubble,
	},
	computed: {
		...mapGetters(['activeTable']),
	},
	methods: {
		editTable() {
			emit('edit-table', this.activeTable.id)
		},
	},
}
</script>

<style lang="scss" scoped>

.light {
	opacity: .3;
}

.first-row:hover .light {
	opacity: 1;
}

.row.first-row {
	position: sticky;
	left: 0;
	top: 0;
	z-index: 15;
	background-color: var(--color-main-background-translucent);
	align-items: center;
}

.user-bubble {
	padding-left: calc(var(--default-grid-baseline) * 2);
}

</style>
