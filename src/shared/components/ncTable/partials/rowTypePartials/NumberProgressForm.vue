<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<RowFormWrapper :title="column.title" :mandatory="isMandatory(column)" :description="column.description" :width="2">
		<input v-model="localValue"
			type="number"
			min="0"
			max="100"
			:readonly="column.viewColumnInformation?.readonly"
			@input="enforceBounds">
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
	data() {
		return {
		}
	},
	computed: {
		localValue: {
			get() {
				if (this.value !== null) {
					return this.value
				} else {
					if (this.column.numberDefault !== undefined) {
						this.$emit('update:value', this.column.numberDefault)
						return this.column.numberDefault
					}
					return null
				}
			},
			set(v) {
				const parsedValue = parseInt(v)
				const clampedValue = Math.min(Math.max(0, v), 100)
				this.$emit('update:value', isNaN(parsedValue) ? null : parseInt(clampedValue))
			},
		},
	},
	methods: {
		enforceBounds(event) {
			const value = parseInt(event.target.value)
			if (isNaN(value)) {
				this.$emit('update:value', null)
				event.target.value = null
				return
			}
			const clampedValue = Math.min(Math.max(0, value), 100)
			this.$emit('update:value', clampedValue)
			event.target.value = clampedValue
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
