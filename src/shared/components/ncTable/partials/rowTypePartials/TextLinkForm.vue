<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<RowFormWrapper :title="column.title" :mandatory="isMandatory(column)" :description="column.description" :loading="isLoadingResults">
		<div class="row">
			<div v-if="providers?.length === 0" class="col-4">
				<NcNoteCard type="info">
					{{ t('tables', 'You can not insert any links in this field. Please configure at least one link provider in the column configuration.') }}
				</NcNoteCard>
			</div>
			<div class="link-input">
				<NcTextField v-if="isPlainUrl" :value.sync="plainLink" :placeholder="t('tables', 'URL')" />
				<NcSelect v-else v-model="localValue"
					:options="results"
					:clearable="true"
					label="title"
					:disabled="column.viewColumnInformation?.readonly"
					:aria-label-combobox="t('tables', 'Link providers')"
					style="width: 100%"
					@search="v => term = v">
					<template #option="props">
						<LinkWidget :thumbnail-url="props.thumbnailUrl" :icon-url="props.icon" :title="props.title" :subline="props.subline" :icon-size="40" />
					</template>
					<template #selected-option="props">
						<LinkWidget :thumbnail-url="props.thumbnailUrl" :icon-url="props.icon" :title="props.title" :icon-size="24" />
					</template>
				</NcSelect>
				<NcButton type="tertiary" :disabled="!localValue" :title="t('tables', 'Copy link')" @click="copyLink">
					<template #icon>
						<ContentCopy v-if="!copied" :size="20" />
						<ClipboardCheckMultipleOutline v-else :size="20" />
					</template>
				</NcButton>
				<NcButton type="tertiary" :disabled="!localValue" :title="t('tables', 'Open link')" @click="openLink">
					<template #icon>
						<OpenInNew :size="20" />
					</template>
				</NcButton>
			</div>
			<NcReferenceList :text="localValue?.value" :limit="1" :interactive="false" />
		</div>
	</RowFormWrapper>
</template>

<script>
import ContentCopy from 'vue-material-design-icons/ContentCopy.vue'
import ClipboardCheckMultipleOutline from 'vue-material-design-icons/ClipboardCheckMultipleOutline.vue'
import OpenInNew from 'vue-material-design-icons/OpenInNew.vue'
import { NcReferenceList } from '@nextcloud/vue/dist/Components/NcRichText.js'
import RowFormWrapper from './RowFormWrapper.vue'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import displayError from '../../../../utils/displayError.js'
import { NcButton, NcTextField, NcNoteCard, NcSelect } from '@nextcloud/vue'
import debounce from 'debounce'
import generalHelper from '../../../../mixins/generalHelper.js'
import copyToClipboard from '../../../../mixins/copyToClipboard.js'
import rowHelper from '../../../../components/ncTable/mixins/rowHelper.js'
import LinkWidget from '../LinkWidget.vue'
import { translate as t } from '@nextcloud/l10n'

export default {

	components: {
		ContentCopy,
		ClipboardCheckMultipleOutline,
		OpenInNew,
		NcButton,
		NcNoteCard,
		NcSelect,
		NcReferenceList,
		NcTextField,
		RowFormWrapper,
		LinkWidget,
	},

	mixins: [generalHelper, copyToClipboard, rowHelper],

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
			copied: false,
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
				if (this.isValidUrl(term)) {
					this.addUrlResult(term)
				}
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

		isValidUrl(string) {
			try {
				return new URL(string)
			} catch (err) {
				return false
			}
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

		copyLink() {
			if (this.localValue) {
				if (this.copied !== false) {
					clearTimeout(this.copied)
				}

				this.copied = this.copyToClipboard(this.localValue.value)
					? setTimeout(() => {
						this.copied = false
					}, 3000)
					: false
			}
		},

		openLink() {
			if (this.localValue) {
				window.open(this.localValue.value, '_blank')
			}
		},
	},
}
</script>
<style lang="scss" scoped>
.link-input {
	display: flex;
	align-items: center;
	margin-bottom: var(--default-grid-baseline);

	:deep(.v-select:not(.vs--open) .vs__search) {
		position: absolute;
	}

	:deep(.vs__selected) {
		flex-grow: 1;
		height: auto !important;
	}
}
</style>
