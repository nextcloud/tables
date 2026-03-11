<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
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
</template>

<script>
import PageLastIcon from 'vue-material-design-icons/PageLast.vue'
import PageFirstIcon from 'vue-material-design-icons/PageFirst.vue'
import ChevronRightIcon from 'vue-material-design-icons/ChevronRight.vue'
import ChevronLeftIcon from 'vue-material-design-icons/ChevronLeft.vue'
import { NcButton, NcSelect, useIsMobile } from '@nextcloud/vue'
import { mapState } from 'pinia'
import { translate as t } from '@nextcloud/l10n'
import { useTablesStore } from '../../../../store/store.js'

export default {
	name: 'PaginationBlock',

	components: {
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
	},

	setup() {
		return {
			isMobile: useIsMobile(),
		}
	},

	data() {
		return {
			pageNumber: 1,
			rowsPerPage: 100,
		}
	},

	computed: {
		...mapState(useTablesStore, ['appNavCollapsed']),
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
	},

	updated() {
		if (this.pageNumber > this.totalPages || this.totalPages === 1) {
			this.pageNumber = this.totalPages
		}
	},

	methods: {
		t,
	},
}
</script>

<style>
// fixme: move to scopped + deep
.vs__dropdown-menu {
	min-width: 95px !important;
}
</style>

<style lang="scss" scoped>
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
</style>
