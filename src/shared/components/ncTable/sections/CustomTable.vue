<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="container" :class="{ 'container--cards': currentLayout !== 'table' }">
		<table v-if="currentLayout === 'table'" class="tables-list__table">
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
		<div v-else class="card-layout" :class="`card-layout--${currentLayout}`">
			<button v-for="row in currentPageRows"
				:key="row.id"
				type="button"
				class="layout-card"
				:data-cy="`${currentLayout}LayoutCard`"
				@click="$emit('edit-row', row.id)">
				<div class="layout-card__image-wrapper">
					<img v-if="getPreviewUrl(row)"
						:src="getPreviewUrl(row)"
						:alt="getCardTitle(row)"
						class="layout-card__image">
					<div v-else class="layout-card__no-image">
						{{ t('tables', 'No image') }}
					</div>
					<div class="layout-card__title-banner">
						{{ getCardTitle(row) }}
					</div>
				</div>
				<div v-if="currentLayout === 'gallery'" class="layout-card__body" data-cy="galleryLayoutBody">
					<ul class="layout-card__metadata">
						<li v-for="item in getGalleryMetadata(row)" :key="`${row.id}-${item.columnId}`" data-cy="galleryMetadataItem">
							<span class="layout-card__metadata-label">{{ item.title }}</span>
							<span class="layout-card__metadata-value">{{ item.value }}</span>
						</li>
					</ul>
				</div>
			</button>
		</div>
		<div v-if="totalPages > 1" class="pagination-footer" :class="{'large-width': !appNavCollapsed || isMobile}">
			<div class="pagination-items">
				<NcButton type="tertiary" :disabled="totalPages === 1 || pageNumber <= 1" :aria-label="t('tables', 'Go to first page')" @click="pageNumber = 1">
					<template #icon>
						<PageFirstIcon :size="20" />
					</template>
				</NcButton>
				<NcButton type="tertiary" :disabled="totalPages === 1 || pageNumber <= 1" :aria-label="t('tables', 'Go to previous page')" @click="pageNumber--">
					<template #icon>
						<ChevronLeftIcon :size="20" />
					</template>
				</NcButton>
				<div class="page-number">
					<NcSelect
						v-model="pageNumber"
						:options="allPageNumbersArray"
						:clearable="false"
						:aria-label-combobox="t('tables', 'Page number')">
						<template #selected-option-container="{ option }">
							<span class="selected-page">
								{{ option.label }} of {{ totalPages }}
							</span>
						</template>
					</NcSelect>
				</div>
				<NcButton type="tertiary" :disabled="totalPages === 1 || pageNumber >= totalPages" :aria-label="t('tables', 'Go to next page')" @click="pageNumber++">
					<template #icon>
						<ChevronRightIcon :size="20" />
					</template>
				</NcButton>
				<NcButton type="tertiary" :disabled="totalPages === 1 || pageNumber >= totalPages" :aria-label="t('tables', 'Go to last page')" @click="pageNumber = totalPages">
					<template #icon>
						<PageLastIcon :size="20" />
					</template>
				</NcButton>
			</div>
		</div>
	</div>
</template>

