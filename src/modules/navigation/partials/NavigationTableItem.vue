<!--
	- SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
	- SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcAppNavigationItem v-if="table" v-model:open="isParentOfActiveView" data-cy="navigationTableItem"
		:name="table.title" :class="{ active: activeTable && table.id === activeTable.id }" :allow-collapse="hasViews"
		:force-menu="true" :to="'/table/' + parseInt(table.id)" @click="openTable">
		<template #icon>
			<template v-if="table.emoji">
				{{ table.emoji }}
			</template>

			<template v-else>
				<Table :size="20" />
			</template>
		</template>

		<template #counter>
			<NcCounterBubble v-if="canReadData(table)" :count="table.rowsCount" />
			<NcActionButton v-if="table.hasShares" icon="icon-share"
				:class="{ 'margin-right': !(activeTable && table.id === activeTable.id) }" @click="actionShowShare" />
			<div v-if="table.isShared && table.ownership !== userId" class="margin-left">
				<NcAvatar :user="table.ownership" :show-user-status="false" />
			</div>
		</template>

		<template #actions>
			<!-- EDIT -->
			<NcActionButton :close-after-click="true"
				@click="emit('tables:table:edit', table.id)">
				<template #icon>
					<IconRename :size="20" decorative />
				</template>
				{{ t('tables', 'Table settings') }}
			</NcActionButton>

			<!-- CREATE VIEW -->
			<NcActionButton v-if="canManageElement(table)" :close-after-click="true" @click="createView">
				<template #icon>
					<PlaylistPlus :size="20" />
				</template>
				{{ t('tables', 'Create view') }}
			</NcActionButton>

			<!-- SHARE -->
			<NcActionButton v-if="canShareElement(table)" icon="icon-share" :close-after-click="true"
				@click="actionShowShare">
				{{ t('tables', 'Share') }}
			</NcActionButton>

			<!-- IMPORT -->
			<NcActionButton v-if="canCreateRowInElement(table)" :close-after-click="true"
				@click="actionShowImport(table)">
				{{ t('tables', 'Import') }}
				<template #icon>
					<Import :size="20" />
				</template>
			</NcActionButton>

			<!-- EXPORT -->
			<NcActionButton @click="exportFile">
				{{ t('tables', 'Export') }}
				<template #icon>
					<TrayArrowDown :size="20" />
				</template>
			</NcActionButton>

			<!-- INTEGRATION -->
			<NcActionButton :close-after-click="true" @click="actionShowIntegration">
				{{ t('tables', 'Integration') }}
				<template #icon>
					<ListBoxOutline :size="20" />
				</template>
			</NcActionButton>

			<!-- Activity -->
			<NcActionButton v-if="canReadData(table) && isActivityEnabled" :close-after-click="true"
				@click="actionShowActivity">
				{{ t('tables', 'Activity') }}
				<template #icon>
					<ActivityIcon :size="20" />
				</template>
			</NcActionButton>

			<!-- FAVORITE -->
			<NcActionButton v-if="!table.favorite && !table.archived" :close-after-click="true"
				@click="toggleFavoriteTable(true)">
				{{ t('tables', 'Add to favorites') }}
				<template #icon>
					<StarOutline :size="20" />
				</template>
			</NcActionButton>

			<!-- UNFAVORITE -->
			<NcActionButton v-if="table.favorite" :close-after-click="true" @click="toggleFavoriteTable(false)">
				{{ t('tables', 'Remove from favorites') }}
				<template #icon>
					<Star :size="20" />
				</template>
			</NcActionButton>

			<!-- ARCHIVE -->
			<NcActionButton v-if="canManageElement(table) && !table.archived && !table.favorite"
				:close-after-click="true" @click="toggleArchiveTable(true)">
				{{ t('tables', 'Archive table') }}
				<template #icon>
					<ArchiveArrowDownOutline :size="20" />
				</template>
			</NcActionButton>

			<!-- UNARCHIVE -->
			<NcActionButton v-if="canManageElement(table) && table.archived" :close-after-click="true"
				@click="toggleArchiveTable(false)">
				{{ t('tables', 'Unarchive table') }}
				<template #icon>
					<ArchiveArrowUpOutline :size="20" />
				</template>
			</NcActionButton>

			<!-- DELETE -->
			<NcActionButton v-if="canManageElement(table)" :close-after-click="true" @click="deleteTable()">
				{{ t('tables', 'Delete table') }}
				<template #icon>
					<DeleteOutline :size="20" />
				</template>
			</NcActionButton>
		</template>
		<ul>
			<NavigationViewItem v-for="(view, index) in orderedViews" :key="'view' + view.id" :view="view"
				:show-share-sender="false"
				:draggable="canReorderViews"
				:class="{ 'view-drop-target': dragOverIndex === index }"
				@dragstart.native="onViewDragStart(index)"
				@dragover.native.prevent="onViewDragOver(index)"
				@drop.native.prevent="onViewDrop"
				@dragend.native="onViewDragEnd" />
		</ul>
	</NcAppNavigationItem>
