<template>
	<div>
		<NcAppSidebar v-show="showSidebar"
			:active="activeSidebarTab"
			:title="elementTitle"
			:subtitle="elementSubtitle"
			@update:active="tab => activeSidebarTab = tab"
			@close="showSidebar = false">
			<NcAppSidebarTab
				id="integration"
				:name="t('tables', 'Integration')">
				<SidebarIntegration />
				<template #icon>
					<Creation :size="20" />
				</template>
			</NcAppSidebarTab>
			<NcAppSidebarTab v-if="activeView && canShareElement(activeView)"
				id="sharing"
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
import SidebarIntegration from './SidebarIntegration.vue'
import { NcAppSidebar, NcAppSidebarTab } from '@nextcloud/vue'
import { mapGetters, mapState } from 'vuex'
import Creation from 'vue-material-design-icons/Creation.vue'
import permissionsMixin from '../../../shared/components/ncTable/mixins/permissionsMixin.js'

export default {
	name: 'Sidebar',
	components: {
		SidebarSharing,
		SidebarIntegration,
		NcAppSidebar,
		NcAppSidebarTab,
		Creation,
	},

	mixins: [permissionsMixin],

	data() {
		return {
			showSidebar: false,
			activeSidebarTab: '',
		}
	},
	computed: {
		...mapState(['tables']),
		...mapGetters(['activeView']),
		elementTitle() {
			if (this.activeView) {
				return this.activeView.emoji + ' ' + this.activeView.title
			} else {
				return t('tables', 'No view in context')
			}
		},
		elementSubtitle() {
			if (this.activeView) {
				return t('tables', 'From {ownerName}', { ownerName: this.activeView.createdBy })
				// TODO: Created By?
			} else {
				return ''
			}
		},
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
