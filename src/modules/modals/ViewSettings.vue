<template>
	<NcAppSettingsDialog :open.sync="open" :show-navigation="true" data-cy="viewSettingsDialog" :title="createView ? t('tables', 'Create view') : t('tables', 'Edit view')">
		<NcAppSettingsSection v-if="columns === null" id="loading" name="">
			<div class="icon-loading" />
		</NcAppSettingsSection>
		<!--title & emoji-->
		<NcAppSettingsSection v-if="columns != null" id="title" :name="t('tables', 'Title')" data-cy="viewSettingsDialogSection">
			<div class="col-4" style="display: inline-flex;">
				<NcEmojiPicker :close-on-select="true" @select="setIcon">
					<NcButton type="tertiary"
						:aria-label="t('tables', 'Select emoji for view')"
						:title="t('tables', 'Select emoji')"
						@click.prevent>
						{{ icon }}
					</NcButton>
				</NcEmojiPicker>
				<input v-model="title"
					:class="{missing: errorTitle}"
					type="text"
					:placeholder="createView ? t('tables', 'Title of the new view') : t('tables', 'New title of the view')">
			</div>
		</NcAppSettingsSection>
		<!--columns & order-->
		<NcAppSettingsSection v-if="columns != null" id="columns-and-order" :name="t('tables', 'Columns')">
			<SelectedViewColumns
				:columns="allColumns"
				:selected-columns="selectedColumns"
				:view-column-ids="viewSetting ? (view.columns ?? columns.map(col => col.id)) : null"
				:generated-column-ids="viewSetting ? (generatedView.columns ?? [...selectedColumns]) : null"
				:disable-hide="!canManageTable(view)" />
		</NcAppSettingsSection>
		<!--filtering-->
		<NcAppSettingsSection v-if="columns != null && canManageTable(view)" id="filter" :name="t('tables', 'Filter')">
			<FilterForm
				:filters.sync="mutableFilters"
				:view-filters="viewSetting ? view.filter : null"
				:generated-filters="viewSetting ? generatedView.filter : null"
				:columns="allColumns" />
		</NcAppSettingsSection>
		<!--sorting-->
		<NcAppSettingsSection v-if="columns != null" id="sort" :name="t('tables', 'Sort')">
			<SortForm
				:sort="mutableView.sort"
				:view-sort="viewSetting ? view.sort : null"
				:generated-sort="viewSetting ? generatedView.sort : null"
				:columns="allColumns" />
		</NcAppSettingsSection>

		<div class="row sticky">
			<div class="fix-col-4 space-T end">
				<div style="padding-right: var(--default-grid-baseline);">
					<NcButton v-if="!localLoading && !createView" type="secondary" :aria-label="createNewViewText" @click="createNewView()">
						{{ createNewViewText }}
					</NcButton>
				</div>
				<NcButton v-if="!localLoading" type="primary" :aria-label="saveText" data-cy="modifyViewBtn" @click="saveView()">
					{{ saveText }}
				</NcButton>
			</div>
		</div>
	</NcAppSettingsDialog>
</template>

