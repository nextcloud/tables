<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="pagination-block">
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
			<div class="page-number page-input">
				<span class="page-label">{{ t('tables', 'Page') }}</span>
				<NcTextField
					v-model.number="pageNumber"
					type="number"
					:aria-label="t('tables', 'Page number')"
					:min="1"
					:max="totalPages" />
				<span class="page-of">/ {{ totalPages }}</span>
			</div>
			<div class="page-number">
				<NcSelect v-model="rowsPerPage"
					:options="[25, 50, 75, 100, 125, 150, 175, 200]"
					:clearable="false"
					:aria-label-combobox="t('tables', 'Per page')" />
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
import { NcButton, NcSelect, NcTextField } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'
import { emit } from '@nextcloud/event-bus'

export default {
	name: 'PaginationBlock',

	components: {
		NcButton,
		PageLastIcon,
		PageFirstIcon,
		ChevronLeftIcon,
		ChevronRightIcon,
		NcSelect,
		NcTextField,
	},

	props: {
		rows: {
			type: Array,
			default: () => [],
		},
	},

	data() {
		return {
			pageNumber: 1,
			rowsPerPage: 100,
		}
	},

	computed: {
		totalPages() {
			return Math.ceil(this.rows.length / this.rowsPerPage)
		},
	},

	watch: {
		pageNumber() {
			this.validatePageInput()
			emit('tables:pagination-changed', { pageNumber: this.pageNumber, rowsPerPage: this.rowsPerPage })
		},
		rowsPerPage() {
			this.validatePageInput()
			emit('tables:pagination-changed', { pageNumber: this.pageNumber, rowsPerPage: this.rowsPerPage })
		},
		rows() {
			this.validatePageInput()
		},
	},

	methods: {
		t,
		validatePageInput() {
			// Ensure page number is within valid range
			if (this.pageNumber < 1) {
				this.pageNumber = 1
			} else if (this.pageNumber > this.totalPages) {
				this.pageNumber = this.totalPages
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.selected-page{
	padding-inline-start: 5px;
	display: inline-flex;
	align-items: center;
}

.page-of {
	font-size: 14px;
	color: var(--color-text-maxcontrast);
	white-space: nowrap;
}

.page-input {
	padding-inline: 5px;
	box-sizing: border-box;
	height: 36px;
	display: flex;
	align-items: center;
	gap: 4px;

	.page-label {
		font-size: 14px;
		color: var(--color-text-maxcontrast);
		white-space: nowrap;
	}

	:deep(.input-field) {
		width: 50px;
		margin: 0;
	}

	:deep(.input-field__main-wrapper) {
		height: 34px;
	}

	:deep(.input-field__input) {
		height: 32px;
		min-height: 32px;
		padding: 0 4px;
		text-align: center;
	}
}

.page-number{
	padding-inline: 5px;
	display: flex;
	align-items: center;
	box-sizing: border-box;
	height: 36px;
}

.pagination-items{
	background-color: var(--color-main-background);
	border-radius: var(--border-radius-large);
	display: flex;
	align-items: center;
	flex-shrink: 0;
	flex-wrap: wrap;
	justify-content: center;
	margin-top: 8px;
	padding: 4px;
	gap: 4px;

	:deep(.button-vue) {
		height: 36px;
		min-height: 36px;
		width: 36px;
		min-width: 36px;
	}
}

.pagination-block{
	display: flex;
	justify-content: center;
	align-items: center;
	max-width: 100%;

	:deep(.v-select) {
		min-width: 120px !important;
	}
}

@media screen and (max-width: 480px) {
	.pagination-items {
		gap: 1px;
		padding: 2px;
	}

	.page-number {
		padding-inline: 2px;
	}

	.page-input :deep(.input-field) {
		width: 40px;
	}
}
</style>
