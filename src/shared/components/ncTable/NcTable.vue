<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<!--

This is a reusable component. There are no outside dependency's.

Emitted events
==============
edit-row                -> click on edit button in a row
update-selected-rows    -> send an array with selected row IDs
create-row              -> click on create (plus) button
create-column
edit-columns
delete-selected-rows
set-search-string       -> @param string
import                  -> click on import button on tables menu

Props
=====
rows <array>            -> Array with row-objects { "columnId": 1, "value": "some" }
columns <array>         -> Array with column-objects { "id":2, "tableId":1, "title":"Description", ... }
config                  -> config object for the table
  options
    show-create-row [true]
    show-delete-rows [true]
  table
    rows
      show-delete-button [false]
      show-edit-button [true]
      action-buttons-position [right]
      action-button-sticky [true]
    columns
      show-inline-edit-button [true]

Bus events
==========
deselect-all-rows        -> unselect all rows, e.g. after deleting selected rows

-->

<template>
	<div ref="table" class="NcTable" data-cy="ncTable">
		<div class="options row" style="padding-right: calc(var(--default-grid-baseline) * 2);">
			<Options :rows="rows" :columns="parsedColumns" :element-id="elementId" :is-view="isView"
				:selected-rows="localSelectedRows" :show-options="parsedColumns.length !== 0"
				:view-setting.sync="localViewSetting" :config="config" @create-row="$emit('create-row')"
				@download-csv="data => downloadCsv(data, parsedColumns, downloadTitle)"
				@set-search-string="str => setSearchString(str)"
				@delete-selected-rows="rowIds => $emit('delete-selected-rows', rowIds)" />
		</div>
		<div class="custom-table row">
			<CustomTable v-if="config.canReadRows || (config.canCreateRows && rows.length > 0)" :columns="parsedColumns"
				:rows="rows" :is-view="isView" :element-id="elementId" :view-setting.sync="localViewSetting"
				:config="config" @create-row="$emit('create-row')"
				@edit-row="rowId => $emit('edit-row', rowId)"
				@create-column="$emit('create-column')"
				@edit-column="col => $emit('edit-column', col)"
				@delete-column="col => $emit('delete-column', col)"
				@update-selected-rows="rowIds => localSelectedRows = rowIds"
				@download-csv="data => downloadCsv(data, parsedColumns, table)">
				<template #actions>
					<slot name="actions" />
				</template>
			</CustomTable>
			<NcEmptyContent v-else-if="config.canCreateRows && rows.length === 0"
				:name="t('tables', 'Create rows')"
				:description="t('tables', 'You are not allowed to read this table, but you can still create rows.')">
				<template #icon>
					<Plus :size="25" />
				</template>
				<template #action>
					<NcButton :aria-label="t('tables', 'Create row')" type="primary"
						@click="$emit('create-row')">
						<template #icon>
							<Plus :size="25" />
						</template>
						{{ t('tables', 'Create row') }}
					</NcButton>
				</template>
			</NcEmptyContent>
			<NcEmptyContent v-else
				:name="t('tables', 'No permissions')"
				:description="t('tables', 'You have no permissions for this table.')">
				<template #icon>
					<Cancel :size="25" />
				</template>
			</NcEmptyContent>
		</div>
	</div>
</template>

<script>

