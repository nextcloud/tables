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
			columns(state) { return state.data.columns[this.isView ? 'view-' + this.element.id : this.element.id] },
			rows(state) { return state.data.rows[this.isView ? 'view-' + this.element.id : this.element.id] },
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
		setActiveElement() {
			if (this.isView) {
				this.$store.commit('setActiveViewId', parseInt(this.element.id))
			} else {
				this.$store.commit('setActiveTableId', parseInt(this.element.id))
			}
		},
		createColumn() {
			this.setActiveElement()
			emit('tables:column:create')
		},
		downloadCSV() {
			this.downloadCsv(this.rows, this.columns, this.element.title)
		},
		toggleShare() {
			this.setActiveElement()
			emit('tables:sidebar:sharing', { open: true, tab: 'sharing' })
		},
		showIntegration() {
			this.setActiveElement()
			emit('tables:sidebar:integration', { open: true, tab: 'integration' })
		},
		openImportModal() {
			this.setActiveElement()
			emit('tables:modal:import', { element: this.element, isView: this.isView })
		},
		deleteRows(rowIds) {
			this.rowsToDelete = rowIds
		},
		async reload(force = false) {
			if (!this.element) {
				return
			}
			const stateId = this.isView ? 'view-' + this.element.id : this.element.id
			// if (stateId && !(stateId in this.$store.state.data.rows)) {
			// 	this.$store.dispatch('initState', { stateId })
			// }

			if (!this.lastActiveElement || this.element.id !== this.lastActiveElement.id || this.isView !== this.lastActiveElement.isView || force) {
				if (this.lastActiveElement) {
					this.$store.dispatch('removeDataFromState', { stateId: this.lastActiveElement.id })
				}
				this.localLoading = true

				this.viewSetting = {}

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
					await this.$store.dispatch('removeRows', { stateId })
				}
				this.lastActiveElement = {
					id: this.element.id,
					isView: this.isView,
				}
				if (this.activeRowId) {
					this.setActiveElement()
					emit('tables:row:edit', { row: this.rows.find(r => r.id === this.activeRowId), columns: this.columns })
				}
				this.localLoading = false
			}
		},
	},
}
</script>
