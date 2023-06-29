<template>
	<div class="value-wrapper">
		<NcRichContenteditable
			:user-data="getAllAutocompleteOptions"
			:auto-complete="autoComplete"
			:value.sync="localValue"
			:placeholder="t('tables', 'Value (type @ for magic values)')"
			class="value-field" />
	</div>
</template>

<script>
import { NcRichContenteditable } from '@nextcloud/vue'
import { MagicFields } from '../../../../../shared/components/ncTable/mixins/magicFields.js'

export default {
	name: 'FilterValueField',
	components: {
		NcRichContenteditable,
	},

	props: {
		searchString: {
			type: String,
			default: null,
		},
	},

	data() {
		return {
			localValue: this.searchString,
		}
	},

	computed: {
		getAllAutocompleteOptions() {
			return MagicFields
		},
		isOperatorChosen() {
			return this.localValue?.includes('@operator-')
		},
		isSearch() {
			return !this.isColumnChosen && !this.isOperatorChosen
		},
		// getPossibleMagicFields() {
		// 	return Object.values(this.magicFields) // .filter(item => item.goodFor.includes(this.getChosenColumnType))
		// },
	},
	methods: {
		autoComplete(search, callback) {
			console.debug('autocomplete search', search)
			callback(Object.values(MagicFields))
		},
	},

}
</script>
<style lang="scss" scoped>

.value-field {
	width: 100%;
	padding: 8px;
	min-width: auto !important;
	height: 44px;
}
.value-wrapper {
	width: 30%;
	height: 63px;
	display: flex;
	align-items: center;
}

</style>

<style>
.tribute-container {
	z-index: 10000 !important;
}
</style>
