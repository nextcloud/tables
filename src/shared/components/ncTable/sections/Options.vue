<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="options">
		<div v-if="showOptions && (config.canReadRows || (config.canCreateRows && rows.length > 0))" class="fix-col-4" style="justify-content: space-between;">
			<div :class="{'add-padding-left': isSmallMobile }"
				class="actionButtonsLeft">
				<NcButton v-if="!isSmallMobile && config.canCreateRows"
					:aria-label="t('tables', 'Create row')"
					:close-after-click="true"
					type="tertiary"
					data-cy="createRowBtn"
					@click="$emit('create-row')">
					{{ t('tables', 'Create row') }}
					<template #icon>
						<Plus :size="25" />
					</template>
				</NcButton>
				<NcButton v-if="isSmallMobile && config.canCreateRows"
					:close-after-click="true"
					:aria-label="t('tables', 'Create Row')"
					type="tertiary"
					data-cy="createRowBtn"
					@click="$emit('create-row')">
					<template #icon>
						<Plus :size="25" />
					</template>
				</NcButton>
				<div class="searchAndFilter">
					<SearchForm
						:columns="columns"
						:search-string="getSearchString"
						@set-search-string="str => $emit('set-search-string', str)" />
				</div>
			</div>

			<div v-if="selectedRows.length > 0" class="selected-rows-option">
				<div style="padding: 10px; color: var(--color-text-maxcontrast);">
					{{ n('tables', '%n selected row', '%n selected rows', selectedRows.length, {}) }}
				</div>
				<NcActions type="secondary" :force-name="true" :inline="showFullOptions ? 2 : 0">
					<NcActionButton
						@click="exportCsv">
						<template #icon>
							<Export :size="20" />
						</template>
						{{ t('tables', 'Export CSV') }}
					</NcActionButton>
					<NcActionButton v-if="config.canDeleteRows"
						@click="deleteSelectedRows">
						<template #icon>
							<Delete :size="20" />
						</template>
						{{ t('tables', 'Delete') }}
					</NcActionButton>
					<NcActionButton v-if="!showFullOptions" @click="deselectAllRows">
						<template #icon>
							<Check :size="20" />
						</template>
						{{ t('tables', 'Uncheck all') }}
					</NcActionButton>
				</NcActions>
			</div>
		</div>
	</div>
</template>

<script>
import { NcButton, NcActions, NcActionButton } from '@nextcloud/vue'
import { emit } from '@nextcloud/event-bus'
import Plus from 'vue-material-design-icons/Plus.vue'
import Check from 'vue-material-design-icons/CheckboxBlankOutline.vue'
import Delete from 'vue-material-design-icons/TrashCanOutline.vue'
import Export from 'vue-material-design-icons/Export.vue'
import viewportHelper from '../../../mixins/viewportHelper.js'
import SearchForm from '../partials/SearchForm.vue'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'Options',

	components: {
		NcActions,
		NcActionButton,
		SearchForm,
		NcButton,
		Plus,
		Check,
		Delete,
		Export,
	},

	mixins: [viewportHelper],

	props: {
		selectedRows: {
			type: Array,
			default: () => [],
		},
		rows: {
			type: Array,
			default: () => [],
		},
		elementId: {
			type: Number,
			default: null,
		},
		isView: {
			type: Boolean,
			default: true,
		},
		showOptions: {
			type: Boolean,
			default: true,
		},
		columns: {
			type: Array,
			default: null,
		},
		viewSetting: {
			type: Object,
			default: null,
		},
		config: {
			type: Object,
			default: null,
		},
	},

	data() {
		return {
			optionsDivWidth: null,
		}
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
			return this.viewSetting?.searchString || ''
		},
		showFullOptions() {
			 return this.optionsDivWidth > 800
		},
	},

	created() {
		this.updateOptionsDivWidth()
		window.addEventListener('resize', this.updateOptionsDivWidth)
	},

	methods: {
		t,
		updateOptionsDivWidth() {
			this.optionsDivWidth = document.getElementsByClassName('options row')[0]?.offsetWidth
		},
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
		deselectAllRows() {
			emit('tables:selected-rows:deselect', { elementId: this.elementId, isView: this.isView })
		},
	},
}
</script>

<style scoped lang="scss">

.sticky {
	position: -webkit-sticky; /* Safari */
	position: sticky;
	top: 90px;
	inset-inline-start: 0;
}

.selected-rows-option {
	justify-content: flex-end;
	display: inline-flex;
	white-space: nowrap;
	overflow: hidden;
	min-width: fit-content;
}

.add-padding-left {
	padding-inline-start: calc(var(--default-grid-baseline) * 1);
}

:deep(.counter-bubble__counter) {
	max-width: fit-content;
}

.actionButtonsLeft {
	display: inline-flex;
	align-items: center;
	padding-inline-start: calc(var(--default-grid-baseline) * 1);
}

:deep(.actionButtonsLeft button) {
	min-width: fit-content;
	margin-top: 5px;
}

.searchAndFilter {
	margin-inline-start: calc(var(--default-grid-baseline) * 3);
	width: auto;
	min-width: 100px;
}

</style>
