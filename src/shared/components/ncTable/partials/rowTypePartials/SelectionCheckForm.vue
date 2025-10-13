<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<RowFormWrapper :title="column.title" :mandatory="isMandatory(column)" :description="column.description">
		<NcCheckboxRadioSwitch
			type="switch"
			:checked.sync="localValue"
			:disabled="column.viewColumnInformation?.readonly"
			data-cy="selectionCheckFormSwitch" />
	</RowFormWrapper>
</template>

<script>
import { NcCheckboxRadioSwitch } from '@nextcloud/vue'
import RowFormWrapper from './RowFormWrapper.vue'
import rowHelper from '../../../../components/ncTable/mixins/rowHelper.js'

export default {
	components: {
		NcCheckboxRadioSwitch,
		RowFormWrapper,
	},
	mixins: [rowHelper],
	props: {
		column: {
			type: Object,
			default: null,
		},
		value: {
			type: String,
			default: '',
		},
	},
	data() {
		return {
		}
	},
	computed: {
		localValue: {
			get() {
				if (this.value) {
					return this.value === 'true'
				} else {
					const defaultValueBool = this.column.selectionDefault === 'true'
					this.$emit('update:value', '' + defaultValueBool)
					return defaultValueBool
				}
			},
			set(v) { this.$emit('update:value', '' + v) },
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
