<template>
	<tr>
		<th>
			<div class="cell-wrapper">
				<NcCheckboxRadioSwitch :checked="allRowsAreSelected" @update:checked="value => $emit('select-all-rows', value)" />
				<div v-if="hasRightHiddenNeighbor(-1)" class="hidden-indicator-first" @click="unhide(-1)" />
			</div>
		</th>
		<th v-for="col in visibleColumns" :key="col.id">
			<div class="cell-wrapper">
				<div class="cell-options-wrapper">
					<div class="cell">
						<div class="clickable" @click="updateOpenState(col.id)">
							{{ col.title }}
						</div>
						<TableHeaderColumnOptions
							:column="col"
							:open-state.sync="openedColumnHeaderMenus[col.id]"
							:can-hide="visibleColumns.length > 1"
							@add-filter="filter => $emit('add-filter', filter)" />
					</div>
					<div v-if="getFilterForColumn(col)" class="filter-wrapper">
						<FilterLabel v-for="filter in getFilterForColumn(col)"
							:id="filter.columnId + filter.operator.id+ filter.value"
							:key="filter.columnId + filter.operator.id+ filter.value"
							:operator="filter.operator"
							:value="filter.value"
							@delete-filter="id => $emit('delete-filter', id)" />
					</div>
				</div>
				<div v-if="hasRightHiddenNeighbor(col.id)" class="hidden-indicator" @click="unhide(col.id)" />
			</div>
		</th>
		<th data-cy="customTableAction">
			<NcActions :force-menu="true" :type="isViewSettingSet ? 'secondary' : 'tertiary'">
				<NcActionCaption v-if="canManageElement(view)" :title="t('tables', 'Manage view')" />
				<NcActionButton v-if="canManageElement(view)"
					:close-after-click="true"
					@click="editView()">
					<template #icon>
						<PlaylistEdit :size="20" decorative />
					</template>
					{{ t('tables', 'Edit view') }}
				</NcActionButton>
				<NcActionButton v-if="canManageTable(view)" :close-after-click="true" @click="$emit('create-column')">
					<template #icon>
						<TableColumnPlusAfter :size="20" decorative title="" />
					</template>
					{{ t('tables', 'Create column') }}
				</NcActionButton>

				<NcActionCaption :title="t('tables', 'Integration')" />
				<NcActionButton v-if="canCreateRowInElement(view)"
					:close-after-click="true"
					@click="$emit('import', view)">
					<template #icon>
						<IconImport :size="20" decorative title="Import" />
					</template>
					{{ t('tables', 'Import') }}
				</NcActionButton>
				<NcActionButton v-if="canReadData(view)" :close-after-click="true"
					icon="icon-download"
					@click="downloadCSV">
					{{ t('tables', 'Export as CSV') }}
				</NcActionButton>
				<NcActionButton v-if="canShareElement(view)"
					:close-after-click="true"
					icon="icon-share"
					@click="toggleShare">
					{{ t('tables', 'Share') }}
				</NcActionButton>
				<NcActionButton
					:close-after-click="true"
					@click="actionShowIntegration">
					{{ t('tables', 'Integration') }}
					<template #icon>
						<Creation :size="20" />
					</template>
				</NcActionButton>
			</NcActions>
		</th>
	</tr>
</template>

<script>
import { NcCheckboxRadioSwitch, NcActions, NcActionButton, NcActionCaption } from '@nextcloud/vue'
import { emit } from '@nextcloud/event-bus'
import TableColumnPlusAfter from 'vue-material-design-icons/TableColumnPlusAfter.vue'
import PlaylistEdit from 'vue-material-design-icons/PlaylistEdit.vue'
import IconImport from 'vue-material-design-icons/Import.vue'
import Creation from 'vue-material-design-icons/Creation.vue'
import TableHeaderColumnOptions from './TableHeaderColumnOptions.vue'
import FilterLabel from './FilterLabel.vue'
import permissionsMixin from '../mixins/permissionsMixin.js'
import { mapGetters } from 'vuex'
import { showSuccess, showError } from '@nextcloud/dialogs'

