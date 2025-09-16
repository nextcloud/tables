<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<RowFormWrapper :title="column.title" :mandatory="isMandatoryField" :description="column.description" :width="2">
		<NcDateTimePickerNative
			id="datetime-time-picker"
			v-model="localValue"
			:label="t('tables', 'Please select a new time')"
			:readonly="column.viewColumnInformation?.readonly"
			type="time" />
		<div v-if="canBeCleared" class="icon-close make-empty" @click="emptyValue" />
	</RowFormWrapper>
</template>

<script>
import { NcDateTimePickerNative } from '@nextcloud/vue'
import Moment from '@nextcloud/moment'
import RowFormWrapper from './RowFormWrapper.vue'
import rowHelper from '../../mixins/rowHelper'

export default {
	components: {
		NcDateTimePickerNative,
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
			default: null,
		},
	},
	data() {
		return {
		}
	},
	computed: {
		isMandatoryField() {
			return this.isMandatory(this.column)
		},
		canBeCleared() {
			return !this.column.viewColumnInformation?.readonly && !this.isMandatoryField
		},
		localValue: {
			get() {
				if (this.value !== null && this.value !== 'none') {
					return Moment(this.value, 'HH:mm').toDate()
				} else if (this.value === null && this.column.datetimeDefault === 'now') {
					const dt = Moment()
					this.$emit('update:value', dt.format('HH:mm'))
					return dt.toDate()
				} else {
					return new Date()
				}
			},
			set(v) {
				if (v === 'none') {
					this.$emit('update:value', v)
				} else if (v) {
					this.$emit('update:value', Moment(v).format('HH:mm'))
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

.hint-padding-left {
	padding-inline-start: 20px;
}

@media only screen and (max-width: 641px) {
	.hint-padding-left {
		padding-inline-start: 0;
	}
}

</style>
