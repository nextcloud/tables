<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="row space-B">
		<h3>{{ shareHeading }}</h3>
		<NcSelectUsers
			v-model="selectedShare"
			style="width: 100%;"
			data-cy="shareFormSelect"
			:loading="loading"
			:options="options"
			:placeholder="selectPlaceholder"
			:aria-label-combobox="selectPlaceholder"
			@search="asyncFind" />
	</div>
</template>

<script>
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { NcSelectUsers } from '@nextcloud/vue'
import { mapState } from 'pinia'
import formatting from '../../../shared/mixins/formatting.js'
import ShareTypes from '../../../shared/mixins/shareTypesMixin.js'
import searchUserGroup from '../../../shared/mixins/searchUserGroup.js'
import { showError } from '@nextcloud/dialogs'
import { useTablesStore } from '../../../store/store.js'

export default {
	name: 'ShareForm',
	components: {
		NcSelectUsers,
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
		selectRemote: {
			type: Boolean,
			default: true,
		},
	},

	data() {
		return {
			selectedShare: null,
		}
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

		sourceToShareType() {
			return {
				users: this.SHARE_TYPES.SHARE_TYPE_USER,
				groups: this.SHARE_TYPES.SHARE_TYPE_GROUP,
				circles: this.SHARE_TYPES.SHARE_TYPE_CIRCLE,
				remotes: this.SHARE_TYPES.SHARE_TYPE_REMOTE,
			}
		},
	},

	watch: {
		selectedShare(share) {
			if (share) {
				this.addShare(share)
				this.selectedShare = null
			}
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
				[this.SHARE_TYPES.SHARE_TYPE_REMOTE]: 'remote',
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
			return {
				id: result.id,
				user: result.id,
				displayName: result.label,
				subname: result.shareWithDisplayNameUnique || result.subline || result.id,
				shareWith: result.id,
				shareType: this.sourceToShareType[result.source] ?? this.SHARE_TYPES.SHARE_TYPE_USER,
				isNoUser: !result.source.startsWith('users'),
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
			return {
				id: result.uuid || result.value.shareWith,
				user: result.uuid || result.value.shareWith,
				displayName: result.name || result.label,
				subname: result.value.shareWith,
				shareWith: result.value.shareWith,
				shareType: result.value.shareType,
				isNoUser: result.value.shareType !== this.SHARE_TYPES.SHARE_TYPE_USER,
				key: result.uuid || result.value.shareWith + '-' + result.value.shareType,
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
