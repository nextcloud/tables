<template>
	<div class="smart-picker-search" :class="{ 'with-empty-content': true }">
		<div class="picker-content">
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
				@search="onSearchInput"
				@input="onSelectResultSelected">
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
			<NcEmptyContent v-if="selectedResult === null"
				class="smart-picker-search--empty-content">
				<template #icon>
					<img v-if="provider.icon_url"
						class="provider-icon"
						:alt="providerIconAlt"
						:src="provider.icon_url">
					<LinkVariantIcon v-else />
				</template>
			</NcEmptyContent>
			<div v-else class="search-content">
				<div v-if="!viewObject" class="icon-loading" />
				<div v-else style="width:100%">
					<div class="reference-style">
						<NcCheckboxRadioSwitch :checked.sync="referenceType" value="link" type="radio" class="reference-option">
							{{ t('tables', 'Link to table') }}
						</NcCheckboxRadioSwitch>
						<NcCheckboxRadioSwitch :checked.sync="referenceType" value="content" type="radio" class="reference-option">
							{{ t('tables', 'Table with content') }}
						</NcCheckboxRadioSwitch>
						<NcCheckboxRadioSwitch :checked.sync="referenceType" value="row" type="radio" class="reference-option">
							{{ t('tables', 'Row from table') }}
						</NcCheckboxRadioSwitch>
					</div>
					<CustomTable
						v-if="referenceType != 'link'"
						:columns="viewObject.columns"
						:rows="viewObject.rows"
						:view="viewObject.view"
						:view-setting="{}"
						:read-only="true" />
				</div>
			</div>
		</div>
		<div class="select-button">
			<NcButton type="primary" :aria-label="t('tables', 'Select')" @click="selectReference">
				{{ t('tables', 'Select') }}
			</NcButton>
		</div>
	</div>
</template>

<script>
import NcSearchResult from './NcSearchResult.vue'
import { delay } from './utils.js'
import { NcEmptyContent, NcSelect, NcCheckboxRadioSwitch, NcButton } from '@nextcloud/vue'

import { translate as t } from '@nextcloud/l10n'

import axios from '@nextcloud/axios'
import { generateOcsUrl, generateUrl } from '@nextcloud/router'

import DotsHorizontalIcon from 'vue-material-design-icons/DotsHorizontal.vue'
import LinkVariantIcon from 'vue-material-design-icons/LinkVariant.vue'
import CustomTable from '../shared/components/ncTable/sections/CustomTable.vue'
import { MetaColumns } from '../shared/components/ncTable/mixins/metaColumns.js'
import { parseCol } from '../shared/components/ncTable/mixins/columnParser.js'

const LIMIT = 5

export default {
	name: 'NcSearch',
	components: {
		LinkVariantIcon,
		DotsHorizontalIcon,
		NcEmptyContent,
		NcSelect,
		NcSearchResult,
		CustomTable,
		NcCheckboxRadioSwitch,
		NcButton,
	},
	props: {
		/**
		 * The selected reference provider
		 */
		provider: {
			type: Object,
			required: true,
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
			selectedResult: null,
			viewObject: null,
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
		rawLinkEntry() {
			return {
				id: 'rawLinkEntry',
				resourceUrl: this.searchQuery,
				isRawLink: true,
			}
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
		onSelectResultSelected(item) {
			console.debug("Selected: ", item)
			this.viewObject = null
			this.loadView(item.resourceUrl)
			// if (item !== null) {
			// }
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

		async loadView(url) {
			console.debug("Loading view of url:", url, typeof url)
			const viewId = url.split('/').slice(-1)[0]
			const viewObject = {}
			viewObject.view = (await axios.get(generateUrl('/apps/tables/view/' + viewId))).data
			console.debug("View:", viewObject, viewObject.view)
			const columns = (await axios.get(generateUrl('/apps/tables/column/view/' + viewId))).data
			console.debug("Columns:", columns)
			const allColumns = columns.map(col => parseCol(col)).concat(MetaColumns.filter(col => viewObject.view.columns.includes(col.id)))
			viewObject.columns = allColumns.sort(function(a, b) {
				return viewObject.view.columns.indexOf(a.id) - viewObject.view.columns.indexOf(b.id)
			  })
			viewObject.rows = (await axios.get(generateUrl('/apps/tables/row/view/' + viewId))).data
			console.debug("->", viewObject)
			this.viewObject = viewObject
		},
	},
}
</script>

<style lang="scss" scoped>
.smart-picker-search {
	width: 100%;
	display: flex;
	flex-direction: column;
	padding: 0 16px 16px 16px;
	min-height: 400px;

	&--empty-content {
		margin-top: auto !important;
		margin-bottom: auto !important;
	}

	.provider-icon {
		width: 150px;
		height: 150px;
		object-fit: contain;
		filter: var(--background-invert-if-dark);
	}

	&--select {
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
}
.reference-style {
	display: flex;
	justify-content: space-between;
}
.search-content {
	padding: 16px;
}
.reference-option {
	padding: 4px 12px;

}
.select-button {
	padding-top: 16px;
	width: 100%;
	display: flex;
	align-items: end;
	flex-direction: column;
}
.picker-content {

}
</style>