export default {

	components: {
		PlaylistEdit,
		IconImport,
		FilterLabel,
		NcCheckboxRadioSwitch,
		TableHeaderColumnOptions,
		NcActions,
		NcActionButton,
		TableColumnPlusAfter,
		NcActionCaption,
		Creation,
	},

	mixins: [permissionsMixin],

	props: {
		columns: {
			type: Array,
			default: () => [],
		},
		rows: {
			type: Array,
			default: () => [],
		},
		selectedRows: {
			type: Array,
			default: () => [],
		},
		view: {
			type: Object,
			default: () => {},
		},
		viewSetting: {
			type: Object,
			default: null,
		},
	},

	data() {
		return {
			openedColumnHeaderMenus: {},
		}
	},

	computed: {
		...mapGetters(['activeView']),
		allRowsAreSelected() {
			if (Array.isArray(this.rows) && Array.isArray(this.selectedRows) && this.rows.length !== 0) {
				return this.rows.length === this.selectedRows.length
			} else {
				return false
			}
		},
		visibleColumns() {
			return this.columns.filter(col => !this.viewSetting?.hiddenColumns?.includes(col.id))
		},
		isViewSettingSet() {
			return !(!this.viewSetting || ((!this.viewSetting.hiddenColumns || this.viewSetting.hiddenColumns.length === 0) && (!this.viewSetting.sorting) && (!this.viewSetting.filter || this.viewSetting.filter.length === 0)))
		},
	},

	methods: {
		editView() {
			emit('tables:view:edit', this.view)
		},
		updateOpenState(columnId) {
			this.openedColumnHeaderMenus[columnId] = !this.openedColumnHeaderMenus[columnId]
			this.openedColumnHeaderMenus = Object.assign({}, this.openedColumnHeaderMenus)
		},
		getFilterForColumn(column) {
			return this.viewSetting?.filter?.filter(item => item.columnId === column.id)
		},
		downloadCSV() {
			this.$emit('download-csv', this.rows)
		},
		toggleShare() {
			emit('tables:sidebar:sharing', { open: true, tab: 'sharing' })
		},
		hasRightHiddenNeighbor(colId) {
			return this.viewSetting?.hiddenColumns?.includes(this.columns[this.columns.indexOf(this.columns.find(col => col.id === colId)) + 1]?.id)
		},
		unhide(colId) {
			this.$store.dispatch('unhideColumn', { columnId: this.columns[this.columns.indexOf(this.columns.find(col => col.id === colId)) + 1]?.id })
		},
		createView() {
			emit('create-view', this.activeView.tableId)
		},
		generateViewConfigData() {
			const data = { data: {} }
			if (this.viewSetting.hiddenColumns && this.viewSetting.hiddenColumns.length !== 0) {
				data.data.columns = JSON.stringify(this.columns.map(col => col.id).filter(id => !this.viewSetting.hiddenColumns.includes(id)))
			} else {
				data.data.columns = JSON.stringify(this.columns.map(col => col.id))
			}
			if (this.viewSetting.sorting) {
				data.data.sort = JSON.stringify([...this.view.sort, this.viewSetting.sorting[0]])
			}
			if (this.viewSetting.filter && this.viewSetting.filter.length !== 0) {
				const filteringRules = this.viewSetting.filter.map(fil => ({
					columnId: fil.columnId,
					operator: fil.operator.id,
					value: fil.value,
				}))
				const newFilter = []
				if (this.view.filter && this.view.filter.length !== 0) {
					this.view.filter.forEach(filterGroup => {
						newFilter.push([...filterGroup, ...filteringRules])
					})
				} else {
					newFilter[0] = filteringRules
				}
				data.data.filter = JSON.stringify(newFilter)
			}
			return data
		},
		async applyViewConfig() {
			await this.$store.dispatch('updateView', { id: this.activeView.id, data: this.generateViewConfigData() })
			emit('tables:view:reload')
			showSuccess(t('tables', 'The configuration of view "{view}" was updated.', { view: this.activeView.title }))
		},
		async createWithViewConfig() {
			const data = {
				tableId: this.activeView.tableId,
				title: this.activeView.title + ' ' + t('tables', 'Copy'),
				emoji: this.activeView.emoji,
			}
			const newViewId = await this.$store.dispatch('insertNewView', { data })
			if (newViewId) {
				const res = await this.$store.dispatch('updateView', { id: newViewId, data: this.generateViewConfigData() })
				if (res) {
					await this.$router.push('/view/' + newViewId)
				} else {
					showError(t('tables', 'Could not configure new view'))
				}
			} else {
				showError(t('tables', 'Could not create new view'))
			}
		},
		async actionShowIntegration() {
			emit('tables:sidebar:integration', { open: true, tab: 'integration' })
		},
	},
}
</script>
<style lang="scss" scoped>

.cell {
	display: inline-flex;
	align-items: center;
}

.cell span {
	padding-left: 12px;

}

.filter-wrapper {
	margin-top: calc(var(--default-grid-baseline) * -1);
	margin-bottom: calc(var(--default-grid-baseline) * 2);
	display: flex;
	flex-wrap: wrap;
	gap: 0 calc(var(--default-grid-baseline) * 2);
}

:deep(.checkbox-radio-switch__icon) {
	margin: 0;
}

.clickable {
	cursor: pointer;
}

.hidden-indicator {
	border-right: solid;
	border-color: var(--color-primary);
	border-width: 3px;
	padding-left: calc(var(--default-grid-baseline) * 1);
	cursor: pointer;
}

.hidden-indicator-first {
	border-right: solid;
	border-color: var(--color-primary);
	border-width: 3px;
	padding-left: calc(var(--default-grid-baseline) * 4);
	cursor: pointer;
}

.cell-wrapper {
	display: flex;
	justify-content: space-between;
}

.cell-options-wrapper {
	display: flex;
	flex-direction: column;
	width: 100%;
}

</style>
