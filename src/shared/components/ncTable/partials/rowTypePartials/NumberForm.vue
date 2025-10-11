<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<RowFormWrapper :width="2" :title="column.title" :mandatory="isMandatory(column)" :description="column.description">
		<div v-if="column.numberPrefix" class="prefix">
			{{ column.numberPrefix }}
		</div>
		<input v-model="localValue"
			type="number"
			:min="column.numberMin"
			:max="column.numberMax"
			:readonly="column.viewColumnInformation?.readonly"
			:step="getStep">
		<div v-if="column.numberSuffix" class="suffix">
			{{ column.numberSuffix }}
		</div>
	</RowFormWrapper>
</template>

<script>
import RowFormWrapper from './RowFormWrapper.vue'
import rowHelper from '../../../../components/ncTable/mixins/rowHelper.js'
export default {

	components: {
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
		getStep() {
			if (this.column?.numberDecimals === 0) {
				return '1'
			} else if (this.column?.numberDecimals > 0) {
				return '.' + '0'.repeat(this.column.numberDecimals - 1) + '1'
			} else {
				return 'any'
			}
		},
		localValue: {
			get() {
				if (this.value !== null) {
					return this.value
				} else {
					if (this.column.numberDefault !== undefined) {
						this.$emit('update:value', this.column.numberDefault)
						return this.column.numberDefault
					} else {
						return null
					}
				}
			},
			set(v) { this.$emit('update:value', this.parseValue(v)) },
		},
	},

	watch: {
		localValue() {
			const value = this.parseValue(this.localValue)
			this.localValue = value
			this.$emit('update:value', value)
		},
		value() {
			this.localValue = this.value
		},
	},

	mounted() {
		this.localValue = this.value
	},

	methods: {
		parseValue(inputValue) {
			if (inputValue === null || inputValue === '') {
				return null
			}
			let parsedValue
			if (typeof inputValue === 'string' || inputValue instanceof String) {
				parsedValue = parseFloat(inputValue.replace(',', '.'))
			} else {
				parsedValue = inputValue
			}
			const roundedValue = parsedValue.toFixed(this.column?.numberDecimals)
			let value = parseFloat(roundedValue)
			if ((this.column?.numberMin !== null && this.column?.numberMin !== undefined) && value < this.column?.numberMin) {
				value = this.column.numberMin
			}
			if ((this.column?.numberMax !== null && this.column?.numberMax !== undefined) && value > this.column?.numberMax) {
				value = this.column.numberMax
			}
			return value
		},
	},
}
</script>
<style lang="scss" scoped>

.prefix {
	padding-inline-end: calc(var(--default-grid-baseline) * 2);
}

.suffix {
	padding-inline-start: calc(var(--default-grid-baseline) * 2);
}

</style>
