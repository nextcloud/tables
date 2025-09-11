<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<RowFormWrapper :title="column.title" :mandatory="column.viewColumnInformation?.mandatory ?? column.mandatory" :description="column.description" :width="2">
		<NcDateTimePickerNative id="datetime-date-picker" v-model="localValue" :readonly="column.viewColumnInformation?.readonly"
			type="date" />
		<div v-if="canBeCleared" class="icon-close make-empty" @click="emptyValue" />
	</RowFormWrapper>
</template>

<script>
import { NcDateTimePickerNative } from '@nextcloud/vue'
import Moment from '@nextcloud/moment'
import RowFormWrapper from './RowFormWrapper.vue'

export default {
	components: {
		NcDateTimePickerNative,
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
		canBeCleared() {
			return !this.column.viewColumnInformation?.readonly && !(this.column.viewColumnInformation?.mandatory ?? this.column.mandatory)
		},
		localValue: {
			get() {
				if (this.value !== null && this.value !== 'none') {
					return Moment(this.value, 'YYYY-MM-DD').toDate()
				} else if (this.value === null && this.column.datetimeDefault === 'today') {
					const dt = Moment()
					this.$emit('update:value', dt.format('YYYY-MM-DD'))
					return dt.toDate()
				} else {
					return null
				}
			},
			set(v) {
				if (v === 'none') {
					this.$emit('update:value', v)
				} else if (v) {
					this.$emit('update:value', Moment(v).format('YYYY-MM-DD'))
				}
			},
		},
	},
	methods: {
		emptyValue() {
			this.localValue = 'none'
		},
	},
}
</script>
<style scoped>

.mx-datepicker {
	width: 100%;
}

.make-empty {
	padding-inline-start: 15px;
}

.make-empty:hover {
	cursor: pointer;
}

</style>
