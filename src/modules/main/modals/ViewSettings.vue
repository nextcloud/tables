<template>
	<NcAppSettingsDialog :open.sync="open" :show-navigation="true" :title="getModalTitle">
		<NcAppSettingsSection v-if="columns === null" id="loading" :title="t('tables', 'Loading')">
			<div class="icon-loading" />
		</NcAppSettingsSection>
		<!--title & emoji-->
		<NcAppSettingsSection v-if="columns != null" id="title" :title="t('tables', 'Title')">
			<div class="col-4" style="display: inline-flex;">
				<NcEmojiPicker :close-on-select="true" @select="setIcon">
					<NcButton type="tertiary"
						:aria-label="emojiPlaceholder"
						:title="t('tables', 'Select emoji')"
						@click.prevent>
						{{ icon }}
					</NcButton>
				</NcEmojiPicker>
				<input v-model="title"
					:class="{missing: errorTitle}"
					type="text"
					:placeholder="titlePlaceholder">
			</div>
		</NcAppSettingsSection>
		<!--columns & order-->
		<NcAppSettingsSection v-if="columns != null" id="columns-and-order" :title="t('tables', 'Columns')">
			<SelectedViewColumns
				:columns="canManageTable(view) ? allColumns : columns.map(id => allColumns.find(col => col.id === id))"
				:selected-columns="selectedColumns"
				:view-column-ids="view.columns"
				:generated-column-ids="generatedView.columns"
				:disable-hide="!canManageTable(view)" />
		</NcAppSettingsSection>
		<!--filtering-->
		<NcAppSettingsSection v-if="columns != null && canManageTable(view)" id="filter" :title="t('tables', 'Filter')">
			<FilterForm
				:filters="mutableView.filter"
				:view-filters="view.filter"
				:generated-filters="generatedView.filter"
				:columns="allColumns" />
		</NcAppSettingsSection>
		<!--sorting-->
		<NcAppSettingsSection v-if="columns != null" id="sort" :title="t('tables', 'Sort')">
			<SortForm
				:sort="mutableView.sort"
				:view-sort="view.sort"
				:generated-sort="generatedView.sort"
				:columns="allColumns" />
		</NcAppSettingsSection>

		<div class="row sticky">
			<div class="fix-col-4 space-T end">
				<button v-if="!localLoading && type === 'edit-view'" class="primary" :aria-label="createNewViewText" @click="createNewView()">
					{{ createNewViewText }}
				</button>
				<button v-if="!localLoading" class="primary" :aria-label="saveText" @click="saveView()">
					{{ saveText }}
				</button>
			</div>
		</div>
	</NcAppSettingsDialog>
</template>

<script>
import { NcAppSettingsDialog, NcAppSettingsSection, NcEmojiPicker, NcButton } from '@nextcloud/vue'
import { showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/dist/index.css'
import FilterForm from '../partials/editViewPartials/filter/FilterForm.vue'
import SortForm from '../partials/editViewPartials/sort/SortForm.vue'
import SelectedViewColumns from '../partials/editViewPartials/SelectedViewColumns.vue'
import { MetaColumns } from '../../../shared/components/ncTable/mixins/metaColumns.js'
import permissionsMixin from '../../../shared/components/ncTable/mixins/permissionsMixin.js'

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
		// Possible types: If a new view is created or a existing view or table is edit
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
		getMetaColumns() {
			return MetaColumns
		},
		getModalTitle() {
			switch (this.type) {
			case 'edit-view': return t('tables', 'Edit view')
			case 'edit-table': return t('tables', 'Edit table')
			case 'create-view': return t('tables', 'Create view')
			default: throw Error('The type ' + this.type + ' is not valid for this modal')
			}
		},
		emojiPlaceholder() {
			switch (this.type) {
			case 'edit-table': return t('tables', 'Select emoji for table')
			case 'edit-view':
			case 'create-view': return t('tables', 'Select emoji for view')
			default: throw Error('The type ' + this.type + ' is not valid for this modal')
			}
		},
		titlePlaceholder() {
			switch (this.type) {
			case 'edit-view': return t('tables', 'New title of the view')
			case 'edit-table': return t('tables', 'New title of the table')
			case 'create-view': return t('tables', 'Title of the new view')
			default: throw Error('The type ' + this.type + ' is not valid for this modal')
			}
		},
		saveText() {
			switch (this.type) {
			case 'edit-table': return t('tables', 'Save Table')
			case 'edit-view':
				if (this.viewSettings) return t('tables', 'Save modified View')
				else return t('tables', 'Save View')
			case 'create-view': return t('tables', 'Create View')
			default: throw Error('The type ' + this.type + ' is not valid for this modal')
			}
		},
		createNewViewText() {
			return t('tables', 'Create new view')
		},
		type() {
			if (!this.showModal) return 'create-view'
			if (this.createView) return 'create-view'
			else return 'edit-view'
		},
		generateViewConfigData() {
			if (!this.viewSetting) return this.view
			const mergedViewSettings = JSON.parse(JSON.stringify(this.view))
			if (this.viewSetting.hiddenColumns && this.viewSetting.hiddenColumns.length !== 0) {
				mergedViewSettings.columns = this.view.columns.filter(id => !this.viewSetting.hiddenColumns.includes(id))
			} else {
				mergedViewSettings.columns = this.view.columns
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
			this.columns = await this.$store.dispatch('getColumnsFromBE', { tableId: this.mutableView.tableId, viewId: this.mutableView.id })
			if (this.selectedColumns === null) this.selectedColumns = this.columns.map(col => col.id)
			// Show columns of view first
			this.allColumns = this.columns.concat(this.getMetaColumns)
			this.allColumns = (this.view.columns ?? this.selectedColumns).map(id => this.allColumns.find(col => col.id === id)).concat(this.allColumns.filter(col => !(this.view.columns ?? this.selectedColumns).includes(col.id)))
		},
		async saveView() {
			if (this.title === '') {
				let titleErrorText
				switch (this.type) {
				case 'edit-view': titleErrorText = t('tables', 'Cannot update view.'); break
				case 'edit-table': titleErrorText = t('tables', 'Cannot update table.'); break
				case 'create-view': titleErrorText = t('tables', 'Cannot create view.'); break
				default: throw Error('The type ' + this.type + ' is not valid for this modal')
				}
				titleErrorText += ' ' + t('tables', 'Title is missing.')
				showError(titleErrorText)
				this.errorTitle = true
			} else {
				this.localLoading = true
				if (this.type === 'create-view') {
					this.mutableView.id = await this.sendNewViewToBE()
				}
				const success = await this.updateViewToBE(this.mutableView.id)
				this.localLoading = false
				if (success) {
					await this.$router.push('/view/' + this.mutableView.id).catch(err => err)
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
			const filteredSortingRules = this.mutableView.sort.filter(sortRule => sortRule.columnId !== undefined)
			const data = {
				data: {
					title: this.title,
					emoji: this.icon,
					columns: JSON.stringify(newSelectedColumnIds),
					sort: JSON.stringify(filteredSortingRules),
				},
			}

			const filteredFilteringRules = this.mutableView.filter.map(filterGroup => filterGroup.filter(fil => fil.columnId !== undefined && fil.operator !== undefined)).filter(filterGroup => filterGroup.length > 0)
			data.data.filter = JSON.stringify(filteredFilteringRules)
			const res = await this.$store.dispatch('updateView', { id, data })
			if (res) {
				this.$emit('reload-view')
				return res
			} else {
				showError(this.type === 'edit-table' ? t('tables', 'Could not update table') : t('tables', 'Could not update view'))
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
