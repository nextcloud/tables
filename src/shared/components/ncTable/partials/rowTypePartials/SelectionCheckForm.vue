<template>
	<RowFormWrapper :title="column.title" :mandatory="column.mandatory" :description="column.description">
		<NcCheckboxRadioSwitch type="switch" :checked.sync="localValue" data-cy="selectionCheckFormSwitch" />
	</RowFormWrapper>
</template>

<script>
import { NcCheckboxRadioSwitch } from '@nextcloud/vue'
import RowFormWrapper from './RowFormWrapper.vue'

export default {
	components: {
		NcCheckboxRadioSwitch,
		RowFormWrapper,
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
