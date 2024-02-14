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
import { emit } from '@nextcloud/event-bus'
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
			viewSetting: {},
		}
	},

	computed: {
		...mapState(['activeRowId']),
		...mapState({
			columns(state) { return state.data.columns[this.isView ? 'view-' + (this.element.id).toString() : (this.element.id).toString()] },
			rows(state) { return state.data.rows[this.isView ? 'view-' + (this.element.id).toString() : (this.element.id).toString()] },
		}),
	},

	watch: {
		element() {
			this.reload()
		},
	},

	beforeMount() {
		this.reload(true)
	},

	methods: {
		createColumn() {
			emit('tables:column:create', { isView: this.isView, element: this.element })
		},
		downloadCSV() {
			this.downloadCsv(this.rows, this.columns, this.element.title)
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

			// Used to reload View from backend, in case there are Filter updates
			const isLastElementSameAndView = this.element.id === this.lastActiveElement?.id && this.isView === this.lastActiveElement?.isView
			if (!this.lastActiveElement || this.element.id !== this.lastActiveElement.id || isLastElementSameAndView || this.isView !== this.lastActiveElement.isView || force) {
				this.localLoading = true

				this.viewSetting = {}
				if (this.isView && this.element?.sort?.length) {
					this.viewSetting.presetSorting = [...this.element.sort]
				}

				await this.$store.dispatch('loadColumnsFromBE', {
					view: this.isView ? this.element : null,
					tableId: !this.isView ? this.element.id : null,
				})
				if (this.canReadData(this.element)) {
					await this.$store.dispatch('loadRowsFromBE', {
						viewId: this.isView ? this.element.id : null,
						tableId: !this.isView ? this.element.id : null,
					})
				} else {
					await this.$store.dispatch('removeRows', {
						isView: this.isView,
						elementId: this.element.id,
					})
				}
				this.lastActiveElement = {
					id: this.element.id,
					isView: this.isView,
				}
				if (this.activeRowId) {
					emit('tables:row:edit', { row: this.rows.find(r => r.id === this.activeRowId), columns: this.columns, isView: this.isView, elementId: this.element.id })
				}
				this.localLoading = false
			}
		},
	},
}
</script>
