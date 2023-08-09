<template>
	<div style="width: 100%">
		<div v-if="loading" class="icon-loading" />

		<!-- default -->
		<div v-if="!loading" class="row space-T">
			<div class="fix-col-4">
				{{ t('tables', 'Allowed types') }}
			</div>
			<div class="col-4 space-B typeSelection">
				<NcCheckboxRadioSwitch v-for="provider in getProviders" :key="provider.id" :checked.sync="provider.active" type="switch">
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
import { NcCheckboxRadioSwitch } from '@nextcloud/vue'
import axios from '@nextcloud/axios'
import displayError from '../../../../../utils/displayError.js'
import { generateOcsUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'

export default {

	components: {
		NcCheckboxRadioSwitch,
	},
	props: {
		column: {
			type: Object,
			default: null,
		},
	},
	data() {
		return {
			mutableColumn: this.column,
			loading: false,
			providers: [
				{
					id: 'url',
					label: t('tables', 'Url'),
					active: true,
				},
			],
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
	},

	watch: {
		getSelectedProviderIds() {
			this.mutableColumn.textAllowedPattern = this.getSelectedProviderIds.join(',')
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
			res.data?.ocs?.data?.forEach(item => {
				this.providers.push(
					{
						id: item.id,
						label: item.name,
						active: this.isActive(item.id),
					}
				)
			})
			this.providers.sort((a, b) => {
				return b.active - a.active
			})
		},
		isActive(providerId) {
			if (this.column.textAllowedPattern) {
				const selectedProviders = this.column.textAllowedPattern.split(',')
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
		padding-left: calc(var(--default-grid-baseline) * 4);
		overflow-y: auto;
		overflow-x: hidden;
	}

	.typeSelection > :deep(span) {
		width: 49%;
	}

</style>
