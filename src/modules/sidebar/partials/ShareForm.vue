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
		<NcSelect id="ajax"
			style="width: 100%;"
			:clear-on-select="true"
			:hide-selected="true"
			:internal-search="false"
			:loading="loading"
			:options="options"
			:placeholder="t('tables', 'User or group name …')"
			:preselect-first="true"
			:preserve-search="true"
			:searchable="true"
			:user-select="true"
			:get-option-key="(option) => option.key"
			:aria-label-combobox="t('tables', 'User or group name …')"
			label="displayName"
			@search="asyncFind"
			@input="addShare">
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
import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import debounce from 'debounce'
import { NcSelect } from '@nextcloud/vue'
import { mapState } from 'vuex'
import formatting from '../../../shared/mixins/formatting.js'
import { SHARE_TYPES } from '../../../shared/constants.js'

export default {
	name: 'ShareForm',
	components: {
		NcSelect,
	},

	mixins: [formatting],

	props: {
		shares: {
			type: Array,
			default: () => ([]),
		},
	},

	data() {
		return {
			query: '',
			loading: false,

			minSearchStringLength: 1,
			maxAutocompleteResults: 20,

			// Search data
			recommendations: [],
			suggestions: [],
		}
	},

	computed: {
		...mapState(['tables', 'tablesLoading', 'showSidebar']),

		/**
		 * Is the search valid ?
		 *
		 * @return {boolean}
		 */
		isValidQuery() {
			return this.query && this.query.trim() !== '' && this.query.length > this.minSearchStringLength
		},

		/**
		 * Multiselect options. Recommendations by default,
		 * direct search when search query is valid.
		 * Filter out existing shares
		 *
		 * @return {Array}
		 */
		options() {
			const shareTypes = { 0: 'user', 1: 'group' }
			// const shares = [...this.userShares, ...this.groupShares]
			const shares = this.shares
			if (this.isValidQuery) {
				// Filter out existing shares
				return this.suggestions.filter(item => !shares.find(share => share.receiver === item.shareWith && share.receiverType === shareTypes[item.shareType]))
			}
			// Filter out existing shares
			return this.recommendations.filter(item => !shares.find(share => share.receiver === item.shareWith && share.receiverType === shareTypes[item.shareType]))
		},

		noResultText() {
			if (this.loading) {
				return t('tables', 'Searching …')
			}
			return t('tables', 'No elements found.')
		},
	},

	mounted() {
		this.getRecommendations()
	},

	methods: {
		addShare(share) {
			this.$emit('add', share)
		},

		async asyncFind(query) {
			// save current query to check if we display
			// recommendations or search results
			this.query = query.trim()
			if (this.isValidQuery) {
				// start loading now to have proper ux feedback
				// during the debounce
				this.loading = true
				await this.debounceGetSuggestions(query)
			}
		},

		/**
		 * Get suggestions
		 *
		 * @param {string} search the search query
		 */
		async getSuggestions(search) {
			this.loading = true

			const shareType = [
				SHARE_TYPES.SHARE_TYPE_USER,
				SHARE_TYPES.SHARE_TYPE_GROUP,
			]

			const request = await axios.get(generateOcsUrl('apps/files_sharing/api/v1/sharees'), {
				params: {
					format: 'json',
					itemType: 'file',
					search,
					perPage: this.maxAutocompleteResults,
					shareType,
				},
			})

			const data = request.data.ocs.data
			const exact = data.exact
			data.exact = [] // removing exact from general results

			// flatten array of arrays
			const rawExactSuggestions = Object.values(exact).reduce((arr, elem) => arr.concat(elem), [])
			const rawSuggestions = Object.values(data).reduce((arr, elem) => arr.concat(elem), [])

			// remove invalid data and format to user-select layout
			const exactSuggestions = this.filterOutUnwantedShares(rawExactSuggestions)
				.map(share => this.formatForMultiselect(share))
			// sort by type so we can get user&groups first...
				.sort((a, b) => a.shareType - b.shareType)
			const suggestions = this.filterOutUnwantedShares(rawSuggestions)
				.map(share => this.formatForMultiselect(share))
			// sort by type so we can get user&groups first...
				.sort((a, b) => a.shareType - b.shareType)

			this.suggestions = exactSuggestions.concat(suggestions)

			this.loading = false
			// console.info('suggestions', this.suggestions)
		},

		/**
		 * Debounce getSuggestions
		 *
		 * @param {...*} args the arguments
		 */
		debounceGetSuggestions: debounce(function(...args) {
			this.getSuggestions(...args)
		}, 300),

		/**
		 * Get the sharing recommendations
		 */
		async getRecommendations() {
			this.loading = true

			const request = await axios.get(generateOcsUrl('apps/files_sharing/api/v1/sharees_recommended'), {
				params: {
					format: 'json',
					itemType: 'file',
				},
			})

			const exact = request.data.ocs.data.exact

			// flatten array of arrays
			const rawRecommendations = Object.values(exact).reduce((arr, elem) => arr.concat(elem), [])

			// remove invalid data and format to user-select layout
			this.recommendations = this.filterOutUnwantedShares(rawRecommendations)
				.map(share => this.formatForMultiselect(share))

			this.loading = false
			console.info('recommendations', this.recommendations)
		},

		/**
		 * Filter out unwanted shares
		 *
		 * @param {object[]} shares the array of shares objects
		 * @return {object}
		 */
		filterOutUnwantedShares(shares) {
			return shares.reduce((arr, share) => {
				// only check proper objects
				if (typeof share !== 'object') {
					return arr
				}

				try {
					// filter out current user
					if (share.value.shareType === SHARE_TYPES.SHARE_TYPE_USER
							&& share.value.shareWith === getCurrentUser().uid) {
						return arr
					}

					// ALL GOOD
					// let's add the suggestion
					arr.push(share)
				} catch {
					return arr
				}
				return arr
			}, [])
		},

		/**
		 * Format shares for the multiselect options
		 *
		 * @param {object} result select entry item
		 * @return {object}
		 */
		formatForMultiselect(result) {
			return {
				shareWith: result.value.shareWith,
				shareType: result.value.shareType,
				user: result.uuid || result.value.shareWith,
				isNoUser: result.value.shareType !== SHARE_TYPES.SHARE_TYPE_USER,
				displayName: result.name || result.label,
				icon: this.shareTypeToIcon(result.value.shareType),
				// Vue unique binding to render within Multiselect's AvatarSelectOption
				key: result.uuid || result.value.shareWith + '-' + result.value.shareType + '-' + result.name || result.label,
			}
		},

		/**
		 * Get the icon based on the share type
		 *
		 * @param {number} type the share type
		 * @return {string} the icon class
		 */
		shareTypeToIcon(type) {
			switch (type) {
			case SHARE_TYPES.SHARE_TYPE_GUEST:
				// default is a user, other icons are here to differenciate
				// themselves from it, so let's not display the user icon
				// case SHARE_TYPES.SHARE_TYPE_REMOTE:
				// case SHARE_TYPES.SHARE_TYPE_USER:
				return 'icon-user'
			case SHARE_TYPES.SHARE_TYPE_REMOTE_GROUP:
			case SHARE_TYPES.SHARE_TYPE_GROUP:
				return 'icon-group'
			case SHARE_TYPES.SHARE_TYPE_EMAIL:
				return 'icon-mail'
			case SHARE_TYPES.SHARE_TYPE_CIRCLE:
				return 'icon-circle'
			case SHARE_TYPES.SHARE_TYPE_ROOM:
				return 'icon-room'

			default:
				return ''
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
