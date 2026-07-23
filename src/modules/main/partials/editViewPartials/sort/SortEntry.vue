<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="sort-entry">
		<div class="selection-fields">
			<NcSelect v-model="selectedColumn" class="select-field"
				:options="columns" :get-option-key="(option) => option.id"
				:aria-label-combobox="t('tables', 'Column')"
				:placeholder="t('tables', 'Column')" label="title" />
			<div class="mode-switch">
				<NcCheckboxRadioSwitch
					v-model="sortMode"
					:button-variant="true"
					value="ASC"
					type="radio"
					button-variant-grouped="horizontal"
					class="mode-checkbox">
					<div style="display: flex">
						<SortAsc :size="20" class="mode-icon" />
						{{ t('tables','Ascending') }}
					</div>
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch
					v-model="sortMode"
					:button-variant="true"
					value="DESC"
					type="radio"
					button-variant-grouped="horizontal"
					class="mode-checkbox">
					<div style="display: flex">
						<SortDesc :size="20" class="mode-icon" />
						{{ t('tables','Descending') }}
					</div>
				</NcCheckboxRadioSwitch>
			</div>
		</div>
		<NcButton
			:close-after-click="true"
			:aria-label="t('tables', 'Delete sorting rule')"
			type="tertiary"
			class="delete-button"
			@click="$emit('delete-sorting-rule')">
			<template #icon>
				<DeleteOutline :size="25" />
			</template>
		</NcButton>
	</div>
</template>

<script>
import { NcButton, NcSelect, NcCheckboxRadioSwitch } from '@nextcloud/vue'
import DeleteOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import SortAsc from 'vue-material-design-icons/SortAscending.vue'
import SortDesc from 'vue-material-design-icons/SortDescending.vue'

export default {
	name: 'SortEntry',
	components: {
		NcSelect,
		NcButton,
		DeleteOutline,
		NcCheckboxRadioSwitch,
		SortAsc,
		SortDesc,
	},
	props: {
		sortEntry: {
			type: Object,
			default: null,
		},
		columns: {
			type: Array,
			default: null,
		},
	},
	emits: [
		'delete-sorting-rule',
		'update:sort-entry',
	],
	computed: {
		selectedColumn: {
			get() {
				return (this.columns ?? []).find(col => col.id === this.sortEntry.columnId)
			},
			set(column) {
				this.$emit('update:sort-entry', {
					...this.sortEntry,
					columnId: column?.id ?? null,
				})
			},
		},
		sortMode: {
			get() {
				return this.sortEntry.mode
			},
			set(mode) {
				this.$emit('update:sort-entry', {
					...this.sortEntry,
					mode,
				})
			},
		},
	},
}
</script>

<style scoped>
.sort-entry {
	display: flex;
	justify-content: space-between;
}

.select-field {
	width: 50%;
	padding: 8px;
	min-width: auto !important;
}

.selection-fields {
	flex: 1;
	display: flex;
}

.mode-switch {
	display: flex;
	padding: calc(var(--default-grid-baseline) * 2);
	width: 40%
}

.mode-checkbox {
	width: 50%;
	height: 100%;
}

.mode-icon {
	padding-inline-end: calc(var(--default-grid-baseline) * 2);
}

:deep(.checkbox-radio-switch--button-variant .checkbox-radio-switch__label) {
	padding: 0px calc(var(--default-grid-baseline) * 2);
	display: flex;
	justify-content: center;
	min-height: auto;
}

:deep(.checkbox-radio-switch--button-variant.checkbox-radio-switch--checked) {
	border: 2px solid var(--color-border-dark);
}

</style>
