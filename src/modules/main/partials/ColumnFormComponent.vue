<template>
	<component :is="getFormComponent" :column="column"
		:value.sync="value_data" />
</template>

<script>
import TextLineForm from '../../../shared/components/ncTable/partials/rowTypePartials/TextLineForm.vue'
import TextLongForm from '../../../shared/components/ncTable/partials/rowTypePartials/TextLongForm.vue'
import TextLinkForm from '../../../shared/components/ncTable/partials/rowTypePartials/TextLinkForm.vue'
import NumberForm from '../../../shared/components/ncTable/partials/rowTypePartials/NumberForm.vue'
import NumberStarsForm from '../../../shared/components/ncTable/partials/rowTypePartials/NumberStarsForm.vue'
import NumberProgressForm from '../../../shared/components/ncTable/partials/rowTypePartials/NumberProgressForm.vue'
import SelectionCheckForm from '../../../shared/components/ncTable/partials/rowTypePartials/SelectionCheckForm.vue'
import SelectionForm from '../../../shared/components/ncTable/partials/rowTypePartials/SelectionForm.vue'
import SelectionMultiForm from '../../../shared/components/ncTable/partials/rowTypePartials/SelectionMultiForm.vue'
import DatetimeForm from '../../../shared/components/ncTable/partials/rowTypePartials/DatetimeForm.vue'
import DatetimeDateForm from '../../../shared/components/ncTable/partials/rowTypePartials/DatetimeDateForm.vue'
import DatetimeTimeForm from '../../../shared/components/ncTable/partials/rowTypePartials/DatetimeTimeForm.vue'
import TextRichForm from '../../../shared/components/ncTable/partials/rowTypePartials/TextRichForm.vue'

export default {
	name: 'ColumnFormComponent',
	components: {
		SelectionCheckForm,
		SelectionForm,
		SelectionMultiForm,
		TextLineForm,
		TextLongForm,
		TextLinkForm,
		TextRichForm,
		NumberForm,
		NumberStarsForm,
		NumberProgressForm,
		DatetimeForm,
		DatetimeDateForm,
		DatetimeTimeForm,
	},
	props: {
		column: {
			type: Object,
			default: null,
		},
		value: {
			type: undefined,
			default: null,
		},
	},
	data() {
		return {
			value_data: this.value,
		}
	},
	computed: {
		getFormComponent() {
			const columnType = this.column.type
			const columnSubType = this.column.subtype ?? ''
			const form = this.snakeToCamel(columnType) + this.snakeToCamel(columnSubType) + 'Form'

			if (this.$options.components && this.$options.components[form]) {
				return form
			} else {
				throw Error('Form ' + form + ' does not exist')
			}
		},
	},
	watch: {
		value_data(val) {
			this.$emit('update:value', this.value_data)
		},
		value() {
			this.value_data = this.value
		},
	},
	methods: {
		snakeToCamel(str) {
			str = str.toLowerCase().replace(/([-_][a-z])/g, group =>
				group
					.toUpperCase()
					.replace('_', '')
					.replace('-', ''),
			)
			return str.charAt(0).toUpperCase() + str.slice(1)
		},
	},
}
</script>
