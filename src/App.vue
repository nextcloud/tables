<template>
	<div id="content">
		<Navigation />
		<AppContent>
			<div v-if="somethingIsLoading" class="icon-loading" />

			<router-view v-if="!somethingIsLoading" />
		</AppContent>
		<AppSidebar
			v-show="showSidebar"
			:active="getSidebarActiveTab"
			:title="(activeTable) ? activeTable.title : t('tables', 'No table in context')"
			:subtitle="(activeTable) ? t('tables', 'From {ownerName}', { ownerName: activeTable.ownership }) : ''"
			@update:active="setActiveSidebarTab"
			@close="$store.commit('setShowSidebar', false)">
			<AppSidebarTab v-if="getSidebarActiveTab === 'activity'"
				id="activity"
				icon="icon-activity"
				:name="t('tables', 'Activity')">
				{{ t('tables', 'Coming soon') }}
			</AppSidebarTab>
			<AppSidebarTab v-if="getSidebarActiveTab === 'comments'"
				id="comments"
				icon="icon-comment"
				:name="t('tables', 'Comments')">
				{{ t('tables', 'Coming soon') }}
			</AppSidebarTab>
			<AppSidebarTab v-if="getSidebarActiveTab === 'share'"
				id="share"
				icon="icon-share"
				:name="t('tables', 'Sharing')">
				<SidebarSharing />
			</AppSidebarTab>
		</AppSidebar>
	</div>
</template>

<script>
import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import Navigation from './Navigation'
import { mapGetters, mapState } from 'vuex'
import AppSidebar from '@nextcloud/vue/dist/Components/AppSidebar'
import AppSidebarTab from '@nextcloud/vue/dist/Components/AppSidebarTab'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import SidebarSharing from './pages/sections/SidebarSharing'

export default {
	name: 'App',
	components: {
		SidebarSharing,
		AppContent,
		Navigation,
		AppSidebar,
		AppSidebarTab,
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
