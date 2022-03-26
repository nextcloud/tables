<template>
	<div class="row">
		<div class="fix-col-1" :class="{ mandatory: column.mandatory }">
			{{ column.title }}
		</div>
		<div class="fix-col-1" :class="{ 'margin-bottom': !column.description }">
			<CheckboxRadioSwitch type="switch" :checked.sync="localValue" />
		</div>
		<div class="fix-col-1 hide-s">
			&nbsp;
		</div>
		<div v-if="column.description" class="fix-col-1 p span margin-bottom">
			<div class="hint-padding-left">
				{{ column.description }}
			</div>
		</div>
		<div v-if="!column.description" class="fix-col-1 p span margin-bottom hide-s">
			&nbsp;
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
					const defaultValueBool = this.column.selectionDefault === 'true'
					this.$emit('update:value', '' + defaultValueBool)
					return defaultValueBool
				}
			},
			set(v) { this.$emit('update:value', '' + v) },
		},
	},
}
</script>
<style scoped>

.hint-padding-left {
	padding-left: 20px;
}

@media only screen and (max-width: 641px) {
	.hint-padding-left {
		padding-left: 0;
	}
}

</style>
