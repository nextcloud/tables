<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="row space-B">
		<div class="resource-label">
			{{ t('tables', 'Select a table or view') }}
		</div>
		<NcSelect style="width: 100%;" :loading="loading" :options="options" :clear-on-select="true"
			:hide-selected="true" :placeholder="t('tables', 'Select a table or view')" :searchable="true"
			:get-option-key="(option) => option.key" label="title"
			:aria-label-combobox="t('tables', 'Select a table or view')" :preselect-first="true"
			:preserve-search="true" @search="asyncFind" @input="addResource">
			<template #no-options>
				{{ t('tables', 'No recommendations. Start typing.') }}
			</template>
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
			<template #noResult>
				{{ noResultText }}
			</template>
		</NcSelect>
	</div>
</template>

<script>
import debounce from 'debounce'
import { mapState } from 'pinia'
import { NcSelect } from '@nextcloud/vue'
import { useTablesStore } from '../../../store/store.js'
import { NODE_TYPE_TABLE, NODE_TYPE_VIEW } from '../../../shared/constants.ts'
import SearchAndSelectOption from '../../../views/partials/SearchAndSelectOption.vue'
import permissionsMixin from '../../../shared/components/ncTable/mixins/permissionsMixin.js'

export default {
	name: 'ResourceForm',
	components: {
		NcSelect,
		SearchAndSelectOption,
	},

	mixins: [permissionsMixin],

	props: {
		resources: {
			type: Array,
			default: () => ([]),
		},
	},

	data() {
		return {
			query: '',
			value: null,
			loading: false,
			minSearchStringLength: 1,
			maxAutocompleteResults: 20,
			suggestions: [],
		}
	},

	computed: {
		...mapState(useTablesStore, ['tables', 'views']),

		isValidQuery() {
			return this.query?.trim() && this.query.length >= this.minSearchStringLength
		},

		options() {
			if (this.isValidQuery) {
				return this.suggestions
			}
			return []
		},

		noResultText() {
			if (this.loading) {
				return t('tables', 'Searching …')
			}
			return t('tables', 'No elements found.')
		},
	},

	methods: {
		addResource(resource) {
			this.$emit('add', resource)
		},

		async asyncFind(query) {
			this.query = query.trim()
			if (this.isValidQuery) {
				this.loading = true
				await this.debounceGetSuggestions(query)
			}
		},

		async getSuggestions(searchTerm) {
			this.loading = true
			let filteredTables = this.tables.filter((table) => this.canManageTable(table))
			filteredTables = filteredTables.filter((table) => table.title.toLowerCase().includes(searchTerm.toLowerCase())
				&& !this.resources.find(t => t.nodeType === NODE_TYPE_TABLE && parseInt(t.id) === parseInt(table.id)))
			filteredTables = filteredTables.map(table => {
				return this.formatElementData(table, NODE_TYPE_TABLE, 'table-')
			})

			let filteredViews = this.views.filter((view) => this.canManageElement(view))
			filteredViews = filteredViews.filter((view) => view.title.toLowerCase().includes(searchTerm.toLowerCase())
				&& !this.resources.find(v => v.nodeType === NODE_TYPE_VIEW && parseInt(v.id) === parseInt(view.id)))
			filteredViews = filteredViews.map(view => {
				return this.formatElementData(view, NODE_TYPE_VIEW, 'view-')
			})
			this.loading = false
			this.suggestions = [...filteredTables, ...filteredViews].sort(this.sortByTitle)
		},

		formatElementData(element, nodeType, keyPrefix) {
			return {
				title: element.title,
				emoji: element.emoji,
				key: keyPrefix + element.id,
				nodeType,
				id: (element.id).toString(),
				ownerDisplayName: element.ownerDisplayName,
				owner: element.ownership,
				rowsCount: element.rowsCount,
				type: element.type,
				label: element.title,
				subline: '',
				permissionRead: true,
				permissionCreate: true,
				permissionUpdate: true,
				permissionDelete: false,
			}
		},

		sortByTitle(a, b) {
			if (a.title.toLowerCase() < b.title.toLowerCase()) return -1
			if (a.title.toLowerCase() > b.title.toLowerCase()) return 1
			return 0
		},

		debounceGetSuggestions: debounce(function(...args) {
			this.getSuggestions(...args)
		}, 300),

	},
}
</script>

<style lang="scss" scoped>
.resource-label {
	font-style: italic;
}
</style>