</template>

<script>
import { NcActionButton, NcAppNavigationItem, NcCounterBubble, NcAvatar } from '@nextcloud/vue'
import '@nextcloud/dialogs/style.css'
import { mapState, mapActions } from 'pinia'
import { emit } from '@nextcloud/event-bus'
import { useTablesStore } from '../../../store/store.js'
import Table from 'vue-material-design-icons/Table.vue'
import Star from 'vue-material-design-icons/Star.vue'
import StarOutline from 'vue-material-design-icons/StarOutline.vue'
import ArchiveArrowDownOutline from 'vue-material-design-icons/ArchiveArrowDownOutline.vue'
import ArchiveArrowUpOutline from 'vue-material-design-icons/ArchiveArrowUpOutline.vue'
import DeleteOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import permissionsMixin from '../../../shared/components/ncTable/mixins/permissionsMixin.js'
import activityMixin from '../../../shared/mixins/activityMixin.js'
import { getCurrentUser, getRequestToken } from '@nextcloud/auth'
import ListBoxOutline from 'vue-material-design-icons/ListBoxOutline.vue'
import Import from 'vue-material-design-icons/Import.vue'
import TrayArrowDown from 'vue-material-design-icons/TrayArrowDown.vue'
import NavigationViewItem from './NavigationViewItem.vue'
import PlaylistPlus from 'vue-material-design-icons/PlaylistPlus.vue'
import IconRename from 'vue-material-design-icons/RenameOutline.vue'
import ActivityIcon from 'vue-material-design-icons/LightningBoltOutline.vue'
import { generateUrl } from '@nextcloud/router'

