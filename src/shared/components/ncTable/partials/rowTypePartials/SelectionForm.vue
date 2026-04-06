<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<RowFormWrapper :title="column.title" :mandatory="isMandatory(column)" :description="column.description">
		<NcSelect
			v-model="localValue"
			:options="selectableOptions"
			:selectable="option => !option?.deleted"
			:clearable="!column.mandatory"
			:disabled="column.viewColumnInformation?.readonly"
			:aria-label-combobox="t('tables', 'Options')" />
	</RowFormWrapper>
</template>

<script>
import { NcSelect } from '@nextcloud/vue'
import RowFormWrapper from './RowFormWrapper.vue'
import rowHelper from '../../../../components/ncTable/mixins/rowHelper.js'
export default {
	name: 'SelectionForm',
	components: {
		NcSelect,
		RowFormWrapper,
	},
	mixins: [rowHelper],
	props: {
		column: {
			type: Object,
			default: null,
		},
		value: {
			type: Number,
			default: null,
		},
	},
	computed: {
		localValue: {
			get() {
				if (this.value !== null) {
					return this.selectedOption
				} else {
					this.$emit('update:value', this.getDefaultId)
					return this.getDefaultOptionObject
				}
			},
			set(v) { this.$emit('update:value', v?.id) },
		},
		getOptions() {
			return this.column?.selectionOptions || []
		},
		getDefaultId() {
			return !isNaN(this.column.selectionDefault) ? parseInt(this.column.selectionDefault) : null
		},
		getDefaultOptionObject() {
			return this.column.getOptionObject(this.getDefaultId)
		},
		selectedOptionId() {
			const optionId = parseInt(this.value)
			return Number.isNaN(optionId) ? null : optionId
		},
		selectedOption() {
			if (this.selectedOptionId === null) {
				return null
			}
			return this.column.getOptionObject(this.selectedOptionId)
		},
		selectableOptions() {
			if (this.selectedOption?.deleted) {
				return [...this.getOptions, this.selectedOption]
			}
			return this.getOptions
		},
	},
}
</script>
<style scoped>

.hint-padding-left {
	padding-inline-start: 20px;
}

@media only screen and (max-width: 641px) {
	.hint-padding-left {
		padding-inline-start: 0;
	}
}

</style>
