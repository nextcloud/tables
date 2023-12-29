<!--
  - @copyright Copyright (c) 2023 Florian Steffens <flost-dev@mailbox.org>
  -
  - @author 2023 Florian Steffens <flost-dev@mailbox.org>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -->
<template>
	<div>
		<NcSelect
			id="smartpicker-select"
			v-model="localValue"
			:loading="loading"
			:placeholder="t('tables', 'Search for table and views...')"
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

	mounted() {
		// this.loadResultsFromBE()
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
			if (this.term?.length >= 3) {
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
		height: 46px;
	}

</style>
