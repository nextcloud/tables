<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcAppNavigationItem v-if="context" data-cy="navigationContextItem" :name="context.name"
		:class="{ active: activeContext && context.id === activeContext.id }" :force-menu="true"
		:to="'/application/' + parseInt(context.id)">
		<template #icon>
			<template v-if="context.iconName">
				<NcIconSvgWrapper :svg="icon" />
			</template>
			<template v-else>
				<TableIcon :size="20" />
			</template>
		</template>
		<template #actions>
			<NcActionButton v-if="ownsContext(context)" :close-after-click="true" data-cy="navigationContextEditBtn"
				@click="editContext">
				<template #icon>
					<PlaylistEdit :size="20" />
				</template>
				{{ t('tables', 'Edit application') }}
			</NcActionButton>
			<NcActionButton v-if="ownsContext(context)" :close-after-click="true" @click="transferContext">
				<template #icon>
					<FileSwapOutline :size="20" />
				</template>
				{{ t('tables', 'Transfer application') }}
			</NcActionButton>
			<NcActionButton v-if="ownsContext(context)" :close-after-click="true" data-cy="navigationContextDeleteBtn"
				@click="deleteContext">
				<template #icon>
					<DeleteOutline :size="20" />
				</template>
				{{ t('tables', 'Delete application') }}
			</NcActionButton>
			<NcActionCheckbox :checked="showInNavigation" data-cy="navigationContextShowInNavSwitch" @change="changeDisplayMode">
				{{ t('tables', 'Show in app list') }}
			</NcActionCheckbox>
		</template>
	</NcAppNavigationItem>
</template>
<script>
import { NcAppNavigationItem, NcActionButton, NcIconSvgWrapper, NcActionCheckbox } from '@nextcloud/vue'
import '@nextcloud/dialogs/style.css'
import { mapState, mapActions } from 'pinia'
import TableIcon from 'vue-material-design-icons/Table.vue'
import { emit } from '@nextcloud/event-bus'
import PlaylistEdit from 'vue-material-design-icons/PlaylistEdit.vue'
import FileSwapOutline from 'vue-material-design-icons/FileSwapOutline.vue'
import DeleteOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import permissionsMixin from '../../../shared/components/ncTable/mixins/permissionsMixin.js'
import svgHelper from '../../../shared/components/ncIconPicker/mixins/svgHelper.js'
import { NAV_ENTRY_MODE } from '../../../shared/constants.ts'
import rebuildNavigation from '../../../service/rebuild-navigation.js'
import { useTablesStore } from '../../../store/store.js'

export default {
	name: 'NavigationContextItem',

	components: {
		PlaylistEdit,
		FileSwapOutline,
		TableIcon,
		DeleteOutline,
		NcIconSvgWrapper,
		NcAppNavigationItem,
		NcActionButton,
		NcActionCheckbox,
	},

	mixins: [permissionsMixin, svgHelper],

	props: {
		context: {
			type: Object,
			default: null,
		},
	},

	data() {
		return {
			icon: null,
			showInNavigation: this.getNavDisplay(),
		}
	},
	computed: {
		...mapState(useTablesStore, ['activeContext']),
	},

	watch: {
		'context.iconName': {
			async handler() {
				this.icon = await this.getContextIcon(this.context.iconName)
			},
			immediate: true,
		},
	},

	methods: {
		...mapActions(useTablesStore, ['updateDisplayMode']),
		emit,
		async editContext() {
			emit('tables:context:edit', this.context.id)
		},
		async transferContext() {
			emit('tables:context:transfer', this.context)
		},
		deleteContext() {
			emit('tables:context:delete', this.context)
		},
		getNavDisplay() {
			for (const share of Object.values(this.context.sharing || {})) {
				if (share?.display_mode !== NAV_ENTRY_MODE.NAV_ENTRY_MODE_HIDDEN) {
					return true
				}
			}
			return false
		},
		async changeDisplayMode() {
			const value = !this.showInNavigation
			const displayMode = value ? NAV_ENTRY_MODE.NAV_ENTRY_MODE_ALL : NAV_ENTRY_MODE.NAV_ENTRY_MODE_HIDDEN
			let hadAtLeastOneEntry = false
			for (const share of Object.values(this.context.sharing || {})) {
				if (!share) {
					continue
				}
				hadAtLeastOneEntry = true
				await this.updateDisplayMode({ shareId: share.share_id, displayMode, target: 'self' })
				this.showInNavigation = value
			}
			if (hadAtLeastOneEntry) {
				await rebuildNavigation()
			} else {
				console.error('No share found ' + this.context.sharing)
			}
		},
	},

}
</script>
