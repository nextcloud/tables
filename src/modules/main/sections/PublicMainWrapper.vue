<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="public-table-wrapper">
		<div v-if="loading" class="icon-loading" />

		<div v-else>
			<PublicElement :element="publicElement" :columns="columns" :rows="rows" :total-rows="totalRows" :view-setting="viewSetting" @update:viewSetting="viewSetting = $event" @download-csv="downloadCSV" @download-filtered-csv="downloadFilteredCSV" />
		</div>
	</div>
</template>

<script>
import { mapActions, storeToRefs } from 'pinia'
import PublicElement from './PublicElement.vue'
import exportTableMixin from '../../../shared/components/ncTable/mixins/exportTableMixin.js'
import { useDataStore } from '../../../store/data.js'
import { useTablesStore } from '../../../store/store.js'
import { computed } from 'vue'
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import { buildUrlQuery, parseUrlQuery } from '../../../shared/utils/urlState.js'
import { showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'

const nodeData = loadState('tables', 'nodeData', null)
const sharePermissions = loadState('tables', 'sharePermissions', null)

export default {
	name: 'PublicMainWrapper',

	components: {
		PublicElement,
	},

	mixins: [exportTableMixin],

	props: {
		token: {
			type: String,
			required: true,
		},
	},

	setup(props) {
		const store = useDataStore()
		const { getColumns, getRows, getTotalRows } = storeToRefs(store)

		const stateKey = 'public-' + props.token
		const rows = computed(() => getRows.value(false, stateKey))
		const columns = computed(() => getColumns.value(false, stateKey))
		const totalRows = computed(() => getTotalRows.value(false, stateKey))

		return { rows, columns, totalRows, dataStore: store }
	},

	data() {
		return {
			loading: false,
			viewSetting: {},
			lastViewSettingFilter: null,
			lastViewSettingSorting: null,
			lastViewSettingSearchString: null,
			rowsPerPage: 100,
			pageNumber: 1,
			paginationOffset: 0,
			rowsLoading: false,
			viewSettingInProgress: false,
			applyUrlStateOnReload: false,
			publicElement: {
				id: 'public',
				emoji: nodeData.emoji,
				title: nodeData.title,
				description: nodeData.description,
				isShared: false, // Setting as false to hide the user bubble
				onSharePermissions: {
					read: sharePermissions.read,
					create: sharePermissions.create,
					update: sharePermissions.update,
					delete: sharePermissions.delete,
					manage: false,
				},
			},
		}
	},

	beforeMount() {
		this.setPublicToken(this.token)
		this.applyUrlStateOnReload = true
		this.reload()
	},

	mounted() {
		subscribe('tables:pagination-changed', this.onPaginationChanged)
	},

	beforeUnmount() {
		unsubscribe('tables:pagination-changed', this.onPaginationChanged)
	},

	watch: {
		viewSetting: {
			handler() {
				const newFilter = this.viewSetting?.filter ? JSON.stringify(this.viewSetting.filter) : null
				const newSorting = this.viewSetting?.sorting ? JSON.stringify(this.viewSetting.sorting) : null
				const newSearchString = this.viewSetting?.searchString || null
				if (newFilter === this.lastViewSettingFilter && newSorting === this.lastViewSettingSorting && newSearchString === this.lastViewSettingSearchString) {
					return
				}
				const oldFilter = this.lastViewSettingFilter
				const oldSorting = this.lastViewSettingSorting
				const oldSearchString = this.lastViewSettingSearchString
				this.lastViewSettingFilter = newFilter
				this.lastViewSettingSorting = newSorting
				this.lastViewSettingSearchString = newSearchString
				this.onViewSettingChanged(oldFilter, oldSorting, oldSearchString)
			},
			deep: true,
		},
	},

	methods: {
		...mapActions(useDataStore, ['loadPublicColumnsFromBE', 'loadPublicRowsFromBE', 'loadPublicRowsCountFromBE', 'loadPublicRowsForExportFromBE', 'setPublicToken']),
		...mapActions(useTablesStore, ['validatePublicExportAccess']),

		async reload() {
			if (!this.token) {
				return
			}

			this.loading = true

			if (this.applyUrlStateOnReload) {
				this.applyUrlStateOnReload = false
				this.applyUrlState()
			} else {
				this.viewSetting = {}
				this.pageNumber = 1
				this.paginationOffset = 0
			}

			await this.loadPublicColumnsFromBE({ token: this.token })

			this.rowsLoading = true
			try {
				await this.loadPublicRowsCountFromBE({
					token: this.token,
					filter: this.viewSetting?.filter,
					sort: this.viewSetting?.sorting,
					search: this.viewSetting?.searchString,
				})
				await this.loadPublicRowsFromBE({
					token: this.token,
					filter: this.viewSetting?.filter,
					sort: this.viewSetting?.sorting,
					search: this.viewSetting?.searchString,
					limit: this.rowsPerPage,
					offset: this.paginationOffset,
				})
			} finally {
				this.rowsLoading = false
			}

			this.loading = false
			this.$nextTick(() => {
				emit('tables:pagination-changed', { pageNumber: this.pageNumber, rowsPerPage: this.rowsPerPage })
			})
		},

		applyUrlState() {
			const { filter, sorting, searchString, pageNumber, rowsPerPage } = parseUrlQuery(this.$route.query)
			this.pageNumber = pageNumber
			this.rowsPerPage = rowsPerPage
			this.paginationOffset = (this.pageNumber - 1) * this.rowsPerPage
			const viewSetting = {
				filter,
				sorting,
				searchString,
			}
			this.lastViewSettingFilter = viewSetting?.filter ? JSON.stringify(viewSetting.filter) : null
			this.lastViewSettingSorting = viewSetting?.sorting ? JSON.stringify(viewSetting.sorting) : null
			this.lastViewSettingSearchString = viewSetting?.searchString || null
			this.viewSetting = viewSetting
		},

		updateUrlFromState() {
			const query = buildUrlQuery(this.viewSetting, this.pageNumber, this.rowsPerPage)
			this.$router.replace({ query }).catch(() => {})
		},

		async onViewSettingChanged(oldFilter, oldSorting, oldSearchString) {
			if (this.loading || this.rowsLoading) {
				return
			}
			const filterChanged = this.lastViewSettingFilter !== oldFilter
			const sortingChanged = this.lastViewSettingSorting !== oldSorting
			const searchStringChanged = this.lastViewSettingSearchString !== oldSearchString
			if (!filterChanged && !sortingChanged && !searchStringChanged) {
				return
			}
			this.viewSettingInProgress = filterChanged || searchStringChanged
			this.rowsLoading = true
			try {
				if (filterChanged || searchStringChanged) {
					this.pageNumber = 1
					this.paginationOffset = 0
					emit('tables:pagination-changed', { pageNumber: 1, rowsPerPage: this.rowsPerPage })
					await this.loadPublicRowsCountFromBE({
						token: this.token,
						filter: this.viewSetting?.filter,
						sort: this.viewSetting?.sorting,
						search: this.viewSetting?.searchString,
					})
					await this.loadPublicRowsFromBE({
						token: this.token,
						filter: this.viewSetting?.filter,
						sort: this.viewSetting?.sorting,
						search: this.viewSetting?.searchString,
						limit: this.rowsPerPage,
						offset: this.paginationOffset,
					})
				} else if (sortingChanged) {
					this.paginationOffset = (this.pageNumber - 1) * this.rowsPerPage
					await this.loadPublicRowsFromBE({
						token: this.token,
						filter: this.viewSetting?.filter,
						sort: this.viewSetting?.sorting,
						search: this.viewSetting?.searchString,
						limit: this.rowsPerPage,
						offset: this.paginationOffset,
					})
				}
			} finally {
				this.rowsLoading = false
				this.viewSettingInProgress = false
				this.updateUrlFromState()
			}
		},

		async onPaginationChanged({ pageNumber, rowsPerPage }) {
			if (this.loading || this.viewSettingInProgress || this.rowsLoading) {
				return
			}
			if (this.pageNumber === pageNumber && this.rowsPerPage === rowsPerPage) {
				return
			}
			this.pageNumber = pageNumber
			if (rowsPerPage) {
				this.rowsPerPage = rowsPerPage
			}
			this.paginationOffset = (this.pageNumber - 1) * this.rowsPerPage

			this.rowsLoading = true
			try {
				await this.loadPublicRowsFromBE({
					token: this.token,
					filter: this.viewSetting?.filter,
					sort: this.viewSetting?.sorting,
					search: this.viewSetting?.searchString,
					limit: this.rowsPerPage,
					offset: this.paginationOffset,
				})
			} finally {
				this.rowsLoading = false
				this.updateUrlFromState()
			}
		},

		async downloadCSV() {
			const access = await this.validatePublicExportAccess(this.token)
			if (!access?.ok) {
				if (access?.reason === 'NO_ACCESS') {
					showError(t('tables', 'Your access was revoked. Reload the page to update your permissions.'))
				}
				return
			}
			const csv = await this.loadPublicRowsForExportFromBE({
				token: this.token,
			})
			if (csv) {
				this.downloadFile(csv, 'public-export.csv')
			}
		},
		async downloadFilteredCSV(rows) {
			const access = await this.validatePublicExportAccess(this.token)
			if (!access?.ok) {
				if (access?.reason === 'NO_ACCESS') {
					showError(t('tables', 'Your access was revoked. Reload the page to update your permissions.'))
				}
				return
			}

			if (rows !== this.rows) {
				const csv = await this.loadPublicRowsForExportFromBE({
					token: this.token,
					rowIds: rows.map(row => row.id),
				})
				if (csv) {
					this.downloadFile(csv, 'public-export.csv')
				}
				return
			}

			const csv = await this.loadPublicRowsForExportFromBE({
				token: this.token,
				filter: this.viewSetting?.filter,
				sort: this.viewSetting?.sorting,
				search: this.viewSetting?.searchString,
			})
			if (csv) {
				this.downloadFile(csv, 'public-export.csv')
			}
		},
	},
}
</script>

<style scoped lang="scss">
.public-table-wrapper {
	width: 100%;
	height: 100%;

	:deep(.tables-list__table) {
		margin-top: 60px;
	}
}
</style>
