<template>
	<RowFormWrapper :title="column.title" :mandatory="column.mandatory" :description="column.description" :width="2">
		<NcDateTimePickerNative id="datetime-picker" v-model="localValue"
			type="datetime-local" />
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
	computed: {
		canBeCleared() {
			return !this.column.mandatory
		},
		localValue: {
			get() {
				if (this.value !== null && this.value !== 'none') {
					return Moment(this.value, 'YYYY-MM-DD HH:mm').toDate()
				} else if (this.value === null && this.column.datetimeDefault === 'now') {
					const dt = Moment()
					this.$emit('update:value', dt.format('YYYY-MM-DD HH:mm'))
					return dt.toDate()
				} else {
					return null
				}
			},
			set(v) {
				if (v === 'none') {
					this.$emit('update:value', v)
				} else if (v) {
					this.$emit('update:value', Moment(v).format('YYYY-MM-DD HH:mm'))
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
	padding-left: 15px;
}

.make-empty:hover {
	cursor: pointer;
}

.hint-padding-left {
	padding-left: 20px;
}

@media only screen and (max-width: 641px) {
	.hint-padding-left {
		padding-left: 0;
	}
}

</style>
