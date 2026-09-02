<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div style="width: 100%">
		<div v-if="loading" class="icon-loading" />

		<template v-else>
			<!-- default -->
			<div class="row space-T">
				<div class="fix-col-4">
					{{ t('tables', 'Allowed types') }}
				</div>
				<div v-if="!canSave" class="fix-col-4">
					<NcNoteCard type="warning">
						{{ t('tables', 'Please select at least one provider.') }}
					</NcNoteCard>
				</div>
				<div class="col-4 space-B typeSelection">
					<NcCheckboxRadioSwitch v-for="provider in getProviders" :key="provider.id" v-model="provider.active" type="switch" data-cy="selectionOptionLabel">
						{{ provider.label }}
					</NcCheckboxRadioSwitch>
				</div>
				<p class="span">
					{{ t('tables', 'The provided types depends on your system setup. You can use the same providers like the fulltext-search.') }}
				</p>
			</div>

			<div class="row space-T">
				<div class="fix-col-4">
					<NcCheckboxRadioSwitch v-model="showPreview"
						type="switch"
						data-cy="linkShowPreviewSwitch"
						:disabled="!canShowImagePreviews">
						{{ t('tables', 'Show image previews') }}
					</NcCheckboxRadioSwitch>
				</div>
				<p class="span">
					{{ imagePreviewDescription }}
				</p>
			</div>
			<div v-if="showPreview" class="row space-B">
				<div class="fix-col-4 title">
					{{ t('tables', 'Preview size') }}
				</div>
				<div class="fix-col-4" :class="{ error: isImagePreviewSizeInvalid }">
					<input
						v-model.number="imagePreviewSize"
						type="number"
						pattern="\d+"
						data-cy="linkImagePreviewSizeInput"
						:disabled="!canShowImagePreviews"
						:min="IMAGE_PREVIEW_SIZE_MIN"
						:max="IMAGE_PREVIEW_SIZE_MAX"
						:placeholder="t('tables', 'Enter a preview size between {min} and {max}', { min: IMAGE_PREVIEW_SIZE_MIN, max: IMAGE_PREVIEW_SIZE_MAX })">
				</div>
				<div v-if="isImagePreviewSizeInvalid" class="fix-col-4">
					<NcNoteCard type="warning">
						{{ t('tables', 'Preview size must be between {min} and {max} pixels.', { min: IMAGE_PREVIEW_SIZE_MIN, max: IMAGE_PREVIEW_SIZE_MAX }) }}
					</NcNoteCard>
				</div>
			</div>
		</template>
	</div>
</template>

<script>
import { NcCheckboxRadioSwitch, NcNoteCard } from '@nextcloud/vue'
import axios from '@nextcloud/axios'
import displayError from '../../../../../utils/displayError.js'
import { generateOcsUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'
import {
	IMAGE_PREVIEW_SIZE_DEFAULT,
	IMAGE_PREVIEW_SIZE_MAX,
	IMAGE_PREVIEW_SIZE_MIN,
} from '../../../../../constants.js'
import { isImagePreviewSizeValid } from '../../../../../utils/imagePreviewSize.js'

export default {

	components: {
		NcCheckboxRadioSwitch,
		NcNoteCard,
	},
	props: {
		column: {
			type: Object,
			default: null,
		},
		canSave: {
			type: Boolean,
			default: true,
		},
	},
	emits: [
		'update:can-save',
		'update:custom-settings',
	],
	data() {
		return {
			mutableColumn: this.column,
			showPreview: !!this.column?.customSettings?.showPreview,
			imagePreviewSize: this.column?.customSettings?.imagePreviewSize ?? IMAGE_PREVIEW_SIZE_DEFAULT,
			IMAGE_PREVIEW_SIZE_MIN,
			IMAGE_PREVIEW_SIZE_MAX,
			loading: false,
			providers: [],
			preActivatedProviders: [
				'url',
				'files',
				'contacts',
			],
		}
	},
	computed: {
		getProviders() {
			return this.providers
		},
		getSelectedProviderIds() {
			const activeProviderIds = []
			this.providers.filter(item => item.active === true).forEach(item => {
				activeProviderIds.push(item.id)
			})
			return activeProviderIds
		},
		canShowImagePreviews() {
			return this.providers.some(provider => provider.id === 'files' && provider.active === true)
		},
		imagePreviewDescription() {
			if (this.canShowImagePreviews) {
				return t('tables', 'Shows previews for image files selected from Files. Other links keep their title.')
			}

			return t('tables', 'Enable the Files provider to show image previews.')
		},
		isImagePreviewSizeInvalid() {
			return this.showPreview && !isImagePreviewSizeValid(this.imagePreviewSize)
		},
		error: {
			get() {
				return !this.canSave
			},
			set(v) {
				this.$emit('update:can-save', !v)
			},
		},
	},

	watch: {
		showPreview(v) {
			if (v && this.isImagePreviewSizeInvalid) {
				this.imagePreviewSize = IMAGE_PREVIEW_SIZE_DEFAULT
			}
			this.$emit('update:custom-settings', { showPreview: v, imagePreviewSize: this.imagePreviewSize })
		},
		imagePreviewSize(v) {
			this.$emit('update:custom-settings', { imagePreviewSize: v })
		},
		getSelectedProviderIds() {
			this.mutableColumn.textAllowedPattern = this.getSelectedProviderIds.join(',')
			if (!this.canShowImagePreviews && this.showPreview) {
				this.showPreview = false
			}
			if (this.getSelectedProviderIds?.length === 0) {
				this.error = true
			} else {
				this.error = false
			}
		},
		column() {
			this.mutableColumn = this.column
			this.showPreview = !!this.column?.customSettings?.showPreview
			this.imagePreviewSize = this.column?.customSettings?.imagePreviewSize ?? IMAGE_PREVIEW_SIZE_DEFAULT
		},
	},

	async mounted() {
		this.loading = true
		await this.loadProviders()
		if (!this.canShowImagePreviews && this.showPreview) {
			this.showPreview = false
		}
		this.loading = false
	},

	methods: {
		t,
		async loadProviders() {
			let res = null
			try {
				res = await axios.get(generateOcsUrl('/search/providers'))
			} catch (e) {
				displayError(e, t('tables', 'Could not load link providers.'))
				return
			}
			this.providers = [
				{
					id: 'url',
					label: t('tables', 'URL'),
					active: this.isActive('url'),
				},
			]
			res.data?.ocs?.data?.forEach(item => {
				this.providers.push(
					{
						id: item.id,
						label: item.name,
						active: this.isActive(item.id),
					},
				)
			})
			this.providers.sort((a, b) => {
				return b.active - a.active
			})
		},
		isActive(providerId) {
			if (this.column?.id) {
				const selectedProviders = this.column.textAllowedPattern?.split(',')
				return selectedProviders.indexOf(providerId) !== -1
			} else {
				return this.preActivatedProviders.indexOf(providerId) !== -1
			}
		},
	},
}
</script>
<style lang="scss" scoped>

	.typeSelection {
		display: inline-flex;
		flex-wrap: wrap;
		max-height: 137px;
		padding-inline-start: calc(var(--default-grid-baseline) * 4);
		overflow-y: auto;
		overflow-x: hidden;
	}

	.typeSelection > :deep(span) {
		width: 49%;
	}

</style>
