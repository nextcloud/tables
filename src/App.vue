<template>
	<div id="content" class="content app-tables">
		<Navigation />
		<NcAppContent>
			<div v-if="somethingIsLoading" class="icon-loading" />

			<router-view v-if="!somethingIsLoading" />
		</NcAppContent>
		<NcAppSidebar v-show="showSidebar"
			:active="getSidebarActiveTab"
			:title="(activeTable) ? activeTable.title : t('tables', 'No table in context')"
			:subtitle="(activeTable) ? t('tables', 'From {ownerName}', { ownerName: activeTable.ownership }) : ''"
			@update:active="setActiveSidebarTab"
			@close="$store.commit('setShowSidebar', false)">
			<NcAppSidebarTab v-if="getSidebarActiveTab === 'activity'"
				id="activity"
				icon="icon-activity"
				:name="t('tables', 'Activity')">
				{{ t('tables', 'Coming soon') }}
			</NcAppSidebarTab>
			<NcAppSidebarTab v-if="getSidebarActiveTab === 'comments'"
				id="comments"
				icon="icon-comment"
				:name="t('tables', 'Comments')">
				{{ t('tables', 'Coming soon') }}
			</NcAppSidebarTab>
			<NcAppSidebarTab v-if="getSidebarActiveTab === 'share'"
				id="share"
				icon="icon-share"
				:name="t('tables', 'Sharing')">
				<SidebarSharing />
			</NcAppSidebarTab>
		</NcAppSidebar>
	</div>
</template>

<script>
import { NcAppContent, NcAppSidebar, NcAppSidebarTab } from '@nextcloud/vue'
import Navigation from './Navigation.vue'
import { mapGetters, mapState } from 'vuex'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import SidebarSharing from './pages/sections/SidebarSharing.vue'

export default {
	name: 'App',
	components: {
		SidebarSharing,
		NcAppContent,
		Navigation,
		NcAppSidebar,
		NcAppSidebarTab,
	},
	props: {
		tableId: {
			type: Number,
			default: null,
		},
	},
	data() {
		return {
			loading: false,
		}
	},
	computed: {
		...mapState(['tables', 'tablesLoading', 'showSidebar', 'sidebarActiveTab']),
		...mapGetters(['activeTable']),
		somethingIsLoading() {
			return this.tablesLoading || this.loading
		},
		getSidebarActiveTab() {
			return this.sidebarActiveTab
		},
	},
	watch: {
		'$route'(to, from) {
			// console.debug('route changed', { to, from })
			this.$store.commit('setActiveTableId', parseInt(to.params.tableId))
		},
	},
	async created() {
		await this.$store.dispatch('loadTablesFromBE')
		this.$store.commit('setActiveTableId', parseInt(this.$router.currentRoute.params.tableId))
	},
	methods: {
		setActiveSidebarTab(activeTab) {
			this.$store.commit('setSidebarActiveTab', activeTab)
		},
		async loadSharees(query) {

			const response = await axios.get(generateOcsUrl('apps/files_sharing/api/v1/sharees'), {
				params: {
					format: 'json',
					itemType: 'file',
					search: '',
					lookup: false,
					perPage: 10,
				},
			})
			console.debug('sharees', response)
		},
	},
}
</script>
<style scoped>

	.sidebar-icon {
		position: absolute;
		right: 5px;
		top: 5px;
	}

</style>
