<template>
	<div class="row space-B">
		<h3>{{ t('tables', 'Select a table or view') }}</h3>
		<NcSelect style="width: 100%;" :loading="loading" :options="options"
			:clear-on-select="true"
			:hide-selected="true"
			:placeholder="t('tables', 'Select a table or view')"
			:searchable="true" :get-option-key="(option) => option.key"
			label="title"
			:aria-label-combobox="t('tables', 'Select a table or view')" :user-select="true"
			:preselect-first="true"
			:preserve-search="true"
			@search="asyncFind" @input="addResource">
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
import { generateOcsUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import debounce from 'debounce'
import { NcSelect } from '@nextcloud/vue'
import { mapState } from 'vuex'

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
		...mapState(['tables', 'views', 'tablesLoading']),

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

		userId() {
			return getCurrentUser().uid
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

		async getSuggestions(search) {
			this.loading = true
			// TODO improve search, should be case-insensitive
			let tables = this.tables.filter((table) => table.title.includes(search))
			tables = tables.map(table => {
				return {
					title: table.title,
					emoji: table.emoji,
					key: 'table-' + table.id,
					nodeType: 0,
					id: (table.id).toString(),
				}
			})
			let views = this.views.filter((view) => view.title.includes(search))
			views = views.map(view => {
				return {
					title: view.title,
					emoji: view.emoji,
					key: 'view-' + view.id,
					nodeType: 1,
					id: (view.id).toString(),
				}
			})
			this.loading = false
			this.suggestions = [...tables, ...views]
		},

		debounceGetSuggestions: debounce(function(...args) {
			this.getSuggestions(...args)
		}, 300),

	},
}
</script>

<style lang="scss" scoped>

</style>