export default {

	components: {
		IconRename,
		Table,
		Star,
		StarOutline,
		ArchiveArrowDownOutline,
		ArchiveArrowUpOutline,
		Import,
		TrayArrowDown,
		NavigationViewItem,
		NcActionButton,
		NcAppNavigationItem,
		NcCounterBubble,
		NcAvatar,
		ListBoxOutline,
		PlaylistPlus,
		DeleteOutline,
		ActivityIcon,
	},

	mixins: [permissionsMixin, activityMixin],

	props: {
		table: {
			type: Object,
			default: null,
		},
		filterString: {
			type: String,
			default: '',
		},
	},

	data() {
		return {
			isParentOfActiveView: false,
			orderedViews: [],
			draggedIndex: null,
			dragOverIndex: null,
		}
	},

	computed: {
		...mapState(useTablesStore, ['activeTable', 'activeView', 'views']),
		getTranslatedDescription() {
			return t('tables', 'Do you really want to delete the table "{table}"?', { table: this.table.title })
		},
		userId() {
			return getCurrentUser().uid
		},
		getViews() {
			return this.views
				.filter(v => v.tableId === this.table.id && v.title.toLowerCase().includes(this.filterString.toLowerCase()))
				.sort((a, b) => {
					const orderA = a.sidebarOrder ?? Number.MAX_SAFE_INTEGER
					const orderB = b.sidebarOrder ?? Number.MAX_SAFE_INTEGER
					return orderA - orderB || a.id - b.id
				})
		},
		hasViews() {
			return this.getViews.length > 0
		},
		canReorderViews() {
			return this.canManageElement(this.table) && !this.filterString && this.orderedViews.length > 1
		},
	},
	watch: {
		getViews: {
			handler(views) {
				this.orderedViews = [...views]
			},
			immediate: true,
		},
		activeView() {
			if (!this.isParentOfActiveView && this.activeView?.tableId === this.table?.id) {
				this.isParentOfActiveView = true
			}
		},
		filterString() {
			if (!this.isParentOfActiveView && this.filterString && !this.table.title.toLowerCase().includes(this.filterString.toLowerCase())) {
				this.isParentOfActiveView = true
			}
		},
	},
	methods: {
		...mapActions(useTablesStore, ['favoriteTable', 'removeFavoriteTable', 'updateTable', 'updateView']),
		emit,
		onViewDragStart(index) {
			if (!this.canReorderViews) {
				return
			}
			this.draggedIndex = index
		},
		onViewDragOver(index) {
			if (this.draggedIndex === null || this.draggedIndex === index) {
				return
			}
			const moved = this.orderedViews.splice(this.draggedIndex, 1)[0]
			this.orderedViews.splice(index, 0, moved)
			this.draggedIndex = index
			this.dragOverIndex = index
		},
		onViewDrop() {
			this.persistViewOrder()
		},
		onViewDragEnd() {
			this.persistViewOrder()
		},
		async persistViewOrder() {
			if (this.draggedIndex === null) {
				this.dragOverIndex = null
				return
			}
			this.draggedIndex = null
			this.dragOverIndex = null

			const updates = []
			this.orderedViews.forEach((view, index) => {
				if (view.sidebarOrder !== index) {
					updates.push(this.updateView({ id: view.id, data: { data: { sidebarOrder: index } } }))
				}
			})
			if (updates.length) {
				await Promise.all(updates)
			}
		},
		deleteTable() {
			emit('tables:table:delete', this.table)
		},
		createView() {
			emit('tables:view:create', { tableId: this.table.id })
		},

		exportFile() {
			const form = document.createElement('form')
			form.method = 'GET'
			form.action = generateUrl(`/apps/tables/api/1/tables/${this.table.id}/scheme`)

			const token = document.createElement('input')
			token.type = 'hidden'
			token.name = 'requesttoken'
			token.value = getRequestToken()

			form.appendChild(token)

			document.body.appendChild(form)
			form.submit()
		},

		async actionShowShare() {
			emit('tables:sidebar:sharing', { open: true, tab: 'sharing' })
			await this.$router.push('/table/' + parseInt(this.table.id)).catch(err => err)
		},
		async actionShowImport(table) {
			emit('tables:modal:import', { element: table, isView: false })
		},
		async actionShowIntegration() {
			emit('tables:sidebar:integration', { open: true, tab: 'integration' })
			await this.$router.push('/table/' + parseInt(this.table.id)).catch(err => err)
		},
		async actionShowActivity() {
			emit('tables:sidebar:activity', { open: true, tab: 'activity' })
			await this.$router.push('/table/' + parseInt(this.table.id)).catch(err => err)
		},
		openTable() {
			this.isParentOfActiveView = true
			// Close navigation
			if (window.innerWidth < 960) {
				emit('toggle-navigation', {
					open: false,
				})
			}
		},
		async toggleArchiveTable(archived) {
			await this.updateTable({
				id: this.table.id,
				data: { archived },
			})
		},
		async toggleFavoriteTable(favorite) {
			if (favorite) {
				await this.favoriteTable({
					id: this.table.id,
				})
			} else {
				await this.removeFavoriteTable({
					id: this.table.id,
				})
			}
		},
	},
}
</script>
<style lang="scss" scoped>
.app-navigation-entry.active {
	.icon-share {
		background-image: var(--icon-share-white)
	}

	.icon-collapse {
		color: var(--color-primary-element-text)
	}
}

:deep(.app-navigation-entry__counter-wrapper) {
	.action-button {
		padding-inline-end: 0;
	}
}

.app-navigation-entry {
	.margin-right {
		margin-inline-end: 44px;
	}

	.margin-left {
		margin-inline-start: calc(var(--default-grid-baseline) * 2);
	}
}

.app-navigation-entry:hover {
	.margin-right {
		margin-inline-end: 0;
	}

	.app-navigation-entry__counter-wrapper .counter-bubble__counter {
		display: inline;
	}
}

.view-drop-target {
	border-top: 2px solid var(--color-primary-element);
}
</style>
