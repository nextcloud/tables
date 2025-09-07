<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		<NcSelect
			id="smartpicker-select"
			v-model="localValue"
			:loading="loading"
			:placeholder="t('tables', 'Search for tables and views...')"
			:aria-label-combobox="t('tables', 'Search for tables and views...')"
			:options="getOptions"
			@search="v => term = v">
			<template #option="props">
				<SearchAndSelectOption
					:label="props.label"
					:emoji="props.emoji"
					:owner="props.owner"
					:owner-display-name="props.ownerDisplayName"
					:rows-count="props.rowsCount"
					:type="props.type"
					:subline="props.subline" />
			</template>
			<template #selected-option="props">
				<SearchAndSelectOption
					:label="props.label"
					:emoji="props.emoji"
					:owner="props.owner"
					:owner-display-name="props.ownerDisplayName"
					:rows-count="props.rowsCount"
					:subline="props.subline"
					:type="props.type" />
			</template>
		</NcSelect>
	</div>
</template>

<script>
import { NcSelect } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'
import SearchAndSelectOption from '../partials/SearchAndSelectOption.vue'
import debounce from 'debounce'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import displayError from '../../shared/utils/displayError.js'

export default {

	components: {
		NcSelect,
		SearchAndSelectOption,
	},

	props: {
		value: {
		      type: Object,
		      default: null,
		    },
	},

	data() {
		return {
			loading: false,
			term: null,
			options: [],
		}
	},

	computed: {
		getOptions() {
			return [...this.options].sort((a, b) => a.quality - b.quality)
		},
		localValue: {
			get() {
				return this.value
			},
			set(v) {
				this.$emit('update:value', v)
			},
		},
	},

	watch: {
		term() {
			this.debounceSubmit()
		},
	},

	methods: {
		t,
		async loadResultsFromBE() {
			this.loading = true

			try {
				const res = await axios.get(generateUrl('/apps/tables/search/all?term=' + this.term))
				console.debug('res', res)
				this.options = res.data
			} catch (e) {
				displayError(e, t('tables', 'Could not load search results.'))
			}

			this.loading = false
		},
		async load() {
			if (this.term) {
				await this.loadResultsFromBE()
			}
		},
		debounceSubmit: debounce(function() {
			this.load()
		}, 500),
	},

}
</script>

<style lang="scss" scoped>

	:deep(.v-select.select) {
		width: 100%;
	}

	:deep(#smartpicker-select input[type=search]) {
		height: 41px;
	}

	:deep(#smartpicker-select .details) {
		display: inline-flex;
		align-items: self-start;
	}

	:deep(#smartpicker-select .vs__selected-options) {
		padding-top: calc(var(--default-grid-baseline) * 3);
		padding-bottom: calc(var(--default-grid-baseline) * 3);
	}

	:deep(#smartpicker-select .vs__selected) {
		margin-inline-start: 0;
	}

</style>
