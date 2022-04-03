<template>
	<div class="row">
		<div class="fix-col-1" :class="{ mandatory: column.mandatory }">
			{{ column.title }}
		</div>
		<div class="fix-col-2" :class="{ 'space-B': !column.description }">
			<DatetimePicker v-model="localValue"
				type="datetime"
				format="YYYY-MM-DD HH:mm"
				:clearable="false"
				:show-week-number="true" />
			<div v-if="canBeCleared" class="icon-close make-empty" @click="emptyValue" />
		</div>
		<div class="fix-col-1 hide-s">
			&nbsp;
		</div>
		<div v-if="column.description" class="fix-col-1 p span space-B">
			<div class="hint-padding-left">
				{{ column.description }}
			</div>
		</div>
		<div v-if="!column.description" class="fix-col-1 p span space-B hide-s">
			&nbsp;
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
