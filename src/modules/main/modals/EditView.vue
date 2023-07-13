<template>
	<NcAppSettingsDialog :open.sync="open" :show-navigation="true" :title="t('tables', 'Edit view')">
		<NcAppSettingsSection v-if="columns === null" id="loading" :title="t('tables', 'Loading')">
			<div class="icon-loading" />
		</NcAppSettingsSection>
		<!--title & emoji-->
		<NcAppSettingsSection v-if="columns != null" id="title" :title="t('tables', 'Title')">
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
					:placeholder="t('tables', 'Title of the new view')">
			</div>
		</NcAppSettingsSection>
		<!--columns & order-->
		<NcAppSettingsSection v-if="columns != null" id="columns-and-order" :title="t('tables', 'Columns')">
			<SelectedViewColumns
				:columns="allColumns"
				:selected-columns="selectedColumns"
				:is-base-view="view.isBaseView" />
		</NcAppSettingsSection>
		<!--filtering-->
		<NcAppSettingsSection v-if="columns != null && !view.isBaseView" id="filter" :title="t('tables', 'Filter')">
			<FilterForm :filters="view.filter" :columns="allColumns" />
		</NcAppSettingsSection>
		<!--sorting-->
		<NcAppSettingsSection v-if="columns != null" id="sort" :title="t('tables', 'Sort')">
			<SortForm :sort="view.sort" :columns="allColumns" />
		</NcAppSettingsSection>

		<div class="row">
			<div class="fix-col-4 space-T end">
				<button v-if="!localLoading" class="primary" :aria-label="t('tables', 'Save View')" @click="actionConfirm()">
					{{ t('tables', 'Save') }}
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

export default {
	name: 'EditView',
	components: {
		NcAppSettingsDialog,
		NcAppSettingsSection,
		NcEmojiPicker,
		NcButton,
		FilterForm,
		SelectedViewColumns,
		SortForm,
	},
	props: {
		showModal: {
			type: Boolean,
			default: false,
		},
		view: {
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
		}
	},
	computed: {
		getMetaColumns() {
			return MetaColumns
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
			this.columns = await this.$store.dispatch('getColumnsFromBE', { tableId: this.view.tableId, viewId: this.view.id })
			// Show columns of view first
			this.allColumns = this.columns.concat(this.getMetaColumns)
			this.allColumns = this.view.columns.map(id => this.allColumns.find(col => col.id === id)).concat(this.allColumns.filter(col => !this.view.columns.includes(col.id)))
		},
		async actionConfirm() {
			if (this.title === '') {
				showError(t('tables', 'Cannot update view. Title is missing.'))
				this.errorTitle = true
			} else {
				this.localLoading = true
				const success = await this.updateViewToBE(this.view.id)
				this.localLoading = false
				if (success) {
					this.actionCancel()
				}
			}
		},
		async updateViewToBE(id) {
			const newSelectedColumnIds = this.allColumns.map(col => col.id).filter(id => this.selectedColumns.includes(id))
			const filteredSortingRules = this.view.sort.filter(sortRule => sortRule.columnId !== undefined)
			const data = {
				data: {
					title: this.title,
					emoji: this.icon,
					columns: JSON.stringify(newSelectedColumnIds),
					sort: JSON.stringify(filteredSortingRules),
				},
			}

			if (!this.view.isBaseView) {
				const filteredFilteringRules = this.view.filter.map(filterGroup => filterGroup.filter(fil => fil.columnId !== undefined && fil.operator !== undefined)).filter(filterGroup => filterGroup.length > 0)
				data.data.filter = JSON.stringify(filteredFilteringRules)
			}
			const res = await this.$store.dispatch('updateView', { id, data })
			if (res) {
				console.debug(res, this.view)
				// TODO: Only reload if something changed
				this.$emit('reload-view')
				return res
			} else {
				showError(t('tables', 'Could not update view'))
			}
		},
		reset() {
			this.title = this.view.title
			this.icon = this.view.emoji
			this.errorTitle = false
			this.selectedColumns = [...this.view.columns]
			this.allColumns = []
			this.localLoading = false
			this.columns = null
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
</style>
