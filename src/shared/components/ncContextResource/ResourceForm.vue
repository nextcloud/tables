<template>
	<div class="row space-B">
		<h3>{{ t('tables', 'Select a table or view') }}</h3>
		<NcSelect style="width: 100%;" :loading="loading" :options="options" :clear-on-select="true"
			:hide-selected="true" :placeholder="t('tables', 'Select a table or view')" :searchable="true"
			:get-option-key="(option) => option.key" label="title"
			:aria-label-combobox="t('tables', 'Select a table or view')" :user-select="true" :preselect-first="true"
			:preserve-search="true" @search="asyncFind" @input="addResource">
			<template #no-options>
				{{ t('tables', 'No recommendations. Start typing.') }}
			</template>
			<template #noResult>
				{{ noResultText }}
			</template>
		</NcSelect>
	</div>
</template>

<script>
import debounce from 'debounce'
import { NcSelect } from '@nextcloud/vue'
import { mapState } from 'vuex'
import { NODE_TYPE_TABLE, NODE_TYPE_VIEW } from '../../../shared/constants.js'

export default {
	name: 'ResourceForm',
	components: {
		NcSelect,
	},

	props: {
		resources: {
			type: Array,
			default: () => ([]),
		},
	},

	data() {
		return {
			query: '',
			value: '',
			loading: false,
			minSearchStringLength: 1,
			maxAutocompleteResults: 20,
			suggestions: [],
		}
	},

	computed: {
		...mapState(['tables', 'views']),

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
			// Check search word and if item already in the resource list before showing as suggestion
			let filteredTables = this.tables.filter((table) => table.title.toLowerCase().includes(searchTerm.toLowerCase())
			&& !this.resources.find(t => t.nodeType === NODE_TYPE_TABLE && parseInt(t.id) === parseInt(table.id)))
			filteredTables = filteredTables.map(table => {
				return {
					title: table.title,
					emoji: table.emoji,
					key: 'table-' + table.id,
					nodeType: NODE_TYPE_TABLE,
					id: (table.id).toString(),
				}
			})
			let filteredViews = this.views.filter((view) => view.title.toLowerCase().includes(searchTerm.toLowerCase())
			&& !this.resources.find(v => v.nodeType === NODE_TYPE_VIEW && parseInt(v.id) === parseInt(view.id)))
			filteredViews = filteredViews.map(view => {
				return {
					title: view.title,
					emoji: view.emoji,
					key: 'view-' + view.id,
					nodeType: NODE_TYPE_VIEW,
					id: (view.id).toString(),
				}
			})
			this.loading = false
			this.suggestions = [...filteredTables, ...filteredViews].sort(this.sortByTitle)
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

<style lang="scss" scoped></style>
