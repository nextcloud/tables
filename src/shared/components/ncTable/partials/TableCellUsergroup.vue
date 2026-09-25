<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="cell-usergroup" :style="{ opacity: !canEditCell() ? 0.6 : 1 }">
		<div v-if="!isEditing" class="non-edit-mode" @click="handleStartEditing">
			<div v-if="value" class="table-cell-usergroup">
				<div v-for="item in value" :key="item.id" class="inline usergroup-entry">
					<NcPopover v-if="isUser(item)"
						v-model:shown="hoverCardOpen[item.id]"
						:triggers="['hover', 'focus']"
						:popover-triggers="['hover']"
						:delay="{ show: 400, hide: 300 }"
						popover-base-class="usergroup-profile-popover"
						popup-role="dialog"
						no-focus-trap>
						<template #trigger="{ attrs }">
							<span v-bind="attrs"
								class="usergroup-profile-trigger"
								role="button"
								tabindex="0"
								:aria-label="t('tables', 'Show profile of {name}', { name: item.displayName ?? item.id })"
								data-cy="usergroupProfileTrigger">
								<NcUserBubble :user="item.id" :display-name="item.displayName ?? item.id" :show-user-status="column.showUserStatus" :size="column.showUserStatus ? 34 : 20" :primary="isCurrentUser(item)" />
							</span>
						</template>
						<NcProfileHoverCard :user="item.id"
							:open="hoverCardOpen[item.id] ?? false"
							:actions="contactsMenuActions[item.id] ?? null"
							:actions-loading="contactsMenuLoading[item.id] ?? false" />
					</NcPopover>
					<NcUserBubble v-else :user="item.id" :avatar-image="getAvatarImage(item)" :is-no-user="true" :display-name="item.displayName ?? item.id" :size="column.showUserStatus ? 34 : 20" />
				</div>
			</div>
		</div>
		<div v-else
			ref="editingContainer"
			class="edit-mode"
			tabindex="0"
			@keydown.enter="saveChanges"
			@keydown.escape="cancelEdit">
			<NcSelect v-model="editValue"
				style="width: 100%;"
				:loading="loading || localLoading"
				:options="options"
				:clearable="!column.mandatory"
				:placeholder="getPlaceholder()"
				:searchable="true"
				:get-option-key="(option) => option.key"
				label="displayName"
				:aria-label-combobox="getPlaceholder()"
				:user-select="true"
				:close-on-select="false"
				:multiple="column.usergroupMultipleItems"
				data-cy="usergroupCellSelect"
				@search="asyncFind">
				<template #noResult>
					{{ noResultText }}
				</template>
			</NcSelect>
			<div v-if="localLoading" class="loading-indicator">
				<div class="icon-loading-small icon-loading-inline" />
			</div>
		</div>
	</div>
</template>

<script>
import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { getEnabledContactsMenuActions, NcPopover, NcProfileHoverCard, NcSelect, NcUserBubble } from '@nextcloud/vue'
import { USERGROUP_TYPE } from '../../../constants.ts'
import cellEditMixin from '../mixins/cellEditMixin.js'
import searchUserGroup from '../../../mixins/searchUserGroup.js'
import ShareTypes from '../../../mixins/shareTypesMixin.js'
import { translate as t } from '@nextcloud/l10n'

const currentUser = getCurrentUser()

