/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import debounce from 'debounce'
import { showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/style.css'
import ShareTypes from './shareTypesMixin.js'

export default {
	mixins: [ShareTypes],
	data() {
		return {
			query: '',
			loading: false,
			minSearchStringLength: 1,
			maxAutocompleteResults: 20,
			suggestions: [],
			recommendations: [],
			currentUserId: getCurrentUser().uid,
		}
	},
	computed: {
		isValidQuery() {
			return this.query?.trim() && this.query.length >= this.minSearchStringLength
		},
		options() {
			if (this.isValidQuery) {
				return this.suggestions
			}
			return this.recommendations
		},

		noResultText() {
			if (this.loading) {
				return t('tables', 'Searching …')
			}
			return t('tables', 'No elements found.')
		},

	},
	methods: {
		getShareTypes() {
			return [
				...(this.selectUsers ? [this.SHARE_TYPES.SHARE_TYPE_USER] : []),
				...(this.selectGroups ? [this.SHARE_TYPES.SHARE_TYPE_GROUP] : []),
				...(this.selectCircles && this.isCirclesEnabled ? [this.SHARE_TYPES.SHARE_TYPE_CIRCLE] : []),
			]
		},
		getShareTypeString() {
			const shareTypes = this.getShareTypes()
			const typeLabels = {
				[this.SHARE_TYPES.SHARE_TYPE_USER]: 'User',
				[this.SHARE_TYPES.SHARE_TYPE_GROUP]: 'Group',
				[this.SHARE_TYPES.SHARE_TYPE_CIRCLE]: 'Team',
			}

			const selectedLabels = shareTypes.map(type => typeLabels[type])
			return selectedLabels.length > 0
				? selectedLabels.join(' or ')
				: 'User, group or team'
		},
		getPlaceholder() {
			return t('tables', '{shareTypeString}...', { shareTypeString: this.getShareTypeString() })
		},
		async asyncFind(query) {
			this.query = query.trim()
			if (this.isValidQuery) {
				this.loading = true
				await this.debounceGetSuggestions(query)
			}
		},
		async getSuggestions(search) {
			this.loading = true
			const shareTypes = this.getShareTypes()
			let shareTypeQueryString = ''
			shareTypes.forEach(shareType => {
				shareTypeQueryString += `&shareTypes[]=${shareType}`
			})
			const url = generateOcsUrl(`core/autocomplete/get?search=${search}${shareTypeQueryString}&limit=${this.maxAutocompleteResults}`)

			try {
				const res = await axios.get(url)
				const rawSuggestions = res.data.ocs.data.map(result => {
					return this.formatResult(result)
				})
				this.suggestions = this.filterOutUnwantedItems(rawSuggestions)
				this.loading = false
			} catch (err) {
				console.debug(err)
				showError(t('tables', 'Failed to fetch {shareTypeString}', { shareTypeString: this.getShareTypeString().toLowerCase() }))
			}
		},

		debounceGetSuggestions: debounce(function(...args) {
			this.getSuggestions(...args)
		}, 300),

	},
}
