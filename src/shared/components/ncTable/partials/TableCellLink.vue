<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="cell-link">
		<div v-if="!isEditing" class="non-edit-mode"
			@click="handleStartEditing"
			@keydown.enter="handleStartEditing"
			@keydown.space.prevent="handleStartEditing">
			<LinkWidget :thumbnail-url="getValueObject.thumbnailUrl"
				:icon-url="getValueObject.icon"
				:title="getValueObject.title"
				:subline="getValueObject.subline"
				:url="getValueObject.resourceUrl"
				:truncate-length="30"
				:icon-size="25"
				:hide-default-icon="true"
				:underline-title="true" />
		</div>
		<div v-else
			ref="editingContainer"
			class="edit-mode"
			tabindex="0"
			@keydown.enter="saveChanges"
			@keydown.escape="cancelEdit">
			<div class="link-input">
				<NcTextField v-if="isPlainUrl"
					v-model="plainLink"
					:placeholder="t('tables', 'URL')"
					:aria-label="t('tables', 'URL')"
					:disabled="localLoading || !canEditCell()" />
				<NcSelect v-else
					v-model="editValue"
					:options="results"
					:clearable="true"
					label="title"
					:aria-label-combobox="t('tables', 'Link providers')"
					:loading="isLoadingResults || localLoading"
					:disabled="localLoading || !canEditCell()"
					style="width: 100%"
					@search="v => term = v">
					<template #option="props">
						<LinkWidget :thumbnail-url="props.thumbnailUrl" :icon-url="props.icon" :title="props.title" :subline="props.subline" :icon-size="40" />
					</template>
					<template #selected-option="props">
						<LinkWidget :thumbnail-url="props.thumbnailUrl" :icon-url="props.icon" :title="props.title" :icon-size="24" />
					</template>
				</NcSelect>
			</div>
			<div v-if="localLoading" class="loading-indicator">
				<div class="icon-loading-small icon-loading-inline" />
			</div>
		</div>
	</div>
</template>

<script>
import { NcTextField, NcSelect } from '@nextcloud/vue'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'
import debounce from 'debounce'
import generalHelper from '../../../mixins/generalHelper.js'
import cellEditMixin from '../mixins/cellEditMixin.js'
import rowHelper from '../mixins/rowHelper.js'
import displayError from '../../../utils/displayError.js'
import { showError } from '@nextcloud/dialogs'
import LinkWidget from './LinkWidget.vue'
import { ALLOWED_PROTOCOLS } from '../../../constants.ts'

export default {
	name: 'TableCellLink',

	components: {
		LinkWidget,
		NcTextField,
		NcSelect,
	},

	mixins: [generalHelper, cellEditMixin, rowHelper],

	props: {
		column: {
			type: Object,
			default: () => {},
		},
		rowId: {
			type: Number,
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
			isInitialEditClick: false,
			allowedProtocols: ALLOWED_PROTOCOLS,
		}
	},

	computed: {
		getValueObject() {
			if (this.hasJsonStructure(this.value)) {
				const valueObject = JSON.parse(this.value)
				delete valueObject.subline
				if (!valueObject.resourceUrl && valueObject.value) {
					valueObject.resourceUrl = valueObject.value
				}
				return valueObject || {}
			} else {
				return {
					thumbnailUrl: null,
					iconUrl: null,
					title: this.value,
					resourceUrl: this.value,
				}
			}
		},

		plainLink: {
			get() {
				return this.editValue?.value ?? ''
			},
			set(v) {
				this.editValue = {
					title: v,
					subline: t('tables', 'URL'),
					providerId: 'url',
					value: v,
				}
			},
		},

		isPlainUrl() {
			return this.providers?.length === 1 && this.providers[0] === 'url'
		},

		isLoadingResults() {
			for (const [, value] of Object.entries(this.providerLoading)) {
				if (value) {
					return true
				}
			}
			return false
		},

		hasInvalidProtocol() {
			if (!this.editValue) {
				return false
			}

			if (typeof this.editValue === 'string') {
				try {
					const parsedUrl = new URL(this.editValue)
					return !this.allowedProtocols.includes(parsedUrl.protocol)
				} catch (e) {
					return this.editValue.length > 0
				}
			}

			if (this.editValue?.value) {
				try {
					const parsedUrl = new URL(this.editValue.value)
					return !this.allowedProtocols.includes(parsedUrl.protocol)
				} catch (e) {
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
		isEditing(isEditing) {
			if (isEditing) {
				this.initEditMode()
				this.$nextTick(() => {
					document.addEventListener('click', this.handleClickOutside)
				})
			} else {
				document.removeEventListener('click', this.handleClickOutside)
				this.isInitialEditClick = false
			}
		},
	},

	mounted() {
		this.debounceSubmit = debounce(function() {
			this.loadResults()
		}, 500)

		if (this.column?.textAllowedPattern) {
			this.providers = this.column?.textAllowedPattern?.split(',')
		} else {
			this.providers = []
		}
	},

	methods: {
		t,

		handleStartEditing(event) {
			this.isInitialEditClick = true
			this.startEditing()
			if (event) {
				event.stopPropagation()
			}
		},

		initEditMode() {
			if (this.hasJsonStructure(this.value)) {
				this.editValue = JSON.parse(this.value)
			} else if (this.value) {
				this.editValue = {
					title: this.value,
					subline: t('tables', 'URL'),
					providerId: 'url',
					value: this.value,
				}
			} else {
				this.editValue = null
			}

			this.loadResults()

			this.$nextTick(() => {
				if (this.isPlainUrl) {
					const input = this.$el.querySelector('input[type="text"]')
					if (input) {
						input.focus()
						// Place cursor at the end for existing content
						if (this.plainLink) {
							input.setSelectionRange(this.plainLink.length, this.plainLink.length)
						}
					}
				} else {
					const selectInput = this.$el.querySelector('.vs__search')
					if (selectInput) {
						selectInput.focus()
					}
				}
			})
		},

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
				this.setProviderLoading(providerId, false)
				return
			}

			for (const item of res.data.ocs.data.entries) {
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

		async saveChanges() {
			if (this.localLoading) {
				return
			}

			if (this.hasInvalidProtocol) {
				showError(t('tables', 'Invalid protocol. Allowed: {allowed}', { allowed: this.allowedProtocols.join(', ') }))
				return
			}

			let newValue = null
			if (this.editValue !== null && this.editValue !== '') {
				newValue = JSON.stringify(this.editValue)
			}

			if (newValue === this.value) {
				this.isEditing = false
				return
			}

			const success = await this.updateCellValue(newValue)

			if (!success) {
				this.cancelEdit()
			}

			this.localLoading = false
			this.isEditing = false
		},

		handleClickOutside(event) {
			// Ignore the initial click that started editing
			if (this.isInitialEditClick) {
				this.isInitialEditClick = false
				return
			}

			if (this.$refs.editingContainer && !this.$refs.editingContainer.contains(event.target)) {
				this.saveChanges()
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.cell-link {
	width: 100%;

	.non-edit-mode {
		cursor: pointer;
		min-height: 20px;
		border-radius: var(--border-radius);
		padding: 2px 4px;
		transition: background-color 0.15s ease-in-out;

		&:hover, &:focus {
			background-color: var(--color-background-hover);
			outline: none;
		}

		&.has-content:hover, &.has-content:focus {
			background-color: var(--color-primary-element-light);
		}
	}

}

:deep(.vs__dropdown-toggle) {
    border: var(--vs-border-width) var(--vs-border-style) var(--vs-border-color);
}

.edit-mode {
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

	.icon-loading-inline {
		margin-inline-start: 4px;
	}
}
</style>
