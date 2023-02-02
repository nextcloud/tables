<template>
	<div>
		<NcAppSidebar v-show="showSidebar"
			:active="activeSidebarTab"
			:title="(activeTable) ? activeTable.title : t('tables', 'No table in context')"
			:subtitle="(activeTable) ? t('tables', 'From {ownerName}', { ownerName: activeTable.ownership }) : ''"
			@update:active="tab => activeSidebarTab = tab"
			@close="showSidebar = false">
			<NcAppSidebarTab v-if="activeSidebarTab === 'activity'"
				id="activity"
				icon="icon-activity"
				:name="t('tables', 'Activity')">
				{{ t('tables', 'Coming soon') }}
			</NcAppSidebarTab>
			<NcAppSidebarTab v-if="activeSidebarTab === 'comments'"
				id="comments"
				icon="icon-comment"
				:name="t('tables', 'Comments')">
				{{ t('tables', 'Coming soon') }}
			</NcAppSidebarTab>
			<NcAppSidebarTab v-if="activeSidebarTab === 'sharing'"
				id="share"
				icon="icon-share"
				:name="t('tables', 'Sharing')">
				<SidebarSharing />
			</NcAppSidebarTab>
		</NcAppSidebar>
	</div>
</template>
<script>
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import SidebarSharing from './SidebarSharing.vue'
import { NcAppSidebar, NcAppSidebarTab } from '@nextcloud/vue'
import { mapGetters, mapState } from 'vuex'

export default {
	name: 'Sidebar',
	components: {
		SidebarSharing,
		NcAppSidebar,
		NcAppSidebarTab,
	},
	data() {
		return {
			showSidebar: false,
			activeSidebarTab: '',
		}
	},
	computed: {
		...mapState(['tables']),
		...mapGetters(['activeTable']),
	},
	mounted() {
		subscribe('tables:sidebar:sharing', data => this.handleToggleSidebar(data))
	},
	beforeDestroy() {
		unsubscribe('tables:sidebar:sharing', data => this.handleToggleSidebar(data))
	},
	methods: {
		handleToggleSidebar(data) {
			this.showSidebar = data.open ? data.open : false
			this.activeSidebarTab = data.tab ? data.tab : ''
		},
	},
}
</script>

<style scoped>

</style>
