<template>
	<RowFormWrapper :title="column.title" :mandatory="column.mandatory" :description="column.description" :loading="isLoadingResults">
		<div class="row">
			<div v-if="providers?.length === 0" class="col-4">
				<NcNoteCard type="info">
					{{ t('tables', 'You can not insert any links in this field. Please configure at least one link provider in the column configuration.') }}
				</NcNoteCard>
			</div>
			<div class="col-4">
				<NcTextField v-if="isPlainUrl" :value.sync="plainLink" :placeholder="t('tables', 'URL')" />
				<NcSelect v-else v-model="localValue"
					:options="results"
					:clearable="true"
					label="title"
					:aria-label-combobox="t('tables', 'Link providers')"
					style="width: 100%"
					@search="v => term = v">
					<template #option="props">
						<LinkWidget :thumbnail-url="props.thumbnailUrl" :icon-url="props.icon" :title="props.title" :subline="props.subline" :icon-size="40" />
					</template>
					<template #selected-option="props">
						<LinkWidget :thumbnail-url="props.thumbnailUrl" :icon-url="props.icon" :title="props.title" :subline="props.subline" :icon-size="40" />
					</template>
				</NcSelect>
			</div>
		</div>
	</RowFormWrapper>
</template>

<script>
import RowFormWrapper from './RowFormWrapper.vue'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import displayError from '../../../../utils/displayError.js'
import { NcTextField, NcNoteCard, NcSelect } from '@nextcloud/vue'
import debounce from 'debounce'
import generalHelper from '../../../../mixins/generalHelper.js'
import LinkWidget from '../LinkWidget.vue'
import { translate as t } from '@nextcloud/l10n'

export default {

	components: {
		NcTextField,
		NcNoteCard,
		RowFormWrapper,
		NcSelect,
		LinkWidget,
	},

	mixins: [generalHelper],

	props: {
		column: {
			type: Object,
			default: null,
		},
		value: {
			type: String,
			default: null,
		},
	},

	data() {
		return {
			providers: null,
			results: [],
			term: '',
			providerLoading: {},
		}
	},

	computed: {
		plainLink: {
			get() {
				return this.localValue?.value ?? ''
			},
			set(v) {
				this.$emit('update:value', JSON.stringify({
					title: v,
					subline: t('tables', 'URL'),
					providerId: 'url',
					value: v,
				}))
			},
		},
		localValue: {
			get() {
				// if we got an old value (string not object as json)
				if (this.value && !this.hasJsonStructure(this.value)) {
					return {
						title: this.value,
						subline: t('tables', 'URL'),
						providerId: 'url',
						value: this.value,
					}
				}

				return this.value ? JSON.parse(this.value) : null
			},
			set(v) {
				let value = null
				if (v !== null && v !== '') {
					value = JSON.stringify(v)
				}
				this.$emit('update:value', value)
			},
		},
		isPlainUrl() {
			return this.providers?.length === 1 && this.providers[0] === 'url'
		},
		isLoadingResults() {
			for (const [key, value] of Object.entries(this.providerLoading)) {
				console.debug('is still loading results at least for: ' + key, value)
				if (value) {
					return true
				}
			}
			return false
		},
	},

	watch: {
		term() {
			this.debounceSubmit()
		},
	},

	beforeMount() {
		if (this.localValue === null) {
			this.localValue = this.column.textDefault
		}

		this.debounceSubmit = debounce(function() {
			this.loadResults()
		}, 500)
	},

	mounted() {
		if (this.column?.textAllowedPattern) {
			this.providers = this.column?.textAllowedPattern?.split(',')
		} else {
			this.providers = []
		}
	},

	methods: {
		t,
		setProviderLoading(providerId, status) {
			this.providerLoading[providerId] = !!status
			this.providerLoading = { ...this.providerLoading }
		},

		loadResults() {
			if (this.term.length >= 3 || this.term === '') {
				this.providers?.forEach(provider => this.loadResultsForProvider(provider, this.term))
			}
		},

		async loadResultsForProvider(providerId, term) {
			if (term === null || term === '') {
				this.results = []
				this.providerLoading = {}
				return
			}

			this.setProviderLoading(providerId, true)

			this.removeResultsByProviderId(providerId)

			if (providerId === 'url') {
				this.addUrlResult(term)
				this.setProviderLoading(providerId, false)
				return
			}

			let res = null
			try {
				res = await axios.get(generateOcsUrl('/search/providers/' + providerId + '/search?term=' + term))
			} catch (e) {
				displayError(e, t('tables', 'Could not load link provider results.'))
				return
			}
			for (const item of res.data.ocs.data.entries) {
				// remove previews for thumbnail and icons if they can not be fetched from the server
				// depending on the server configuration if previews are allowed or not
				if (item.thumbnailUrl && !await this.isUrlReachable(item.thumbnailUrl)) {
					delete item.thumbnailUrl
				}
				if (item.icon && !await this.isUrlReachable(item.icon)) {
					delete item.icon
				}

				// add needed general data
				item.providerId = providerId
				item.subline = res.data?.ocs?.data?.name
				item.value = item.resourceUrl
			}
			this.results = this.results.concat(res.data?.ocs?.data?.entries)
			this.setProviderLoading(providerId, false)
		},

		addUrlResult(term) {
			this.results.push({
				title: term,
				subline: t('tables', 'Url'),
				providerId: 'url',
				value: term,
			})
		},

		removeResultsByProviderId(providerId) {
			this.results = this.results.filter(item => item.providerId !== providerId)
		},
	},
}
</script>
