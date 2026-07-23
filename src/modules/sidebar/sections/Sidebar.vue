<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		<NcAppSidebar v-if="showSidebar"
			:active="activeSidebarTab"
			:name="elementTitle"
			@update:active="tab => activeSidebarTab = tab"
			@close="showSidebar = false">
			<template #description>
				<table v-if="activeElement">
					<tr>
						<td>{{ t('tables', 'Created at') }}</td>
						<td>{{ niceDateTime(activeElement.createdAt) }}</td>
					</tr>
					<tr>
						<td>{{ t('tables', 'Ownership') }}</td>
						<td><NcUserBubble :user="activeElement.ownership" :display-name="activeElement.ownerDisplayName" /></td>
					</tr>
					<tr>
						<td v-if="isView">
							{{ t('tables', 'View ID') }}
						</td>
						<td v-else>
							{{ t('tables', 'Table ID') }}
						</td>
						<td>{{ activeElement.id }}</td>
					</tr>
				</table>
			</template>
			<NcAppSidebarTab
				id="integration"
				:order="2"
				:name="t('tables', 'Integration')">
				<SidebarIntegration />
				<template #icon>
					<ListBox v-if="activeSidebarTab === 'integration'" :size="20" />
					<ListBoxOutline v-else :size="20" />
				</template>
			</NcAppSidebarTab>
			<NcAppSidebarTab v-if="isActivityEnabled"
				id="activity"
				:order="1"
				:name="t('tables', 'Activity')">
				<template #icon>
					<ActivityIcon v-if="activeSidebarTab === 'activity'" :size="20" />
					<LightningBoltOutline v-else :size="20" />
				</template>
				<SidebarActivity />
			</NcAppSidebarTab>
			<NcAppSidebarTab v-if="activeElement && canShareElement(activeElement)"
				id="sharing"
				:order="0"
				:name="t('tables', 'Sharing')">
				<template #icon>
					<NcIconSvgWrapper v-if="activeSidebarTab === 'sharing'" :svg="IconPersonAdd" />
					<NcIconSvgWrapper v-else :svg="IconPersonAddOutline" />
				</template>
				<SidebarSharing />
			</NcAppSidebarTab>
		</NcAppSidebar>
	</div>
</template>

<script>
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import SidebarActivity from './SidebarActivity.vue'
import SidebarSharing from './SidebarSharing.vue'
import SidebarIntegration from './SidebarIntegration.vue'
import { NcAppSidebar, NcAppSidebarTab, NcUserBubble, NcIconSvgWrapper } from '@nextcloud/vue'
import { mapState } from 'pinia'
import ActivityIcon from 'vue-material-design-icons/LightningBolt.vue'
import LightningBoltOutline from 'vue-material-design-icons/LightningBoltOutline.vue'
import ListBox from 'vue-material-design-icons/ListBox.vue'
import ListBoxOutline from 'vue-material-design-icons/ListBoxOutline.vue'
import IconPersonAdd from '@material-symbols/svg-400/outlined/person_add-fill.svg?raw'
import IconPersonAddOutline from '@material-symbols/svg-400/outlined/person_add.svg?raw'
import permissionsMixin from '../../../shared/components/ncTable/mixins/permissionsMixin.js'
import activityMixin from '../../../shared/mixins/activityMixin.js'
import Moment from '@nextcloud/moment'
import { useTablesStore } from '../../../store/store.js'

export default {
	name: 'Sidebar',
	setup() {
		return { IconPersonAdd, IconPersonAddOutline }
	},
	components: {
		NcUserBubble,
		SidebarActivity,
		SidebarSharing,
		SidebarIntegration,
		NcAppSidebar,
		NcAppSidebarTab,
		ActivityIcon,
		LightningBoltOutline,
		ListBox,
		ListBoxOutline,
		NcIconSvgWrapper,
	},

	mixins: [permissionsMixin, activityMixin],

	data() {
		return {
			showSidebar: false,
			activeSidebarTab: '',
		}
	},
	computed: {
		...mapState(useTablesStore, ['tables', 'activeElement', 'isView']),
		elementTitle() {
			if (this.activeElement) {
				return this.activeElement.emoji + ' ' + this.activeElement.title
			} else {
				return t('tables', 'No view in context')
			}
		},
		elementSubtitle() {
			if (this.activeElement) {
				return t('tables', 'From {ownerName}', { ownerName: this.activeElement.ownership })
				// TODO: Created By?
			} else {
				return ''
			}
		},
	},

	mounted() {
		subscribe('tables:sidebar:sharing', data => this.handleToggleSidebar(data))
		subscribe('tables:sidebar:integration', data => this.handleToggleSidebar(data))
		subscribe('tables:sidebar:activity', data => this.handleToggleSidebar(data))
	},
	beforeUnmount() {
		unsubscribe('tables:sidebar:sharing', data => this.handleToggleSidebar(data))
		unsubscribe('tables:sidebar:integration', data => this.handleToggleSidebar(data))
		unsubscribe('tables:sidebar:activity', data => this.handleToggleSidebar(data))
	},
	methods: {
		niceDateTime(value) {
			return Moment(value, 'YYYY-MM-DD HH:mm:ss').format('lll')
		},
		handleToggleSidebar(data) {
			this.showSidebar = data.open ? data.open : false
			this.activeSidebarTab = data.tab ? data.tab : ''
		},
	},
}
</script>
<style lang="scss" scoped>

	table {
		margin-bottom: calc(var(--default-grid-baseline) * 2);
		width: 100%;
		color: var(--color-text-maxcontrast);
	}

</style>
