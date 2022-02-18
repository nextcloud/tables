<template>
	<div class="row">
		<div class="fix-col-2" :class="{ mandatory: column.mandatory }">
			{{ column.title }}
		</div>
		<div class="fix-col-2" :class="{ 'margin-bottom': !column.description }">
			<CheckboxRadioSwitch type="switch" :checked.sync="localValue" />
		</div>
		<div v-if="column.description" class="fix-col-2">
&nbsp;
		</div>
		<div v-if="column.description" class="fix-col-2 p span margin-bottom">
			{{ column.description }}
		</div>
	</div>
</template>

<script>
import CheckboxRadioSwitch from '@nextcloud/vue/dist/Components/CheckboxRadioSwitch'

export default {
	name: 'SelectionCheckForm',
	components: {
		CheckboxRadioSwitch,
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
					return this.value === 'true'
				} else {
					return this.column.selectionDefault === 'true'
				}
			},
			set(v) { this.$emit('update:value', v) },
		},
	},
}
</script>
