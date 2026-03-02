<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="container">
		<table class="tables-list__table">
			<thead class="tables-list__thead">
				<TableHeader :columns="columns"
					:selected-rows="selectedRows"
					:rows="rows"
					:view-setting.sync="localViewSetting"
					:config="config"
					:pinned-column-id="pinnedColumnId"
					:column-widths="columnWidths"
					@create-row="$emit('create-row')"
					@create-column="$emit('create-column')"
					@edit-column="col => $emit('edit-column', col)"
					@delete-column="col => $emit('delete-column', col)"
					@download-csv="data => $emit('download-csv', data)"

					@select-all-rows="selectAllRows"
					@pin-column="setPinnedColumn">
					<template #actions>
						<slot name="actions" />
					</template>
				</TableHeader>
			</thead>
			<transition-group
				name="table-row"
				tag="tbody"
				:css="rowAnimation"
				@after-leave="disableRowAnimation">
				<TableRow v-for="row in currentPageRows"
					:key="row.id"
					data-cy="customTableRow"
					:row="row"
					:columns="columns"
					:selected="isRowSelected(row?.id)"
					:view-setting.sync="localViewSetting"
					:config="config"
					:element-id="elementId"
					:is-view="isView"

					:pinned-column-id="pinnedColumnId"
					:column-widths="columnWidths"
					@update-row-selection="updateRowSelection"
					@edit-row="rowId => $emit('edit-row', rowId)" />
			</transition-group>
		</table>
	</div>
</template>

<script>
import TableHeader from '../partials/TableHeader.vue'
import TableRow from '../partials/TableRow.vue'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'CustomTable',

	components: {
		TableRow,
		TableHeader,
	},

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
			selectedRows: [],
			searchTerm: null,
			localViewSetting: this.viewSetting,
			pageNumber: 1,
			rowsPerPage: 100,
			rowAnimation: false,
			pinnedColumnId: null,
			columnWidths: null,
		}
	},

	computed: {
		currentPageRows() {
			return this.rows.slice((this.pageNumber - 1) * this.rowsPerPage, ((this.pageNumber - 1) * this.rowsPerPage) + this.rowsPerPage)
		},
	},

	watch: {
		localViewSetting() {
			this.$emit('update:viewSetting', this.localViewSetting)
		},
		viewSetting() {
			this.localViewSetting = this.viewSetting
		},
		pinnedColumnId(newVal) {
			if (newVal !== null) {
				this.$nextTick(() => this.measureColumnWidths())
			} else {
				this.columnWidths = null
			}
		},
	},

	mounted() {
		subscribe('tables:selected-rows:deselect', ({ elementId, isView }) => this.deselectAllRows(elementId, isView))
		subscribe('tables:row:animate', this.enableRowAnimation)
		subscribe('tables:pagination-changed', this.handlePaginationChanged)
	},
	beforeDestroy() {
		unsubscribe('tables:selected-rows:deselect', ({ elementId, isView }) => this.deselectAllRows(elementId, isView))
		unsubscribe('tables:row:animate', this.enableRowAnimation)
		unsubscribe('tables:pagination-changed', this.handlePaginationChanged)
	},

	methods: {
		t,
		setPinnedColumn(columnId) {
			this.pinnedColumnId = this.pinnedColumnId === columnId ? null : columnId
		},
		measureColumnWidths() {
			const headerRow = this.$el.querySelector('thead tr')
			if (!headerRow) return
			const widths = {}
			headerRow.querySelectorAll('th[data-col-id]').forEach(th => {
				widths[parseInt(th.dataset.colId, 10)] = th.offsetWidth
			})
			if (JSON.stringify(widths) !== JSON.stringify(this.columnWidths)) {
				this.columnWidths = widths
			}
		},
		handlePaginationChanged({ pageNumber, rowsPerPage }) {
			this.pageNumber = pageNumber
			if (rowsPerPage) {
				this.rowsPerPage = rowsPerPage
			}
		},
		deselectAllRows(elementId, isView) {
			if (parseInt(elementId) === parseInt(this.elementId) && isView === this.isView) {
				this.selectedRows = []
			}
		},
		selectAllRows(value) {
			this.selectedRows = []
			if (value) {
				this.rows.forEach(item => { this.selectedRows.push(item.id) })
			}
			this.$emit('update-selected-rows', this.selectedRows)
		},
		isRowSelected(id) {
			return this.selectedRows.includes(id)
		},
		updateRowSelection(values) {
			const id = values.rowId
			const v = values.value

			if (this.selectedRows.includes(id) && !v) {
				const index = this.selectedRows.indexOf(id)
				if (index > -1) {
					this.selectedRows.splice(index, 1)
				}
				this.$emit('update-selected-rows', this.selectedRows)
			}
			if (!this.selectedRows.includes(id) && v) {
				this.selectedRows.push(values.rowId)
				this.$emit('update-selected-rows', this.selectedRows)
			}
		},
		enableRowAnimation() {
			this.rowAnimation = true
		},
		disableRowAnimation() {
			this.rowAnimation = false
		},
	},
}
</script>