export default {
	name: 'TableCellUsergroup',

	components: {
		NcPopover,
		NcProfileHoverCard,
		NcSelect,
		NcUserBubble,
	},

	mixins: [
		cellEditMixin,
		searchUserGroup,
		ShareTypes,
	],

	props: {
		column: {
			type: Object,
			default: () => { },
		},
		rowId: {
			type: Number,
			default: null,
		},
		value: {
			type: Array,
			default: () => [],
		},
	},

	data() {
		return {
			editValue: [],
			selectUsers: this.column?.usergroupSelectUsers ?? true,
			selectGroups: this.column?.usergroupSelectGroups ?? false,
			selectCircles: false,
			isInitialEditClick: false,
			hoverCardOpen: {},
			contactsMenuActions: {},
			contactsMenuLoading: {},
		}
	},

	computed: {
		isCurrentUser() {
			return (item) => this.isUser(item) && item.id === currentUser?.uid
		},
		isUser() {
			return (item) => item.type === USERGROUP_TYPE.USER
		},
	},

	watch: {
		hoverCardOpen: {
			deep: true,
			handler(openStates) {
				Object.entries(openStates)
					.filter(([userId, isOpen]) => isOpen && !(userId in this.contactsMenuActions) && !this.contactsMenuLoading[userId])
					.forEach(([userId]) => this.loadContactsMenuActions(userId))
			},
		},
		isEditing(isEditing) {
			if (isEditing) {
				this.editValue = this.value ? [...this.value] : []

				// Add click outside listener after the current event loop
				// to avoid the same click that triggered editing from closing the editor
				this.$nextTick(() => {
					document.addEventListener('click', this.handleClickOutside)
				})
			} else {
				document.removeEventListener('click', this.handleClickOutside)
				this.isInitialEditClick = false
			}
		},
	},

	created() {
		this.selectCircles = this.isCirclesEnabled ? this.column?.usergroupSelectTeams ?? false : false
	},

	methods: {
		t,

		handleStartEditing(event) {
			this.isInitialEditClick = true
			this.startEditing()
			// Stop the event from propagating to avoid immediate click outside
			event.stopPropagation()
		},

		/**
		 * Load the contacts menu entries of a user, the same source the avatar's hover card uses,
		 * so the card in the cell offers the same actions (profile page, mail, Talk, ...)
		 *
		 * @param {string} userId the user to load the entries for
		 */
		async loadContactsMenuActions(userId) {
			this.contactsMenuLoading[userId] = true
			try {
				const response = await axios.post(generateUrl('contactsmenu/findOne'), { shareType: 0, shareWith: userId })
				const entry = response.data
				const serverActions = entry.topAction ? [entry.topAction, ...entry.actions] : entry.actions ?? []
				const actions = serverActions.map((action) => ({
					id: action.id,
					appId: action.appId,
					text: action.title,
					href: action.hyperlink,
					icon: action.icon,
				}))
				getEnabledContactsMenuActions(entry).forEach((action) => {
					actions.push({
						id: action.id,
						text: action.displayName(entry),
						iconSvg: action.iconSvg(entry),
						onClick: () => action.callback(entry),
					})
				})
				this.contactsMenuActions[userId] = actions
			} catch (e) {
				console.error('Could not load contacts menu actions', e)
				this.contactsMenuActions[userId] = null
			} finally {
				this.contactsMenuLoading[userId] = false
			}
		},

		getAvatarImage(item) {
			if (item.type === USERGROUP_TYPE.GROUP) {
				return 'icon-group'
			}
			if (item.type === USERGROUP_TYPE.CIRCLE) {
				return 'icon-circles'
			}
			return ''
		},

		formatResult(autocompleteResult) {
			return {
				id: autocompleteResult.id,
				type: this.getType(autocompleteResult.source),
				key: autocompleteResult.source + '-' + autocompleteResult.id,
				displayName: autocompleteResult.label,
			}
		},

		filterOutUnwantedItems(list) {
			return list
		},

		async saveChanges() {
			if (this.localLoading) {
				return
			}

			let newValue = []
			if (this.editValue !== null) {
				newValue = !Array.isArray(this.editValue) ? [this.editValue] : this.editValue
			}
			// If the column does not allow multiple items, limit to one item
			// in case we switched from multiple to single selection
			if (!this.column.usergroupMultipleItems) {
				newValue = newValue.slice(0, 1)
			}
			const success = await this.updateCellValue(newValue)

			if (!success) {
				this.cancelEdit()
			}

			this.localLoading = false
			this.isEditing = false
		},

		handleClickOutside(event) {
			if (this.isInitialEditClick) {
				this.isInitialEditClick = false
				return
			}

			if (this.$refs.editingContainer && !this.$refs.editingContainer.contains(event.target)) {
				this.saveChanges()
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.cell-usergroup {
	width: 100%;

	.non-edit-mode {
		cursor: pointer;
	}
}

:deep(.vs__dropdown-toggle) {
    border: var(--vs-border-width) var(--vs-border-style) var(--vs-border-color);
}

.table-cell-usergroup {
	display: flex;
	flex-wrap: wrap;
	padding: 10px;
}

.usergroup-entry {
	padding-inline-end: 10px;
}

.edit-mode {
	padding: 10px 0;

	.icon-loading-inline {
		margin-inline-start: 4px;
	}
}
</style>
