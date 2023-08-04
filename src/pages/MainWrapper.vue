<template>
	<div>
		<div v-if="localLoading || !element" class="icon-loading" />

		<div v-else>
			<DefaultMainView v-if="isView"
				:view="element"
				:columns="columns"
				:rows="rows"
				:view-setting="viewSetting"
				:selected-rows.sync="selectedRows"
				@create-column="createColumn"
				@import="openImportModal"
				@download-csv="downloadCSV"
				@toggle-share="toggleShare"
				@show-integration="showIntegration" />
			<Dashboard v-else
				:table="element"
				:columns="columns"
				:rows="rows"
				:view-setting="viewSetting"
				:selected-rows.sync="selectedRows"
				@create-column="createColumn"
				@import="openImportModal"
				@download-csv="downloadCSV"
				@toggle-share="toggleShare"
				@show-integration="showIntegration" />
		</div>
		<CreateColumn :show-modal="showCreateColumn" @close="showCreateColumn = false" />
		<EditColumn v-if="columnToEdit" :column="columnToEdit" @close="columnToEdit = false" />
		<DeleteColumn v-if="columnToDelete" :column-to-delete="columnToDelete" @cancel="columnToDelete = null" />
		<CreateRow :columns="columns"
			:show-modal="showCreateRow"
			@close="showCreateRow = false" />
		<EditRow :columns="columns"
			:row="rows.find(r => r.id === editRowId)"
			:show-modal="editRowId !== null"
			:out-transition="true"
			@close="editRowId = null" />
		<DeleteRows v-if="rowsToDelete" :rows-to-delete="rowsToDelete" @cancel="rowsToDelete = null" />
		<ViewSettings
			:show-modal="viewToEdit !== null"
			:view="viewToEdit"
			:view-setting="viewSetting"
			@close="viewToEdit = null"
			@reload-view="reload(true)" />
	</div>
</template>

<script>

import { mapState } from 'vuex'
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import CreateColumn from '../modules/main/modals/CreateColumn.vue'
import EditColumn from '../modules/main/modals/EditColumn.vue'
import DeleteColumn from '../modules/main/modals/DeleteColumn.vue'
import CreateRow from '../modules/main/modals/CreateRow.vue'
import EditRow from '../modules/main/modals/EditRow.vue'
import DeleteRows from '../modules/main/modals/DeleteRows.vue'
import DefaultMainView from './DefaultMainView.vue'
import Dashboard from './Dashboard.vue'
import ViewSettings from '../modules/main/modals/ViewSettings.vue'
import permissionsMixin from '../shared/components/ncTable/mixins/permissionsMixin.js'
import exportTableMixin from '../shared/components/ncTable/mixins/exportTableMixin.js'

export default {
	name: 'MainWrapper',

	components: {
		CreateColumn,
		EditColumn,
		DeleteColumn,
		CreateRow,
		EditRow,
		DeleteRows,
		DefaultMainView,
		Dashboard,
		ViewSettings,
	},

	mixins: [permissionsMixin, exportTableMixin],

	props: {
		element: {
			type: Object,
			default: null,
		},
		isView: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			showCreateColumn: false,
			columnToEdit: null,
			columnToDelete: null,
			showCreateRow: false,
			editRowId: null,
			rowsToDelete: null,
			localLoading: false,
			lastActiveElement: null,
			viewToEdit: null,
			selectedRows: [],
		}
	},

	computed: {
		...mapState({
			columns: state => state.data.columns,
			rows: state => state.data.rows,
			viewSetting: state => state.data.viewSetting,
		}),
	},

	watch: {
		element() {
			this.reload()
		},
	},

	mounted() {
		this.reload(true)
		subscribe('tables:view:reload', () => { this.reload(true) })
		subscribe('tables:column:create', () => { this.showCreateColumn = true })
		subscribe('tables:column:edit', column => { this.columnToEdit = column })
		subscribe('tables:column:delete', column => { this.columnToDelete = column })
		subscribe('tables:row:create', () => { this.showCreateRow = true })
		subscribe('tables:row:edit', rowId => { this.editRowId = rowId })
		subscribe('tables:row:delete', rows => { this.rowsToDelete = rows })
		subscribe('tables:view:edit', view => { this.viewToEdit = view })
	},
	unmounted() {
		unsubscribe('tables:view:reload', () => { this.reload(true) })
		unsubscribe('tables:column:create', () => { this.showCreateColumn = true })
		unsubscribe('tables:column:edit', column => { this.columnToEdit = column })
		unsubscribe('tables:column:delete', column => { this.columnToDelete = column })
		unsubscribe('tables:row:create', () => { this.showCreateRow = true })
		unsubscribe('tables:row:edit', rowId => { this.editRowId = rowId })
		unsubscribe('tables:row:delete', rows => { this.rowsToDelete = rows })
		unsubscribe('tables:view:edit', view => { this.viewToEdit = view })
	},

	methods: {
		createColumn() {
			emit('tables:column:create')
		},
		downloadCSV() {
			this.downloadCsv(this.rows, this.columns, this.element)
		},
		toggleShare() {
			emit('tables:sidebar:sharing', { open: true, tab: 'sharing' })
		},
		showIntegration() {
			emit('tables:sidebar:integration', { open: true, tab: 'integration' })
		},
		openImportModal(element) {
			emit('tables:modal:import', { element, isView: this.isView })
		},
		deleteRows(rowIds) {
			this.rowsToDelete = rowIds
		},
		async reload(force = false) {
			if (!this.element) {
				return
			}

			if (!this.lastActiveElement || this.element.id !== this.lastActiveElement.id || this.isView !== this.lastActiveElement.isView || force) {
				this.localLoading = true

				if (this.isView) await this.$store.dispatch('resetViewSetting')

				await this.$store.dispatch('loadColumnsFromBE', {
					view: this.isView ? this.element : null,
					table: !this.isView ? this.element : null,
				})
				if (this.canReadData(this.element)) {
					await this.$store.dispatch('loadRowsFromBE', {
						viewId: this.isView ? this.element.id : null,
						tableId: !this.isView ? this.element.id : null,
					})
				} else {
					await this.$store.dispatch('removeRows')
				}
				this.lastActiveViewId = {
					id: this.element.id,
					isView: this.isView,
				}
				this.localLoading = false
			}
		},
	},
}
</script>
