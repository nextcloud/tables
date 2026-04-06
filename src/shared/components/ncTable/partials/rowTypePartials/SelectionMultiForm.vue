<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<RowFormWrapper :title="column.title" :mandatory="isMandatory(column)" :description="column.description">
		<NcSelect
			v-model="localValues"
			:tag-width="80"
			:options="selectableOptions"
			:selectable="option => !option?.deleted"
			:clearable="!column.mandatory"
			:disabled="column.viewColumnInformation?.readonly"
			:multiple="true"
			:aria-label-combobox="t('tables', 'Options')" />
	</RowFormWrapper>
</template>

<script>
import { NcSelect } from '@nextcloud/vue'
import RowFormWrapper from './RowFormWrapper.vue'
import rowHelper from '../../../../components/ncTable/mixins/rowHelper.js'
export default {
	name: 'SelectionMultiForm',
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
			type: Array,
			default: null,
		},
	},
	computed: {
		localValues: {
			get() {
				if (this.value !== null) {
					return this.selectedOptionIds.map(id => this.column.getOptionObject(id))
				} else {
					this.$emit('update:value', this.column.default())
					return this.column.getDefaultObjects()
				}
			},
			set(v) {
				this.$emit('update:value', this.getIdArrayFromObjects(v))
			},
		},
		getOptions() {
			return this.column.selectionOptions || []
		},
		selectedOptionIds() {
			return (this.value || [])
				.map(item => parseInt(item))
				.filter(id => !Number.isNaN(id))
		},
		selectableOptions() {
			const missingOptions = this.selectedOptionIds
				.map(id => this.column.getOptionObject(id))
				.filter(opt => opt.deleted)
			return [...this.getOptions, ...missingOptions]
		},
	},
	methods: {
		getIdArrayFromObjects(objects) {
			const ids = []
			objects.forEach(o => {
				ids.push(o.id)
			})
			return ids
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

.multiselect {
	width: 100%;
}

</style>
