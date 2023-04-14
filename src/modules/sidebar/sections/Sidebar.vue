<template>
	<div>
		<NcAppSidebar v-show="showSidebar"
			:active="activeSidebarTab"
			:title="(activeTable) ? activeTable.emoji + ' ' + activeTable.title : t('tables', 'No table in context')"
			:subtitle="(activeTable) ? t('tables', 'From {ownerName}', { ownerName: activeTable.ownership }) : ''"
			@update:active="tab => activeSidebarTab = tab"
			@close="showSidebar = false">
			<NcAppSidebarTab v-if="canShareActiveTable"
				id="sharing"
				icon="icon-share"
				:name="t('tables', 'Sharing')">
				<SidebarSharing />
			</NcAppSidebarTab>
			<NcAppSidebarTab
				id="integration"
				icon="icon-share"
				:name="t('tables', 'Integration')">
				<SidebarIntegration />
				<template #icon>
					<Creation :size="20" />
				</template>
			</NcAppSidebarTab>
		</NcAppSidebar>
	</div>
</template>
<script>
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import SidebarSharing from './SidebarSharing.vue'
import SidebarIntegration from './SidebarIntegration.vue'
import { NcAppSidebar, NcAppSidebarTab } from '@nextcloud/vue'
import { mapGetters, mapState } from 'vuex'
import Creation from 'vue-material-design-icons/Creation.vue'
import tablePermissions from '../../main/mixins/tablePermissions.js'

export default {
	name: 'Sidebar',
	components: {
		SidebarSharing,
		SidebarIntegration,
		NcAppSidebar,
		NcAppSidebarTab,
		Creation,
	},

	mixins: [tablePermissions],

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
		subscribe('tables:sidebar:integration', data => this.handleToggleSidebar(data))
	},
	beforeDestroy() {
		unsubscribe('tables:sidebar:sharing', data => this.handleToggleSidebar(data))
		unsubscribe('tables:sidebar:integration', data => this.handleToggleSidebar(data))
	},
	methods: {
		handleToggleSidebar(data) {
			console.debug('toggle sidebar in nav', data)
			this.showSidebar = data.open ? data.open : false
			this.activeSidebarTab = data.tab ? data.tab : ''
		},
	},
}
</script>
