<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<RowFormWrapper :width="2" :title="column.title" :mandatory="isMandatory(column)" :description="column.description">
		<div v-if="column.numberPrefix" class="prefix">
			{{ column.numberPrefix }}
		</div>
		<div class="number-input">
			<input v-model="localValue"
				type="number"
				class="number-input__field"
				:class="{ 'number-input__field--error': hasRangeError }"
				:min="column.numberMin"
				:max="column.numberMax"
				:readonly="column.viewColumnInformation?.readonly"
				:step="getStep"
				:aria-invalid="hasRangeError"
				@blur="formatValue"
				@keyup.enter="formatValue">
			<p v-if="hasRangeError" class="number-input__hint" role="alert">
				{{ rangeHintText }}
			</p>
		</div>
		<div v-if="column.numberSuffix" class="suffix">
			{{ column.numberSuffix }}
		</div>
	</RowFormWrapper>
</template>

<script>
import RowFormWrapper from './RowFormWrapper.vue'
import { translate as t } from '@nextcloud/l10n'
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
		hasMin() {
			return this.column?.numberMin !== null && this.column?.numberMin !== undefined
		},
		hasMax() {
			return this.column?.numberMax !== null && this.column?.numberMax !== undefined
		},
		hasRangeError() {
			const value = this.parseValue(this.localValue)
			if (value === null) {
				return false
			}
			if (this.hasMin && value < this.column.numberMin) {
				return true
			}
			if (this.hasMax && value > this.column.numberMax) {
				return true
			}
			return false
		},
		rangeHintText() {
			const min = this.column?.numberMin
			const max = this.column?.numberMax
			if (this.hasMin && this.hasMax) {
				return t('tables', 'Enter a value between {min} and {max}.', { min, max })
			} else if (this.hasMin) {
				return t('tables', 'Enter a value of {min} or more.', { min })
			} else if (this.hasMax) {
				return t('tables', 'Enter a value of {max} or less.', { max })
			}
			return ''
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
			set(v) {
				this.$emit('update:value', v === '' ? null : v)
			},
		},
	},

	watch: {
		value() {
			this.localValue = this.value
		},
	},

	mounted() {
		this.localValue = this.value
	},

	methods: {
		t,
		// Normalise the input (decimal separator + rounding) without clamping.
		// Out-of-range values are surfaced via hasRangeError instead of being
		// silently replaced by the min/max value.
		formatValue() {
			const parsedValue = this.parseValue(this.localValue)
			this.localValue = parsedValue
			this.$emit('update:value', parsedValue)
		},
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
			if (isNaN(parsedValue)) {
				return null
			}
			const roundedValue = parsedValue.toFixed(this.column?.numberDecimals)
			return parseFloat(roundedValue)
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

.number-input {
	display: flex;
	flex-direction: column;
	flex: 1;
	min-width: 0;

	&__field {
		width: 100%;

		&--error {
			border-color: var(--color-error) !important;
		}
	}

	&__hint {
		margin-block-start: calc(var(--default-grid-baseline) * 1);
		color: var(--color-error);
		font-size: 0.9em;
	}
}

</style>
