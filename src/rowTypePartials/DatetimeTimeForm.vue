<template>
	<div class="row">
		<div class="fix-col-3" :class="{ mandatory: column.mandatory }">
			{{ column.title }}
		</div>
		<div class="fix-col-1" :class="{ 'margin-bottom': !column.description }">
			<DatetimePicker v-model="localValue" type="time" format="HH:mm" />
		</div>
		<div v-if="column.description" class="fix-col-3">
&nbsp;
		</div>
		<div v-if="column.description" class="fix-col-1 p span margin-bottom">
			{{ column.description }}
		</div>
	</div>
</template>

<script>
import DatetimePicker from '@nextcloud/vue/dist/Components/DatetimePicker'
import Moment from '@nextcloud/moment'

export default {
	name: 'DatetimeTimeForm',
	components: {
		DatetimePicker,
	},
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
					return Moment(this.value, 'HH:mm:ss').toDate()
				} else {
					return this.column.datetimeDefault === 'now' ? Moment().toDate() : ''
				}
			},
			set(v) {
				// console.debug('date as moment', Moment(v).format('YYYY-MM-DD HH:mm:ss'))
				this.$emit('update:value', Moment(v).format('HH:mm:ss'))
			},
		},
	},
	created() {
		if (this.column.datetimeDefault === 'now') this.localValue = Moment().toDate()
	},
}
</script>
<style scoped>

.mx-datepicker {
	width: 100%;
}

</style>