<style lang="scss" scoped>
:deep(.text-editor__wrapper .paragraph-content:last-child) {
	margin-bottom: 0!important;
}

:deep(.text-editor__wrapper .ProseMirror > *:first-child) {
	margin-top: 0!important;
}

:deep(table) {
	position: relative;
	border-collapse: collapse;
	border-spacing: 0;
	table-layout: auto;
	width: 100%;
	border: none;

	* {
		border: none;
	}
	// white-space: nowrap;

	td, th {
		padding-inline-end: 8px;
		max-width: 500px;
	}

	td .showOnHover, th .showOnHover {
		opacity: 0;
	}

	td:hover .showOnHover, th:hover .showOnHover, .showOnHover:focus-within {
		opacity: 1;
	}

	td:not(:first-child), th:not(:first-child) {
		padding-inline: 8px;
	}

	tr {
		height: 51px;
		background-color: var(--color-main-background);
	}

	thead {
		position: sticky;
		top: 108px;
		z-index: 6;

		tr {
			th {
				vertical-align: middle;
				color: var(--color-text-maxcontrast);
				box-shadow: inset 0 -1px 0 var(--color-border); // use box-shadow instead of border to be compatible with sticky heads
				background-color: var(--color-main-background-translucent);
				z-index: 5;
			}
		}
	}

	tbody {

		td {
			text-align: start;
			vertical-align: middle;
			border: 1px solid var(--color-border-dark);
		}

		td > div {
			max-height: 200px;
			overflow-y: auto;
		}

		tr:active, tr:hover, tr:focus, tr:hover .editor-wrapper .editor {
			background-color: var(--color-background-dark);
		}

		.editor-wrapper .editor {
			background-color: inherit;
		}

		.selected:active, .selected:hover, .selected:focus, tr:hover .editor-wrapper .editor {
			background-color: inherit;
		}

		// viewer integration
		.editor-wrapper {
			min-width: 100px;
			overflow-y: auto;

			.preview .widget-custom {
				margin-top: 0;
				margin-bottom: 0;
				max-height: 200px;
				overflow: hidden;

				img {
					height: auto !important;
					width: 100% !important;
				}
			}

			.preview [data-node-view-content] {
				display: none;
			}
		}

		// inline editing
		.inline-editing-container {
			position: relative;
			width: 100%;
			overflow-y: hidden;

			.cell-input {
				width: 100%;
				height: 100%;
				border-radius: 0;
				padding: 4px 8px;
			}
		}

		.icon-loading-inline {
			position: absolute;
			inset-inline-end: 8px;
			top: 50%;
			transform: translateY(-50%);
		}

		tr:focus-within > td:last-child {
			opacity: 1;
		}
	}

	tr > th.frozen-column,
	tr > td.frozen-column {
		background-color: inherit;
		z-index: 4;
	}

	thead tr > th.frozen-column {
		z-index: 6;
		border-right: 1px solid transparent; // aligns inset shadow with td (which has a 1px border)
		box-shadow: inset 0 -1px 0 var(--color-border), inset -1px 0 0 var(--color-border-dark);
	}

	tr > td.frozen-column {
		box-shadow: inset -1px 0 0 var(--color-border-dark);
	}

	thead tr > th.frozen-column--last {
		box-shadow: inset 0 -1px 0 var(--color-border), inset -3px 0 0 var(--color-border-dark);
	}

	tr > td.frozen-column--last {
		box-shadow: inset -3px 0 0 var(--color-border-dark);
	}

	tr>th.sticky:first-child,tr>td.sticky:first-child {
		position: sticky;
		inset-inline-start: 0;
		padding-inline: calc(var(--default-grid-baseline) * 4);
		width: 60px;
		background-color: inherit;
		z-index: 5;
	}

	tr>th.sticky:last-child,tr>td.sticky:last-child {
		position: sticky;
		inset-inline-end: 0;
		width: 55px;
		background-color: inherit;
		padding-inline-end: 16px;
	}

	tr>td.sticky:last-child {
		// visibility: hidden;
		opacity: 0;
	}

	tr:hover>td:last-child {
		// visibility: visible;
		opacity: 1;
	}

}

.table-row-leave-active {
  transition: all 600ms ease;
}

.table-row-leave-to {
  opacity: 0;
  height: 0 !important;
  padding-top: 0 !important;
  padding-bottom: 0 !important;
  margin-top: 0 !important;
  margin-bottom: 0 !important;
  transform: translateX(-1rem);
}

</style>
