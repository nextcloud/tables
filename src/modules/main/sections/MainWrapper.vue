<template>
	<div>
		<div v-if="localLoading || !element" class="icon-loading" />

		<div v-else>
			<CustomView v-if="isView"
				:view="element"
				:columns="columns"
				:rows="rows"
				:view-setting="viewSetting"
				@create-column="createColumn"
				@import="openImportModal"
				@download-csv="downloadCSV"
				@toggle-share="toggleShare"
				@show-integration="showIntegration" />
			<CustomTable v-else
				:table="element"
				:columns="columns"
				:rows="rows"
				:view-setting="viewSetting"
				@create-column="createColumn"
				@import="openImportModal"
				@download-csv="downloadCSV"
				@toggle-share="toggleShare"
				@show-integration="showIntegration" />
		</div>
	</div>
</template>

<script>

import { mapState } from 'vuex'
import { emit} from '@nextcloud/event-bus'
import CustomView from './View.vue'
import CustomTable from './Table.vue'
import permissionsMixin from '../../../shared/components/ncTable/mixins/permissionsMixin.js'
import exportTableMixin from '../../../shared/components/ncTable/mixins/exportTableMixin.js'

export default {
	name: 'MainWrapper',

	components: {
		CustomView,
		CustomTable,
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
			localLoading: false,
			lastActiveElement: null,
		}
	},

	computed: {
		...mapState(['activeRowId']),
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
		openImportModal() {
			emit('tables:modal:import', { element: this.element, isView: this.isView })
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
				if (this.activeRowId) {
					emit('tables:row:edit', { row: this.rows.find(r => r.id === this.activeRowId), columns: this.columns })
				}
				this.localLoading = false
			}
		},
	},
}
</script>
