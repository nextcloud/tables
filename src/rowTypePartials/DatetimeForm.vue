<template>
	<div class="row">
		<div class="fix-col-2" :class="{ mandatory: column.mandatory }">
			{{ column.title }}
		</div>
		<div class="fix-col-2" :class="{ 'margin-bottom': !column.description }">
			<DatetimePicker v-model="localValue" type="datetime" format="YYYY-MM-DD HH:mm" />
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
	name: 'DatetimeForm',
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
			default: null,
		},
	},
	computed: {
		localValue: {
			get() {
				if (this.value !== null) {
					return Moment(this.value, 'YYYY-MM-DD HH:mm').toDate()
				} else {
					return this.column.datetimeDefault === 'now' ? Moment().format('YYYY-MM-DD HH:mm') : ''
				}
			},
			set(v) {
				if (v) this.$emit('update:value', Moment(v).format('YYYY-MM-DD HH:mm'))
			},
		},
	},
}
</script>
<style scoped>

.mx-datepicker {
	width: 100%;
}

</style>
