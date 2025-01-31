<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="row space-B">
		<h3>{{ shareHeading }}</h3>
		<NcSelect id="ajax" style="width: 100%;" :clear-on-select="true"
			data-cy="shareFormSelect" :hide-selected="true" :internal-search="false"
			:loading="loading" :options="options" :placeholder="selectPlaceholder" :preselect-first="true"
			:preserve-search="true" :searchable="true" :user-select="true" :get-option-key="(option) => option.key"
			:aria-label-combobox="selectPlaceholder" label="displayName" @search="asyncFind" @input="addShare">
			<template #no-options>
				{{ t('tables', 'No recommendations. Start typing.') }}
			</template>
			<template #noResult>
				{{ noResultText }}
			</template>
		</NcSelect>
	</div>
</template>

<script>
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { NcSelect } from '@nextcloud/vue'
import { mapState } from 'pinia'
import formatting from '../../../shared/mixins/formatting.js'
import ShareTypes from '../../../shared/mixins/shareTypesMixin.js'
import searchUserGroup from '../../../shared/mixins/searchUserGroup.js'
import { showError } from '@nextcloud/dialogs'
import { useTablesStore } from '../../../store/store.js'

export default {
	name: 'ShareForm',
	components: {
		NcSelect,
	},

	mixins: [formatting, ShareTypes, searchUserGroup],

	props: {
		shares: {
			type: Array,
			default: () => ([]),
		},
		selectUsers: {
			type: Boolean,
			default: true,
		},
		selectGroups: {
			type: Boolean,
			default: true,
		},
		selectCircles: {
			type: Boolean,
			default: true,
		},
	},

	computed: {
		...mapState(useTablesStore, ['tables', 'showSidebar', 'isLoadingSomething']),

		shareHeading() {
			return this.isCirclesEnabled
				? t('tables', 'Share with accounts, groups or teams')
				: t('tables', 'Share with accounts or groups')
		},

		selectPlaceholder() {
			return this.isCirclesEnabled
				? t('tables', 'User, group or team …')
				: t('tables', 'User or group …')
		},
	},

	mounted() {
		this.getRecommendations()
	},

	methods: {
		addShare(share) {
			this.$emit('add', share)
		},

		/**
		 * Get the sharing recommendations
		 */
		async getRecommendations() {
			this.loading = true

			try {
				const request = await axios.get(generateOcsUrl('apps/files_sharing/api/v1/sharees_recommended'), {
					params: {
						format: 'json',
						itemType: 'file',
					},
				})

				const exact = request.data.ocs.data.exact

				// flatten array of arrays
				let rawRecommendations = Object.values(exact).reduce((arr, elem) => arr.concat(elem), [])
				console.info('recommendations', rawRecommendations)

				rawRecommendations = rawRecommendations.map(result => {
					return this.formatRecommendations(result)
				})
				this.recommendations = this.filterOutUnwantedItems(rawRecommendations)
				this.loading = false
			} catch (err) {
				console.debug(err)
				showError(t('tables', 'Failed to fetch share recommendations'))
			}
		},

		filterOutUnwantedItems(items) {
			const shareTypesList = this.getShareTypes()
			const shareTypes = {
				[this.SHARE_TYPES.SHARE_TYPE_USER]: 'user',
				[this.SHARE_TYPES.SHARE_TYPE_GROUP]: 'group',
				...(this.isCirclesEnabled ? { [this.SHARE_TYPES.SHARE_TYPE_CIRCLE]: 'circle' } : {}),
			}

			// Filter out current user and sort
			items = items.filter((item) =>
				!(item.shareType === this.SHARE_TYPES.SHARE_TYPE_USER
				&& item.shareWith === this.currentUserId))
				.sort((a, b) => a.shareType - b.shareType)

			// Filter out non-valid share types and circles if disabled
			items = items.filter((item) => (
				shareTypesList.includes(item.shareType)
				&& (item.shareType !== this.SHARE_TYPES.SHARE_TYPE_CIRCLE || this.isCirclesEnabled)
			))

			// Filter out existing shares
			return items.filter(item =>
				!this.shares.find(share =>
					share.receiver === item.shareWith
					&& share.receiverType === shareTypes[item.shareType],
				),
			)
		},

		formatResult(result) {
			const isUser = result.source.startsWith('users')
			const isGroup = result.source.startsWith('groups')
			const isCircle = !isUser && !isGroup && this.isCirclesEnabled

			return {
				shareWith: result.id,
				shareType: isUser
					? this.SHARE_TYPES.SHARE_TYPE_USER
					: isGroup
						? this.SHARE_TYPES.SHARE_TYPE_GROUP
						: isCircle
							? this.SHARE_TYPES.SHARE_TYPE_CIRCLE
							: this.SHARE_TYPES.SHARE_TYPE_USER,
				user: result.id,
				isNoUser: !isUser,
				displayName: result.label,
				icon: isUser
					? 'icon-user'
					: isGroup
						? 'icon-group'
						: isCircle
							? 'icon-circles'
							: 'icon-user',
				key: result.source + '-' + result.id,
			}
		},

		/**
		 * Format shares for the multiselect options
		 *
		 * @param {object} result select entry item
		 * @return {object}
		 */
		formatRecommendations(result) {
			const isUser = result.value.shareType === this.SHARE_TYPES.SHARE_TYPE_USER
			const isGroup = result.value.shareType === this.SHARE_TYPES.SHARE_TYPE_GROUP
			const isCircle = !isUser && !isGroup && this.isCirclesEnabled

			return {
				shareWith: result.value.shareWith,
				shareType: result.value.shareType,
				user: result.uuid || result.value.shareWith,
				isNoUser: !isUser,
				displayName: result.name || result.label,
				icon: isUser
					? 'icon-user'
					: isGroup
						? 'icon-group'
						: isCircle
							? 'icon-circle'
							: 'icon-user',
				key: result.uuid || result.value.shareWith + '-'
					+ result.value.shareType + '-'
					+ (result.name || result.label),
			}
		},

	},
}
</script>

<style lang="scss" scoped>
.multiselect {
	width: 100% !important;
	max-width: 100% !important;
}
</style>
