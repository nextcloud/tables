import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import debounce from 'debounce'
import { showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/style.css'

export default {
	data() {
		return {
			query: '',
			loading: false,
			minSearchStringLength: 1,
			maxAutocompleteResults: 20,
			suggestions: [],
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
			return []
		},

		noResultText() {
			if (this.loading) {
				return t('tables', 'Searching …')
			}
			return t('tables', 'No elements found.')
		},

		userId() {
			return getCurrentUser().uid
		},
	},
	methods: {
		getShareTypes() {
			const types = []
			if (this.selectUsers) {
				types.push(this.SHARE_TYPES.SHARE_TYPE_USER)
			}
			if (this.selectGroups) {
				types.push(this.SHARE_TYPES.SHARE_TYPE_GROUP)
			}
			return types
		},
		getShareTypeString() {
			if (this.selectUsers && !this.selectGroups) {
				return 'User'
			} else if (!this.selectUsers && this.selectGroups) {
				return 'Group'
			} else {
				return 'User or group'
			}
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
				const rawSuggestions = res.data.ocs.data.map(autocompleteResult => {
					return {
						user: autocompleteResult.id,
						displayName: autocompleteResult.label,
						icon: autocompleteResult.icon,
						isUser: autocompleteResult.source.startsWith('users'),
						key: autocompleteResult.source + '-' + autocompleteResult.id,
					}
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

		filterOutCurrentUser(list) {
			return list.filter((item) => !(item.isUser && item.user === getCurrentUser().uid))
		},
	},
}
