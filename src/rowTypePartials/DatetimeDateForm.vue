<template>
	<div class="row">
		<div class="fix-col-2" :class="{ mandatory: column.mandatory }">
			{{ column.title }}
		</div>
		<div class="fix-col-2" :class="{ 'margin-bottom': !column.description }">
			<DatetimePicker v-model="localValue" type="date" format="YYYY-MM-DD" />
		</div>
		<div v-if="column.description" class="fix-col-2 hide-s">
&nbsp;
		</div>
		<div v-if="column.description" class="fix-col-2 p span margin-bottom">
			{{ column.description }}
		</div>
	</div>
</template>

<script>
import DatetimePicker from '@nextcloud/vue/dist/Components/DatetimePicker'
import Moment from '@nextcloud/moment'

export default {
	name: 'DatetimeDateForm',
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
					return Moment(this.value, 'YYYY-MM-DD HH:mm').toDate()
				} else {
					return this.column.datetimeDefault === 'today' ? Moment().toDate() : ''
				}
			},
			set(v) {
				this.$emit('update:value', Moment(v).format('YYYY-MM-DD HH:mm'))
			},
		},
	},
	created() {
		if (this.column.datetimeDefault === 'today') this.localValue = Moment().toDate()
	},
}
</script>
<style scoped>

.mx-datepicker {
	width: 100%;
}

</style>
