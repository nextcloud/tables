<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div v-if="!view" class="icon-loading" />
	<div v-else class="main-view-view">
		<Temp :view="view" :rows="view?.rows || []" :columns="view?.columnValues || []" />
		<MainModals />
	</div>
</template>

<script>

import MainModals from '../modules/modals/Modals.vue'
import Temp from '../modules/main/sections/Temp.vue'

export default {

	components: {
		Temp,
		MainModals,
	},

	data() {
		return {
			view: null,
			columns: [],
			rows: [],
			queryParams: {},
			parsedData: {
				tableId: null,
				data: {
					title: 'Temporary View',
					emoji: '\ud83d\ude09',
					columns: [],
					sort: [],
					filter: [],
				},
			},
		}
	},

	created() {
		this.extractQueryParams()
		this.parseQueryParams()
	},

	// async mounted() {
	//     const res = await this.$store.dispatch('createTemporaryView', {
	//         tableId: 1,
	//         data: {
	//             title: 'Temporary View',
	//             emoji: '\ud83d\ude09',
	//             columns: [1, 2, 3, 4],
	//             sort: [{ columnId: 1, mode: 'ASC' }],
	//             filter: [[{ columnId: 1, operator: 'contains', value: 'row' }], [{ columnId: 2, operator: 'contains', value: 'row' }]],
	//         },
	//     })
	//     this.view = res
	// },

	methods: {
		extractQueryParams() {
			// Access query parameters from the $route object
			this.queryParams = { ...this.$route.query }
			console.log('Query parameters:', this.queryParams)
		},

		// Example: http://nextcloud.local/index.php/apps/tables/#/tempview?tableId=1&columns=[1,2,3,4]&filter=1:contains:row&sort=1:asc
		parseQueryParams() {
			const query = this.$route.query

			// Parse tableId
			this.parsedData.tableId = parseInt(query.tableId) || null

			// Parse columns
			if (query.columns) {
				try {
					this.parsedData.data.columns = JSON.parse(query.columns)
				} catch (e) {
					console.error('Error parsing columns:', e)
				}
			}

			// Parse sort
			if (query.sort) {
				const sortParts = query.sort.split(':')
				if (sortParts.length === 2) {
					this.parsedData.data.sort = [{
						columnId: parseInt(sortParts[0]),
						mode: sortParts[1].toUpperCase(),
					}]
				}
			}

			// Parse filter
            // Should be an array of arrarys
			if (query.filter) {
				const [columnId, operator, value] = query.filter.split(':')
				this.parsedData.data.filter = [[{ columnId: parseInt(columnId), operator, value }]]
			}

			this.loadTempView()
		},

		async loadTempView() {
			const res = await this.$store.dispatch('createTemporaryView', {
				tableId: this.parsedData.tableId,
				data: this.parsedData.data,
			})
			this.view = res
			this.localLoading = false
			console.log('temporary view', this.view)
		},
	},
}
</script>
<style lang="scss">
.main-view-view {
    width: max-content;
    min-width: var(--app-content-width, 100%);
}
</style>
