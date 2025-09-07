<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div style="width: 100%">
		<div v-if="loading" class="icon-loading" />

		<!-- default -->
		<div v-if="!loading" class="row space-T">
			<div class="fix-col-4">
				{{ t('tables', 'Allowed types') }}
			</div>
			<div v-if="!canSave" class="fix-col-4">
				<NcNoteCard type="warning">
					{{ t('tables', 'Please select at least one provider.') }}
				</NcNoteCard>
			</div>
			<div class="col-4 space-B typeSelection">
				<NcCheckboxRadioSwitch v-for="provider in getProviders" :key="provider.id" :checked.sync="provider.active" type="switch" data-cy="selectionOptionLabel">
					{{ provider.label }}
				</NcCheckboxRadioSwitch>
			</div>
			<p class="span">
				{{ t('tables', 'The provided types depends on your system setup. You can use the same providers like the fulltext-search.') }}
			</p>
		</div>
	</div>
</template>

<script>
import { NcCheckboxRadioSwitch, NcNoteCard } from '@nextcloud/vue'
import axios from '@nextcloud/axios'
import displayError from '../../../../../utils/displayError.js'
import { generateOcsUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'

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
	data() {
		return {
			mutableColumn: this.column,
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
		getSelectedProviderIds() {
			this.mutableColumn.textAllowedPattern = this.getSelectedProviderIds.join(',')
			if (this.getSelectedProviderIds?.length === 0) {
				this.error = true
			} else {
				this.error = false
			}
		},
		column() {
			this.mutableColumn = this.column
		},
	},

	async mounted() {
		this.loading = true
		await this.loadProviders()
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