<script>
import TableHeader from '../partials/TableHeader.vue'
import PageLastIcon from 'vue-material-design-icons/PageLast.vue'
import PageFirstIcon from 'vue-material-design-icons/PageFirst.vue'
import ChevronRightIcon from 'vue-material-design-icons/ChevronRight.vue'
import ChevronLeftIcon from 'vue-material-design-icons/ChevronLeft.vue'
import TableRow from '../partials/TableRow.vue'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { NcButton, useIsMobile, NcSelect } from '@nextcloud/vue'
import { mapState } from 'pinia'
import { translate as t } from '@nextcloud/l10n'
import { useTablesStore } from '../../../../store/store.js'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'CustomTable',

	components: {
		TableRow,
		TableHeader,
		NcButton,
		PageLastIcon,
		PageFirstIcon,
		ChevronLeftIcon,
		ChevronRightIcon,
		NcSelect,
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

	setup() {
		return {
			isMobile: useIsMobile(),
		}
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
		...mapState(useTablesStore, ['appNavCollapsed']),
		currentLayout() {
			return ['tiles', 'gallery'].includes(this.localViewSetting?.layout) ? this.localViewSetting.layout : 'table'
		},
		allPageNumbersArray() {
			return Array.from(
				{ length: this.totalPages },
				(value, index) => 1 + index,
			)
		},
		currentPageRows() {
			return this.rows.slice((this.pageNumber - 1) * this.rowsPerPage, ((this.pageNumber - 1) * this.rowsPerPage) + this.rowsPerPage)
		},
		totalPages() {
			return Math.ceil(this.rows.length / this.rowsPerPage)
		},
	},

	watch: {
		localViewSetting() {
			this.$emit('update:viewSetting', this.localViewSetting)
		},
		viewSetting() {
			this.localViewSetting = this.viewSetting
		},
		currentLayout() {
			this.pageNumber = 1
		},
		pinnedColumnId(newVal) {
			if (newVal !== null) {
				this.$nextTick(() => this.measureColumnWidths())
			} else {
				this.columnWidths = null
			}
		},
	},

	updated() {
		if (this.pageNumber > this.totalPages || this.totalPages === 1) {
			this.pageNumber = this.totalPages
		}
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
		getCell(row, columnId) {
			return row?.data?.find(item => item?.columnId === columnId) ?? null
		},
		getPreviewUrl(row) {
			const backgroundColumn = this.getBackgroundColumn()
			const rawValue = this.getCell(row, backgroundColumn?.id)?.value
			if (rawValue === null || rawValue === undefined || rawValue === '') {
				return null
			}

			const candidates = [rawValue]
			if (typeof rawValue === 'string') {
				try {
					const parsed = JSON.parse(rawValue)
					candidates.push(parsed?.value, parsed?.resourceUrl, parsed?.thumbnailUrl)
				} catch (err) {
					// Keep raw string candidate
				}
			}

			for (const candidate of candidates) {
				if (typeof candidate !== 'string' || candidate.length === 0) {
					continue
				}
				const normalized = candidate.replace(/\\\//g, '/')
				const fileIdMatch = normalized.match(/[?&]fileId=(\d+)/i) ?? normalized.match(/\/f\/(\d+)/)
				if (fileIdMatch) {
					return generateUrl(`/core/preview?fileId=${fileIdMatch[1]}&x=1024&y=1024&a=true`)
				}
				if (normalized.includes('/core/preview?')) {
					return normalized
				}
			}

			return null
		},
		getDisplayValue(column, row) {
			if (!column) {
				return ''
			}
			const valueObject = this.getCell(row, column.id)
			if (!valueObject || valueObject.value === null || valueObject.value === undefined || valueObject.value === '') {
				return ''
			}
			if (typeof column.getValueString === 'function') {
				return String(column.getValueString(valueObject) ?? '')
			}
			return String(valueObject.value)
		},
		getTitleColumn() {
			const preferredColumnId = this.localViewSetting?.cardTitleSource
			return this.columns.find(column => column.id === preferredColumnId) ?? this.columns[1] ?? this.columns[0] ?? null
		},
		getBackgroundColumn() {
			const preferredColumnId = this.localViewSetting?.cardBackgroundSource
			return this.columns.find(column => column.id === preferredColumnId) ?? this.columns[0] ?? null
		},
		getCardTitle(row) {
			const titleColumn = this.getTitleColumn()
			return this.getDisplayValue(titleColumn, row) || `${t('tables', 'Row')} ${row.id}`
		},
		getGalleryMetadata(row) {
			const titleColumnId = this.getTitleColumn()?.id
			const backgroundColumnId = this.getBackgroundColumn()?.id
			return this.columns
				.filter(column => column.id !== titleColumnId)
				.filter(column => column.id !== backgroundColumnId)
				.map(column => ({
					columnId: column.id,
					title: column.title,
					value: this.getDisplayValue(column, row),
				}))
				.filter(item => item.value !== '')
				.slice(0, 6)
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

.selected-page{
	padding-inline-start: 5px;

	display:inline-flex;
	align-items: center;
}

.page-number{
	padding-inline: 5px;
}

.large-width{
	width: 100vw !important;
	inset-inline-start: 0 !important;
}

.pagination-items{
	background-color: var(--color-main-background);
	border-radius: var(--border-radius-large);
	pointer-events: all;

	display: flex;
	align-items: center;
}

.pagination-footer{
	box-shadow: var(--box-shadow);
	filter: drop-shadow(0 1px 6px var(--color-box-shadow));
	padding-bottom: 20px;
	width: calc(100vw - 316px);
	pointer-events: none;

	display: flex;
	justify-content: center;
	align-items: center;

	:deep(.v-select) {
		min-width: 95px !important;
	}
}

.container {
	min-width: 0;
}

.container--cards {
	width: var(--app-content-width, 100%);
	max-width: var(--app-content-width, 100%);
}

.card-layout {
	width: 100%;
	display: grid;
	grid-auto-flow: row;
	gap: 16px;
	padding-inline: calc(var(--default-grid-baseline) * 2);
	padding-top: 8px;
	grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr));
}

.layout-card {
	width: 100%;
	max-width: 100%;
	padding: 0;
	border: 1px solid var(--color-border-dark);
	border-radius: var(--border-radius-large);
	overflow: hidden;
	background: var(--color-main-background);
	text-align: start;
	cursor: pointer;
	color: var(--color-main-text);
}

.layout-card__image-wrapper {
	position: relative;
	aspect-ratio: 3 / 2;
	background: var(--color-background-dark);
}

.layout-card__image {
	width: 100%;
	height: 100%;
	object-fit: cover;
	display: block;
}

.layout-card__no-image {
	display: flex;
	align-items: center;
	justify-content: center;
	height: 100%;
	color: var(--color-text-maxcontrast);
}

.layout-card__title-banner {
	position: absolute;
	inset-inline: 0;
	bottom: 0;
	padding: 12px;
	background: rgba(0,0,0,0.4);
	color: #fff;
	text-align: start;
	font-weight: 600;
	display: flex;
	justify-content: flex-start;
}

.layout-card__body {
	padding: 12px;
	max-height: 168px;
	overflow: hidden;
}

.layout-card__title {
	font-weight: 600;
	margin-bottom: 8px;
}

.layout-card__metadata {
	list-style: none;
	padding: 0;
	margin: 0;
	display: flex;
	flex-direction: column;
	gap: 6px;
}

.layout-card__metadata li {
	display: block;
}

.layout-card__metadata li + li {
	padding-top: 8px;
}

.layout-card__metadata-label {
	font-size: 12px;
	font-weight: 600;
	color: var(--color-text-maxcontrast);
	margin-bottom: 2px;
}

.layout-card__metadata-value {
	font-weight: 400;
	color: var(--color-main-text);
	white-space: normal;
	overflow-wrap: anywhere;
	display: -webkit-box;
	-webkit-line-clamp: 2;
	-webkit-box-orient: vertical;
	overflow: hidden;
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
				box-shadow: inset 0 -1px 0 var(--color-border);
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
		opacity: 0;
	}

	tr:hover>td:last-child {
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
