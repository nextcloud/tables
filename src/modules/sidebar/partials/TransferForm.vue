<template>
	<div class="row space-B">
		<h3>{{ t('tables', 'Share with accounts') }}</h3>
		<NcSelect id="transfer-ownership-select" style="width: 100%;" :loading="loading" :options="options"
			:placeholder="t('tables', 'User…')" :searchable="true" label="displayName" @search="asyncFind">
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
import ShareTypes from '../mixins/shareTypesMixin.js'

export default {
	name: 'TransferForm',
	components: {
		NcSelect,
	},

	mixins: [ShareTypes, formatting],

	props: {
		newOwnerUserId: {
			type: String,
			default: '',
		},
	},

	data() {
		return {
			query: '',
			loading: false,
			minSearchStringLength: 1,
			maxAutocompleteResults: 20,
			recommendations: [],
			suggestions: [],
		}
	},

	computed: {
		...mapState(['tables', 'tablesLoading']),

		isValidQuery() {
			return this.query && this.query.trim() !== '' && this.query.length > this.minSearchStringLength
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

	mounted() {
		this.getRecommendations()
	},

	methods: {
		addShare(id) {
			this.newOwnerUserId = id
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

			const shareType = [
				this.SHARE_TYPES.SHARE_TYPE_USER,
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
			data.exact = []

			const rawExactSuggestions = Object.values(exact).reduce((arr, elem) => arr.concat(elem), [])
			const rawSuggestions = Object.values(data).reduce((arr, elem) => arr.concat(elem), [])

			const exactSuggestions = this.filterOutCurrentUser(rawExactSuggestions)
				.map(share => this.formatForSelect(share))
			const suggestions = this.filterOutCurrentUser(rawSuggestions)
				.map(share => this.formatForSelect(share))

			this.suggestions = exactSuggestions.concat(suggestions)

			this.loading = false
			console.info('suggestions', this.suggestions)
		},

		debounceGetSuggestions: debounce(function (...args) {
			this.getSuggestions(...args)
		}, 300),

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

			this.recommendations = this.filterOutCurrentUser(rawRecommendations)
				.map(share => this.formatForSelect(share))

			this.loading = false
			console.info('recommendations', this.recommendations)
		},


		filterOutCurrentUser(shares) {
			return shares.reduce((arr, share) => {
				if (typeof share !== 'object') {
					return arr
				}
				try {
					if (share.value.shareWith === getCurrentUser().uid) {
						return arr
					}
					arr.push(share)
				} catch {
					return arr
				}
				return arr
			}, [])
		},

		formatForSelect(result) {
			return {
				user: result.uuid || result.value.shareWith,
				displayName: result.name || result.label,
				icon: 'icon-user',
				key: result.uuid || result.label,
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
