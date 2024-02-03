<template>
	<RowFormWrapper :title="column.title" :mandatory="column.mandatory" :description="column.description" :width="2">
		<NcDateTimePicker v-model="localValue"
			type="date"
			format="YYYY-MM-DD"
			:clearable="false"
			:show-week-number="true" />
		<div v-if="canBeCleared" class="icon-close make-empty" @click="emptyValue" />
	</RowFormWrapper>
</template>

<script>
import { NcDateTimePicker } from '@nextcloud/vue'
import Moment from '@nextcloud/moment'
import RowFormWrapper from './RowFormWrapper.vue'

export default {
	components: {
		NcDateTimePicker,
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
			return !this.column.mandatory
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
	padding-left: 15px;
}

.make-empty:hover {
	cursor: pointer;
}

</style>
