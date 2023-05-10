<template>
	<div class="options">
		<div v-if="showOptions && canReadTable(table)" class="fix-col-4" style="justify-content: space-between;">
			<div :class="{'add-padding-left': isSmallMobile }" class="actionButtonsLeft">
				<NcButton v-if="!isSmallMobile && canCreateRowInTable(table)"
					:close-after-click="true"
					type="tertiary"
					@click="$emit('create-row')">
					{{ t('tables', 'Create row') }}
					<template #icon>
						<Plus :size="25" />
					</template>
				</NcButton>
				<NcButton v-if="isSmallMobile && canCreateRowInTable(table)"
					:close-after-click="true"
					type="tertiary"
					@click="$emit('create-row')">
					<template #icon>
						<Plus :size="25" />
					</template>
				</NcButton>
				<div class="searchAndFilter">
					<SearchForm
						:columns="columns"
						:search-string="getSearchString"
						@add-filter="filter => $emit('add-filter', filter)"
						@set-search-string="str => $emit('set-search-string', str)" />
				</div>
			</div>

			<div v-if="selectedRows.length > 0" class="selected-rows-option">
				<div style="padding: 10px; color: var(--color-text-maxcontrast);">
					{{ n('tables', '%n selected row', '%n selected rows', selectedRows.length, {}) }}
				</div>
      &nbsp;&nbsp;
				<NcButton v-if="!isSmallMobile"
					icon="icon-download"
					:title="t('tables', 'Export as CSV')"
					@click="exportCsv">
					<template #icon>
						<Export :size="20" />
					</template>
					{{ t('tables', 'Export CSV') }}
				</NcButton>
				<NcButton v-if="isSmallMobile"
					icon="icon-download"
					:title="t('tables', 'Export as CSV')"
					@click="exportCsv">
					<template #icon>
						<Export :size="20" />
					</template>
				</NcButton>
      &nbsp;&nbsp;
				<NcButton v-if="!isSmallMobile && canDeleteData(table)"
					icon="icon-delete"
					:title="t('tables', 'Delete')"
					@click="deleteSelectedRows">
					{{ t('tables', 'Delete') }}
					<template #icon>
						<Delete :size="20" />
					</template>
				</NcButton>
				<NcButton v-if="isSmallMobile && canDeleteData(table)"
					icon="icon-delete"
					:title="t('tables', 'Delete')"
					@click="deleteSelectedRows">
					<template #icon>
						<Delete :size="20" />
					</template>
				</NcButton>
			</div>
		</div>
	</div>
</template>

<script>
import { NcButton } from '@nextcloud/vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import Export from 'vue-material-design-icons/Export.vue'
import viewportHelper from '../../../mixins/viewportHelper.js'
import permissionsMixin from '../mixins/permissionsMixin.js'
import SearchForm from '../partials/SearchForm.vue'

export default {
	name: 'Options',

	components: {
		SearchForm,
		NcButton,
		Plus,
		Delete,
		Export,
	},

	mixins: [viewportHelper, permissionsMixin],

	props: {
		selectedRows: {
			type: Array,
			default: () => [],
		},
		rows: {
			type: Array,
			default: () => [],
		},
		showOptions: {
			type: Boolean,
			default: true,
		},
		table: {
			type: Object,
			default: () => {},
		},
		columns: {
		      type: Array,
		      default: null,
		    },
		view: {
		      type: Object,
		      default: null,
		    },
	},

	computed: {
		getSelectedRows() {
			const rows = []
			this.selectedRows.forEach(id => {
				rows.push(this.getRowById(id))
			})
			return rows
		},
		getSearchString() {
			return this.view?.searchString || ''
		},
	},

	methods: {
		exportCsv() {
			this.$emit('download-csv', this.getSelectedRows)
		},
		getRowById(rowId) {
			const index = this.rows.findIndex(row => row.id === rowId)
			return this.rows[index]
		},
		deleteSelectedRows() {
			this.$emit('delete-selected-rows', this.selectedRows)
		},
	},
}
</script>

<style scoped lang="scss">

.sticky {
	position: -webkit-sticky; /* Safari */
	position: sticky;
	top: 90px;
	left: 0;
}

.selected-rows-option {
	justify-content: flex-end;
	display: inline-flex;
}

.add-padding-left {
	padding-left: calc(var(--default-grid-baseline) * 1);
}

:deep(.counter-bubble__counter) {
	max-width: fit-content;
}

.actionButtonsLeft {
	display: inline-flex;
	align-items: center;
}

:deep(.actionButtonsLeft button) {
	min-width: fit-content;
}

.searchAndFilter {
	margin-left: calc(var(--default-grid-baseline) * 3);
}

</style>
