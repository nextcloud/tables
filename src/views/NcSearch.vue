<template>
	<NcSelect ref="search-select"
		v-model="selectedResult"
		class="smart-picker-search--select"
		input-id="search-select-input"
		label="title"
		:placeholder="mySearchPlaceholder"
		:options="options"
		:close-on-select="true"
		:filterable="false"
		:autoscroll="true"
		:loading="searching"
		@search="onSearchInput">
		<template #option="option">
			<div v-if="option.isRawLink" class="custom-option">
				<LinkVariantIcon class="option-simple-icon" :size="20" />
				<span class="option-text">
					{{ t('Raw link {options}', { options: option.resourceUrl }) }}
				</span>
			</div>
			<NcSearchResult v-else-if="option.resourceUrl"
				class="search-result"
				:entry="option"
				:query="searchQuery" />
			<span v-else-if="option.isCustomGroupTitle" class="custom-option group-name">
				<img v-if="provider.icon_url"
					class="provider-icon group-name-icon"
					:src="provider.icon_url">
				<span class="option-text">
					<strong>{{ option.title }}</strong>
				</span>
			</span>
			<span v-else-if="option.isMore" :class="{ 'custom-option': true }">
				<span v-if="option.isLoading" class="option-simple-icon icon-loading-small" />
				<DotsHorizontalIcon v-else class="option-simple-icon" :size="20" />
				<span class="option-text">
					{{ t('Load more "{options}""', { options: option.title }) }}
				</span>
			</span>
		</template>
		<template #no-options>
			{{ noOptionsText }}
		</template>
	</NcSelect>
</template>

<script>
import NcSearchResult from './NcSearchResult.vue'
import { delay } from './utils.js'
import { NcSelect } from '@nextcloud/vue'

import { translate as t } from '@nextcloud/l10n'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

import DotsHorizontalIcon from 'vue-material-design-icons/DotsHorizontal.vue'
import LinkVariantIcon from 'vue-material-design-icons/LinkVariant.vue'

const LIMIT = 5

export default {
	name: 'NcSearch',
	components: {
		LinkVariantIcon,
		DotsHorizontalIcon,
		NcSelect,
		NcSearchResult,
	},
	props: {
		provider: {
			type: Object,
			required: true,
		},
		selection: {
			type: Object,
			default: null,
		},
		searchPlaceholder: {
			type: String,
			default: null,
		},
	},
	emits: [
		'submit',
	],
	data() {
		return {
			loading: false,
			searchQuery: '',
			selectedResult: this.selection,
			resultsBySearchProvider: {},
			searching: false,
			abortController: null,
			noOptionsText: t('Start typing to search'),
			providerIconAlt: t('Provider icon'),
			referenceType: 'link',
		}
	},
	computed: {
		mySearchPlaceholder() {
			return this.searchPlaceholder || t('Search')
		},
		searchProviderIds() {
			return this.provider.search_providers_ids
		},
		options() {
			if (this.searchQuery === '') {
				return []
			}
			const options = this.formattedSearchResults
			console.debug(options)
			return options
		},
		formattedSearchResults() {
			const results = []
			this.searchProviderIds.forEach(pid => {
				if (this.resultsBySearchProvider[pid].entries.length > 0) {
					// don't show group name entry if there is only one search provider and one result
					const providerEntriesWithId = this.resultsBySearchProvider[pid].entries.map((entry, index) => {
						return {
							id: 'provider-' + pid + '-entry-' + index,
							...entry,
						}
					})
					results.push(...providerEntriesWithId)
				}
			})
			return results
		},
	},
	watch: {
		selectedResult() {
			this.$emit('update:selection', this.selectedResult)
		},
	},
	mounted() {
		this.resetResults()
	},
	beforeDestroy() {
		this.cancelSearchRequests()
	},
	methods: {
		selectReference() {

		},
		t,
		resetResults() {
			const resultsBySearchProvider = {}
			this.searchProviderIds.forEach(pid => {
				resultsBySearchProvider[pid] = {
					entries: [],
				}
			})
			this.resultsBySearchProvider = resultsBySearchProvider
		},
		focus() {
			setTimeout(() => {
				this.$refs['search-select']?.$el?.querySelector('#search-select-input')?.focus()
			}, 300)
		},
		cancelSearchRequests() {
			if (this.abortController) {
				this.abortController.abort()
			}
		},
		onSearchInput(query, loading) {
			this.searchQuery = query
			delay(() => {
				this.updateSearch()
			}, 500)()
		},
		updateSearch() {
			this.cancelSearchRequests()
			this.resetResults()
			if (this.searchQuery === '') {
				this.searching = false
				return
			}

			return this.searchProviders()
		},
		searchProviders(searchProviderId = null) {
			this.abortController = new AbortController()
			this.searching = true

			const searchPromises = searchProviderId === null
				? [...this.searchProviderIds].map(pid => {
					return this.searchOneProvider(pid)
				})
				: [this.searchOneProvider(searchProviderId, this.resultsBySearchProvider[searchProviderId]?.cursor ?? null)]
			// fake one to have a request error
			// searchPromises.push(this.searchOneProvider('nopid'))

			return Promise.allSettled(searchPromises)
				.then((promises) => {
					const isOneCanceled = !!promises.find(p => {
						return p.status === 'rejected' && (p.reason.name === 'CanceledError' || p.reason.code === 'ERR_CANCELED')
					})
					// nothing was canceled: not searching
					if (!isOneCanceled) {
						this.searching = false
					}
				})
		},
		searchOneProvider(providerId, cursor = null) {
			const url = cursor === null
				? generateOcsUrl('search/providers/{providerId}/search?term={term}&limit={limit}', { providerId, term: this.searchQuery, limit: LIMIT })
				: generateOcsUrl('search/providers/{providerId}/search?term={term}&limit={limit}&cursor={cursor}', { providerId, term: this.searchQuery, limit: LIMIT, cursor })
			return axios.get(url, {
				signal: this.abortController.signal,
			})
				.then((response) => {
					const data = response.data.ocs.data
					this.resultsBySearchProvider[providerId].name = data.name
					this.resultsBySearchProvider[providerId].cursor = data.cursor
					this.resultsBySearchProvider[providerId].entries.push(...data.entries)
				})
		},
	},
}
</script>

<style lang="scss" scoped>
.smart-picker-search--select {
	width: 100%;

	.search-result {
		width: 100%;
	}

	.group-name-icon,
	.option-simple-icon {
		width: 20px;
		height: 20px;
		margin: 0 20px 0 10px;
	}

	.custom-option {
		height: 44px;
		display: flex;
		align-items: center;
		overflow: hidden;
	}

	.option-text {
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
}
</style>
