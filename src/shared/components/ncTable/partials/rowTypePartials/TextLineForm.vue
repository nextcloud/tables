<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<RowFormWrapper :title="column.title" :mandatory="column.viewColumnInformation?.mandatory ?? column.mandatory" :length="length" :max-length="column.textMaxLength" :description="column.description">
		<input v-model="localValue" :maxlength="column.textMaxLength" :readonly="column.viewColumnInformation?.readonly">
	</RowFormWrapper>
</template>

<script>
import RowFormWrapper from './RowFormWrapper.vue'

export default {
	components: {
		RowFormWrapper,
	},
	props: {
		column: {
			type: Object,
			default: null,
		},
		value: {
			type: String,
			default: null,
		},
	},
	data() {
		return {
		}
	},
	computed: {
		localValue: {
			get() {
				if (this.value === null) {
					const newValue = this.column?.textDefault ? this.column.textDefault : ''
					this.$emit('update:value', newValue)
					return newValue
				}
				return this.value
			},
			set(v) { this.$emit('update:value', v) },
		},
		length() {
			return (this.localValue) ? this.localValue.length : 0
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
