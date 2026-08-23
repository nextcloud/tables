<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="options">
		<div v-if="showOptions && (config.canReadRows || (config.canCreateRows && rows.length > 0))" class="fix-col-4">
			<div :class="{ 'add-padding-left': isSmallMobile }" class="actionButtonsLeft">
				<NcButton v-if="!isSmallMobile && config.canCreateRows" :aria-label="t('tables', 'Create row')"
					:close-after-click="true" type="tertiary" data-cy="createRowBtn" @click="$emit('create-row')">
					{{ t('tables', 'Create row') }}
					<template #icon>
						<Plus :size="25" />
					</template>
				</NcButton>
				<NcButton v-if="isSmallMobile && config.canCreateRows" :close-after-click="true"
					:aria-label="t('tables', 'Create Row')" type="tertiary" data-cy="createRowBtn"
					@click="$emit('create-row')">
					<template #icon>
						<Plus :size="25" />
					</template>
				</NcButton>
				<div class="searchAndFilter">
					<SearchForm :columns="columns" :search-string="getSearchString"
						@set-search-string="str => $emit('set-search-string', str)" />
				</div>
				<NcButton v-if="config.canReadRows" :aria-label="t('tables', 'Reload rows')" type="tertiary" @click="onReload">
					<template #icon>
						<NcLoadingIcon v-if="rowsLoading" :size="20" />
						<Refresh v-else :size="20" />
					</template>
				</NcButton>
				<PaginationBlock :total="total" />
			</div>

			<div v-if="selectedRows.length > 0" class="selected-rows-option">
				<div style="padding: 10px; color: var(--color-text-maxcontrast);">
					{{ n('tables', '%n selected row', '%n selected rows', selectedRows.length, {}) }}
				</div>
				<NcActions type="secondary" :force-name="true" :inline="showFullOptions ? 2 : 0">
					<NcActionButton close-after-click data-cy="exportSelectedBtn" @click="exportSelected">
						<template #icon>
							<TrayArrowDown :size="20" />
						</template>
						{{ t('tables', 'Export selected rows') }}
					</NcActionButton>
					<NcActionButton v-if="isFiltered" close-after-click data-cy="exportFilteredBtn" @click="exportFiltered">
						<template #icon>
							<TrayArrowDown :size="20" />
						</template>
						{{ t('tables', 'Export filtered rows') }}
					</NcActionButton>
					<NcActionButton v-if="!sharedLinkUrl" :close-after-click="false" data-cy="shareSelectedBtn" @click="shareSelected">
						<template #icon>
							<ShareVariantOutline :size="20" />
						</template>
						{{ t('tables', 'Share selected rows') }}
					</NcActionButton>
					<NcActionButton v-else close-after-click data-cy="openSelectedBtn" @click="openSharedLink">
						<template #icon>
							<OpenInNew :size="20" />
						</template>
						{{ t('tables', 'Open') }}
					</NcActionButton>
					<NcActionButton v-if="config.canDeleteRows" close-after-click @click="deleteSelectedRows">
						<template #icon>
							<Delete :size="20" />
						</template>
						{{ t('tables', 'Delete') }}
					</NcActionButton>
					<NcActionButton v-if="!showFullOptions" close-after-click @click="deselectAllRows">
						<template #icon>
							<Check :size="20" />
						</template>
						{{ t('tables', 'Uncheck all') }}
					</NcActionButton>
				</NcActions>
			</div>
			<div v-else class="selected-rows-placeholder" />
		</div>
	</div>
</template>

