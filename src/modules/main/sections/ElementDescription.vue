<template>
	<div class="row first-row">
		<h1>
			{{ activeView.emoji }}&nbsp;{{ activeView.title }}
		</h1>
		<div class="light">
			<NcActions>
				<NcActionButton v-if="!activeView.isShared || (activeView.isShared && activeView.onSharePermissions.manage)"
					icon="icon-rename"
					:close-after-click="true"
					@click="editElement">
					{{ activeView.isBaseView ? t('tables', 'Edit table') : t('tables', 'Edit view') }}
				</NcActionButton>
			</NcActions>
		</div>
		<div class="user-bubble">
			<NcUserBubble v-if="activeView.isShared"
				:display-name="activeView.ownerDisplayName"
				:show-user-status="true"
				:user="activeView.ownership" />
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
		...mapGetters(['activeView']),
	},

	methods: {
		editElement() {
			emit('edit-view', this.activeElemen)
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
