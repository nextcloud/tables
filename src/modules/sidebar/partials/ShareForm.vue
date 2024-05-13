<!--
	- Parts from this code were taken from the form app, many thanks to the authors!
	-
  - @copyright Copyright (c) 2018 René Gieling <github@dartcafe.de>
  -
  - @author René Gieling <github@dartcafe.de>
  -
  - @license AGPL-3.0-or-later
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program.  If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<div class="row space-B">
		<h3>{{ t('tables', 'Share with accounts or groups') }}</h3>
		<NcSelect id="ajax" style="width: 100%;" :clear-on-select="true" :hide-selected="true" :internal-search="false"
			:loading="loading" :options="options" :placeholder="t('tables', 'User or group name …')"
			:preselect-first="true" :preserve-search="true" :searchable="true" :user-select="true"
			:get-option-key="(option) => option.key" :aria-label-combobox="t('tables', 'User or group name …')"
			label="displayName" @search="asyncFind" @input="addShare">
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
import { mapState } from 'vuex'
import formatting from '../../../shared/mixins/formatting.js'
import ShareTypes from '../../../shared/mixins/shareTypesMixin.js'
import searchUserGroup from '../../../shared/mixins/searchUserGroup.js'
import { showError } from '@nextcloud/dialogs'

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
	},

	computed: {
		...mapState(['tables', 'tablesLoading', 'showSidebar']),
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
			const shareTypes = { 0: 'user', 1: 'group' }

			// Filter out current user and sort
			items = items.filter((item) => !(item.shareType === this.SHARE_TYPES.SHARE_TYPE_USER && item.shareWith === this.currentUserId)).sort((a, b) => a.shareType - b.shareType)

			// Filter out non-valid share types
			items = items.filter((item) => (shareTypesList.includes(item.shareType)))

			// Filter out existing shares
			return items.filter(item => !this.shares.find(share => share.receiver === item.shareWith && share.receiverType === shareTypes[item.shareType]))
		},

		formatResult(result) {
			return {
				shareWith: result.id,
				shareType: result.source.startsWith('users') ? this.SHARE_TYPES.SHARE_TYPE_USER : this.SHARE_TYPES.SHARE_TYPE_GROUP,
				user: result.id,
				isNoUser: !result.source.startsWith('users'),
				displayName: result.label,
				icon: result.icon || result.source.startsWith('users') ? 'icon-user' : 'icon-group',
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
				shareWith: result.value.shareWith,
				shareType: result.value.shareType,
				user: result.uuid || result.value.shareWith,
				isNoUser: result.value.shareType !== SHARE_TYPES.SHARE_TYPE_USER,
				displayName: result.name || result.label,
				icon: result.value.shareType === this.SHARE_TYPES.SHARE_TYPE_USER ? 'icon-user' : 'icon-group',
				// Vue unique binding to render within Multiselect's AvatarSelectOption
				key: result.uuid || result.value.shareWith + '-' + result.value.shareType + '-' + result.name || result.label,
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