<script>
import { NcButton, NcActions, NcActionButton, NcLoadingIcon } from '@nextcloud/vue'
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import Plus from 'vue-material-design-icons/Plus.vue'
import Check from 'vue-material-design-icons/CheckboxBlankOutline.vue'
import Delete from 'vue-material-design-icons/TrashCanOutline.vue'
import TrayArrowDown from 'vue-material-design-icons/TrayArrowDown.vue'
import Refresh from 'vue-material-design-icons/Refresh.vue'
import ShareVariantOutline from 'vue-material-design-icons/ShareVariantOutline.vue'
import OpenInNew from 'vue-material-design-icons/OpenInNew.vue'
import { generateUrl, getBaseUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/style.css'
import viewportHelper from '../../../mixins/viewportHelper.js'
import SearchForm from '../partials/SearchForm.vue'
import PaginationBlock from './PaginationBlock.vue'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'

export default {
	name: 'Options',

	components: {
		NcActions,
		NcActionButton,
		SearchForm,
		NcButton,
		NcLoadingIcon,
		Plus,
		Check,
		Delete,
		TrayArrowDown,
		Refresh,
		ShareVariantOutline,
		OpenInNew,
		PaginationBlock,
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
		total: {
			type: Number,
			default: null,
		},
		allRows: {
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

	emits: [
		'create-row',
		'delete-selected-rows',
		'download-filtered-csv',
		'set-search-string',
	],
	data() {
		return {
			optionsDivWidth: null,
			rowsLoading: false,
			sharedLinkUrl: null,
			openLinkTimeout: null,
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
		isFiltered() {
			return (this.viewSetting?.filter?.length > 0) || !!this.viewSetting?.searchString
		},
	},

	mounted() {
		this.updateOptionsDivWidth()
		window.addEventListener('resize', this.updateOptionsDivWidth)
		subscribe('tables:rows-loading', this.setRowsLoading)
	},

	beforeUnmount() {
		window.removeEventListener('resize', this.updateOptionsDivWidth)
		unsubscribe('tables:rows-loading', this.setRowsLoading)
		this.clearOpenLink()
	},

	methods: {
		t,
		n,
		updateOptionsDivWidth() {
			this.optionsDivWidth = this.$el?.offsetWidth
		},
		exportFiltered() {
			this.$emit('download-filtered-csv', this.rows)
		},
		onReload() {
			emit('tables:reload')
		},
		setRowsLoading(loading) {
			this.rowsLoading = loading
		},
		exportSelected() {
			this.$emit('download-filtered-csv', this.getSelectedRows)
		},
		async shareSelected() {
			const rowIds = this.selectedRows.join(',')
			const base = (this.isView ? 'view' : 'table') + '/{elementId}?rowIds={rowIds}'
			const path = generateUrl('apps/tables/#/' + base, { elementId: this.elementId, rowIds })
			const url = getBaseUrl() + path

			try {
				await navigator.clipboard.writeText(url)
				showSuccess(t('tables', 'Link to selected rows copied to clipboard'))
				this.sharedLinkUrl = url
				this.openLinkTimeout = setTimeout(() => {
					this.sharedLinkUrl = null
					this.openLinkTimeout = null
				}, 5000)
			} catch (e) {
				console.error('Could not copy selected row link to clipboard', e)
				showError(t('tables', 'Could not copy link to clipboard'))
			}
		},
		openSharedLink() {
			if (this.sharedLinkUrl) {
				window.open(this.sharedLinkUrl, '_blank', 'noopener,noreferrer')
			}
		},
		clearOpenLink() {
			if (this.openLinkTimeout) {
				clearTimeout(this.openLinkTimeout)
				this.openLinkTimeout = null
				this.sharedLinkUrl = null
			}
		},
		getRowById(rowId) {
			const index = this.allRows.findIndex(row => row.id === rowId)
			return this.allRows[index]
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
	position: -webkit-sticky;
	/* Safari */
	position: sticky;
	top: 90px;
	inset-inline-start: 0;
}

.fix-col-4 {
	display: flex;
	justify-content: space-between;
	align-items: center;
	gap: 8px;
}

.selected-rows-placeholder {
	min-width: fit-content;
	flex-shrink: 0;
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
	flex-wrap: wrap;
	gap: 4px;
}

:deep(.actionButtonsLeft button) {
	min-width: fit-content;
}

.searchAndFilter {
	margin-inline-start: calc(var(--default-grid-baseline) * 3);
	width: auto;
	min-width: 100px;
	flex-shrink: 1;
}

@media only screen and (max-width: 641px) {
	.fix-col-4 {
		flex-direction: column;
		align-items: stretch;
	}

	.actionButtonsLeft {
		justify-content: center;
	}

	.selected-rows-option {
		justify-content: center;
		margin-top: 8px;
	}
}

@media only screen and (max-width: 480px) {
	.searchAndFilter {
		margin-inline-start: calc(var(--default-grid-baseline) * 1);
		min-width: 80px;
		order: -1;
		flex: 1 1 auto;
	}
}

</style>
