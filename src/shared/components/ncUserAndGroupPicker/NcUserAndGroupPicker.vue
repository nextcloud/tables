<template>
	<div class="row space-B">
		<h3>{{ t('tables', 'Transfer this table to another user') }}</h3>
		<NcSelect id="transfer-ownership-select" v-model="value" style="width: 100%;" :loading="loading" :options="options" :placeholder="t('tables', 'User…')"
			:searchable="true" :get-option-key="(option) => option.key"
			label="displayName" :user-select="true"
			@search="asyncFind" @input="addTransfer">
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
import formatting from '../../../shared/mixins/formatting.js'
import ShareTypes from '../../mixins/shareTypesMixin.js'

export default {

	components: {
		NcSelect,
	},

	mixins: [ShareTypes, formatting],

	props: {
		newOwnerUserId: {
			type: String,
			default: '',
		},
		selectUsers: {
			type: Boolean,
			default: true,
		},
		selectGroups: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			query: '',
			value: '',
			loading: false,
			minSearchStringLength: 1,
			maxAutocompleteResults: 20,
			suggestions: [],
		}
	},

	computed: {
		localValue: {
			get() {
				return this.newOwnerUserId
			},
			set(v) {
				console.info('newOwnerUserId set to ', v)
				this.$emit('update:newOwnerUserId', v)
			},
		},

		isValidQuery() {
			return this.query && this.query.trim() !== '' && this.query.length > this.minSearchStringLength
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
		addTransfer(selectedItem) {
			if (selectedItem) {
				this.localValue = selectedItem.user
			} else {
				this.localValue = ''
			}
		},

		getShareTypes() {
			if (this.selectUsers && this.selectGroups) {
				return [
					this.SHARE_TYPES.SHARE_TYPE_USER,
					this.SHARE_TYPES.SHARE_TYPE_GROUP,
				]
			} else if (this.selectUsers) {
				return [
					this.SHARE_TYPES.SHARE_TYPE_USER,
				]
			} else if (this.selectGroups) {
				return [
					this.SHARE_TYPES.SHARE_TYPE_GROUP,
				]
			} else {
				return []
			}
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
			const url = generateOcsUrl('core/autocomplete/get?search={searchQuery}&itemType=%20&itemId=%20{shareTypeQueryString}&limit={limit}', { searchQuery: search, shareTypeQueryString, limit: this.maxAutocompleteResults })
			await axios.get(url).then(res => {
				const rawSuggestions = res.data.ocs.data.map(autocompleteResult => {
					return {
						user: autocompleteResult.id,
						displayName: autocompleteResult.label,
						icon: autocompleteResult.icon,
						isUser: autocompleteResult.source.startsWith('users'),
						key: autocompleteResult.source + '-' + autocompleteResult.id,
					}
				})

				this.suggestions = this.filterOutCurrentUser(rawSuggestions)

				this.loading = false

			}).catch(err => {
				console.debug(err)
			})
		},

		debounceGetSuggestions: debounce(function(...args) {
			this.getSuggestions(...args)
		}, 300),

		filterOutCurrentUser(list) {
			return list.reduce((arr, item) => {
				if (typeof item !== 'object') {
					return arr
				}
				try {
					if (item.isUser && item.user === getCurrentUser().uid) {
						return arr
					}
					arr.push(item)
				} catch {
					return arr
				}
				return arr
			}, [])
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