import Options from './sections/Options.vue'
import CustomTable from './sections/CustomTable.vue'
import exportTableMixin from './mixins/exportTableMixin.js'
import { NcEmptyContent, NcButton } from '@nextcloud/vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Cancel from 'vue-material-design-icons/Cancel.vue'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { parseCol } from './mixins/columnParser.js'
import { AbstractColumn } from './mixins/columnClass.js'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'NcTable',

	components: { CustomTable, Options, NcButton, NcEmptyContent, Plus, Cancel },

	mixins: [exportTableMixin],

	props: {
		rows: {
			type: Array,
			default: () => [],
		},
		columns: {
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
		downloadTitle: {
			type: String,
			default: t('tables', 'Download'),
		},
		viewSetting: {
			type: Object,
			default: null,
		},
		selectedRows: {
			type: Array,
			default: null,
		},
		canReadRows: {
			type: Boolean,
			default: true,
		},
		canCreateRows: {
			type: Boolean,
			default: true,
		},
		canEditRows: {
			type: Boolean,
			default: true,
		},
		canDeleteRows: {
			type: Boolean,
			default: true,
		},
		canCreateColumns: {
			type: Boolean,
			default: true,
		},
		canEditColumns: {
			type: Boolean,
			default: true,
		},
		canDeleteColumns: {
			type: Boolean,
			default: true,
		},
		canDeleteTable: {
			type: Boolean,
			default: true,
		},
		canSelectRows: {
			type: Boolean,
			default: true,
		},
		canHideColumns: {
			type: Boolean,
			default: true,
		},
		canFilter: {
			type: Boolean,
			default: true,
		},
		showActions: {
			type: Boolean,
			default: true,
		},
	},
	data() {
		return {
			localSelectedRows: [],
			localViewSetting: this.viewSetting ?? {},
			viewerAppAvailable: !!window.OCA?.Viewer?.open,
			table: null,
		}
	},
	computed: {
		config() {
			return {
				canReadRows: this.canReadRows,
				canCreateRows: this.canCreateRows,
				canEditRows: this.canEditRows,
				canDeleteRows: this.canDeleteRows,
				canCreateColumns: this.canCreateColumns,
				canEditColumns: this.canEditColumns,
				canDeleteColumns: this.canDeleteColumns,
				canDeleteTable: this.canDeleteTable,
				canSelectRows: this.canSelectRows,
				canHideColumns: this.canHideColumns,
				canFilter: this.canFilter,
				showActions: this.showActions,
			}
		},
		parsedColumns() {
			if (this.columns.length && !(this.columns[0] instanceof AbstractColumn)) {
				return this.columns.map(col => parseCol(col))
			}
			return this.columns
		},
	},
	watch: {
		localSelectedRows() {
			this.$emit('update:selectedRows', this.localSelectedRows)
		},
		localViewSetting() {
			this.$emit('update:viewSetting', this.localViewSetting)
		},
		viewSetting() {
			this.localViewSetting = this.viewSetting
		},
	},
	mounted() {
		subscribe('tables:selected-rows:deselect', ({ elementId, isView }) => this.deselectRows(elementId, isView))

		if (this.viewerAppAvailable) {
			this.$refs.table.addEventListener('click', function(event) {
				const $img = event.target.closest('.image_container.widget-file img')
				if (!$img) {
					return
				}

				const filePath = decodeURIComponent($img.src.match(/\/dav\/files\/(.+?)\/(.+)/)[2])
				OCA.Viewer.open({ path: filePath, list: [{ filename: filePath }] })
			})
		}
	},
	beforeDestroy() {
		unsubscribe('tables:selected-rows:deselect', ({ elementId, isView }) => this.deselectRows(elementId, isView))
	},
	methods: {
		t,
		deselectRows(elementId, isView) {
			if (parseInt(elementId) === parseInt(this.elementId) && isView === this.isView) {
				this.localSelectedRows = []
			}
		},
		setSearchString(str) {
			this.localViewSetting.searchString = str !== '' ? str : null
			this.localViewSetting = JSON.parse(JSON.stringify(this.localViewSetting))
		},
	},
}
</script>

<style scoped lang="scss">
.options.row {
	width: var(--app-content-width, auto);
	position: sticky;
	top: 60px;
	inset-inline-start: 0;
	z-index: 15;
	background-color: var(--color-main-background);
	padding-top: 4px; // fix to show buttons completely
	padding-bottom: 4px; // to make it nice with the padding-top
}
</style>

<style lang="scss" scoped>
.image_container.widget-file {
	height: auto !important;
}

.image_container.widget-file img {
	cursor: pointer !important;
}
</style>