<script>
import { NcAppSettingsDialog, NcAppSettingsSection, NcEmojiPicker, NcButton } from '@nextcloud/vue'
import { showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/style.css'
import FilterForm from '../main/partials/editViewPartials/filter/FilterForm.vue'
import SortForm from '../main/partials/editViewPartials/sort/SortForm.vue'
import SelectedViewColumns from '../main/partials/editViewPartials/SelectedViewColumns.vue'
import { MetaColumns } from '../../shared/components/ncTable/mixins/metaColumns.js'
import permissionsMixin from '../../shared/components/ncTable/mixins/permissionsMixin.js'

export default {
	name: 'ViewSettings',
	components: {
		NcAppSettingsDialog,
		NcAppSettingsSection,
		NcEmojiPicker,
		NcButton,
		FilterForm,
		SelectedViewColumns,
		SortForm,
	},
	mixins: [permissionsMixin],
	props: {
		showModal: {
			type: Boolean,
			default: false,
		},
		view: {
			type: Object,
			default: null,
		},
		// If a new view is created or an existing view is edited
		createView: {
			type: Boolean,
			default: false,
		},
		// Local/frontend view settings like filter, sorting, ...
		viewSetting: {
			type: Object,
			default: null,
		},
	},
	data() {
		return {
			open: false,
			title: '',
			icon: '',
			errorTitle: false,
			selectedColumns: [],
			allColumns: [],
			localLoading: false,
			prepareDelete: false,
			columns: null,
			draggedItem: null,
			startDragIndex: null,
			mutableView: null,
			generatedView: null,
		}
	},
	computed: {
		mutableFilters: {
			get() {
				return this.mutableView.filter
			},
			set(filters) {
				this.mutableView.filter = filters
			},
		},
		saveText() {
			if (this.createView) {
				return t('tables', 'Create View')
			} else if (this.viewSettings) {
				return t('tables', 'Save modified View')
			} else {
				return t('tables', 'Save View')
			}
		},
		createNewViewText() {
			return t('tables', 'Save as new view')
		},
		generateViewConfigData() {
			if (!this.viewSetting) return this.view
			const mergedViewSettings = JSON.parse(JSON.stringify(this.view))
			if (this.view.columns) {
				if (this.viewSetting.hiddenColumns && this.viewSetting.hiddenColumns.length !== 0) {
					mergedViewSettings.columns = this.view.columns.filter(id => !this.viewSetting.hiddenColumns.includes(id))
				} else {
					mergedViewSettings.columns = this.view.columns
				}
			}
			if (this.viewSetting.sorting) {
				mergedViewSettings.sort = [this.viewSetting.sorting[0]]
			} else {
				mergedViewSettings.sort = this.view.sort
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
				mergedViewSettings.filter = newFilter
			} else {
				mergedViewSettings.filter = this.view.filter
			}
			return mergedViewSettings
		},
	},
	watch: {
		title() {
			if (this.title.length >= 200) {
				showError(t('tables', 'The title limit is reached with 200 characters. Please use a shorter title.'))
				this.title = this.title.slice(0, 199)
			}
		},
		async showModal(value) {
			if (value) {
				this.reset()
				await this.loadTableColumnsFromBE()
				this.open = true
			}
		},
		open(value) {
			if (!value) {
				this.reset()
				this.$emit('close')
			}
		},
	},
	methods: {
		setIcon(icon) {
			this.icon = icon
		},
		actionCancel() {
			this.reset()
			this.open = false
		},
		async loadTableColumnsFromBE() {
			this.columns = await this.$store.dispatch('getColumnsFromBE', {
				tableId: this.canManageTable(this.view) ? this.mutableView.tableId : null,
				viewId: this.mutableView.id,
			})
			if (this.selectedColumns === null) this.selectedColumns = this.columns.map(col => col.id).filter(colId => !this.viewSetting?.hiddenColumns?.includes(colId))
			// Show columns of view first
			if (this.canManageTable(this.view)) this.allColumns = this.columns.concat(MetaColumns)
			else this.allColumns = this.columns
			if (this.view.columns) {
				this.allColumns = this.view.columns.map(id => this.allColumns.find(col => col.id === id)).concat(this.allColumns.filter(col => !this.view.columns.includes(col.id)))
			}
		},
		async saveView() {
			if (this.title === '') {
				let titleErrorText = this.createView ? t('tables', 'Cannot create view.') : t('tables', 'Cannot update view.')
				titleErrorText += ' ' + t('tables', 'Title is missing.')
				showError(titleErrorText)
				this.errorTitle = true
			} else {
				this.localLoading = true
				if (this.createView) {
					this.mutableView.id = await this.sendNewViewToBE()
				}
				const success = await this.updateViewToBE(this.mutableView.id)
				this.localLoading = false
				if (success) {
					if (this.createView) await this.$router.push('/view/' + this.mutableView.id).catch(err => err)
					this.actionCancel()
				}
			}
		},
		async createNewView() {
			if (this.title === '') {
				showError(t('tables', 'Cannot create view.') + ' ' + t('tables', 'Title is missing.'))
				this.errorTitle = true
			} else {
				this.localLoading = true
				this.mutableView.id = await this.sendNewViewToBE()
				const success = await this.updateViewToBE(this.mutableView.id)
				this.localLoading = false
				if (success) {
					await this.$router.push('/view/' + this.mutableView.id).catch(err => err)
					this.actionCancel()
				}
			}
		},
		async sendNewViewToBE() {
			const data = {
				tableId: this.mutableView.tableId,
				title: this.title,
				emoji: this.icon,
			}
			const res = await this.$store.dispatch('insertNewView', { data })
			if (res) {
				return res
			} else {
				showError(t('tables', 'Could not create new view'))
			}
		},
		async updateViewToBE(id) {
			const newSelectedColumnIds = this.allColumns.map(col => col.id).filter(id => this.selectedColumns.includes(id))
			const data = {
				data: {
					title: this.title,
					emoji: this.icon,
					columns: JSON.stringify(newSelectedColumnIds),
				},
			}
			// Update sorting rules if they don't contain hidden rules (= rules regarding rows the user can not see) that were not overwritten
			if (!this.mutableView.sort.includes(null)) {
				const filteredSortingRules = this.mutableView.sort.filter(sortRule => sortRule.columnId !== undefined)
				data.data.sort = JSON.stringify(filteredSortingRules)
			}

			if (!this.mutableView.filter.some(filterGroup => filterGroup.includes(null))) {
				const filteredFilteringRules = this.mutableView.filter.map(filterGroup => filterGroup.filter(fil => fil.columnId !== undefined && fil.operator !== undefined)).filter(filterGroup => filterGroup.length > 0)
				data.data.filter = JSON.stringify(filteredFilteringRules)
			}

			const res = await this.$store.dispatch('updateView', { id, data })
			if (res) {
				return res
			} else {
				showError(t('tables', 'Could not update view'))
			}
		},
		reset() {
			// Deep copy of generated view config data
			this.mutableView = JSON.parse(JSON.stringify(this.generateViewConfigData))
			this.generatedView = JSON.parse(JSON.stringify(this.generateViewConfigData))
			this.title = this.mutableView.title ?? ''
			this.icon = this.mutableView.emoji ?? this.loadEmoji()
			this.errorTitle = false
			this.selectedColumns = this.mutableView.columns ? [...this.mutableView.columns] : null
			this.allColumns = []
			this.localLoading = false
			this.columns = null
		},
		loadEmoji() {
			const emojis = ['😀', '😃', '😄', '😁', '😆', '😅', '🤣', '😂', '🙂', '🙃', '🫠', '😉', '😊', '😇']
			return emojis[~~(Math.random() * emojis.length)]
		},
	},
}
</script>

<style lang="scss" scoped>

:deep(.app-settings__navigation) {
	margin-right: 0;
	min-width: auto;
}

:deep(.app-settings__content) {
	width: auto;
	flex: 1;
	padding: calc(var(--default-grid-baseline) * 2);
}

.sticky {
	position: sticky;
	bottom: 0;
}
</style>
