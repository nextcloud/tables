<template>
	<div class="row first-row">
		<h1>
			{{ activeElement.emoji }}&nbsp;{{ activeElement.title }}
		</h1>
		<div class="light">
			<NcActions>
				<NcActionButton v-if="!activeElement.isShared || (activeElement.isShared && activeElement.onSharePermissions.manage)"
					icon="icon-rename"
					:close-after-click="true"
					@click="editElement">
					{{ t('tables', 'Edit table') }}
				</NcActionButton>
			</NcActions>
		</div>
		<div class="user-bubble">
			<NcUserBubble v-if="activeElement.isShared"
				:display-name="activeElement.ownerDisplayName"
				:show-user-status="true"
				:user="activeElement.ownership" />
		</div>
	</div>
</template>

<script>

import { mapGetters } from 'vuex'
import { emit } from '@nextcloud/event-bus'
import { NcActions, NcActionButton, NcUserBubble } from '@nextcloud/vue'
export default {
	name: 'ElementDescription',
	components: {
		NcActions,
		NcActionButton,
		NcUserBubble,
	},
	computed: {
		...mapGetters(['activeTable', 'activeView']),
		activeElement() {
			if (this.activeTable) return this.activeTable
			else return this.activeView
		},
		isTable() {
			return !this.activeView
		},
	},

	methods: {
		editElement() {
			emit(this.isTable ? 'edit-table' : 'edit-view', this.activeElement.id)
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
