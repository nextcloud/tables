<template>
	<RowFormWrapper :title="column.title" :mandatory="column.mandatory" :length="localValueTextOnly.length ? localValueTextOnly.length : 0" :max-length="column.textMaxLength" :description="column.description">
		<TiptapMenuBar v-if="!isMobileDevice"
			:value.sync="localValue"
			@input="updateText" />
		<textarea v-if="isMobileDevice" v-model="localValue" />
	</RowFormWrapper>
</template>

<script>
import TiptapMenuBar from '../TiptapMenuBar.vue'
import RowFormWrapper from './RowFormWrapper.vue'

export default {
	components: {
		TiptapMenuBar,
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
			showBigEditor: false,
		}
	},
	computed: {
		isMobileDevice() {
			// return (/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent))
			return false
		},
		localValue: {
			get() {
				return (this.value && true)
					? this.value
					: ((this.column.textDefault !== undefined)
						? this.column.textDefault
						: '')
			},
			set(v) {
				this.$emit('update:value', v)
			},
		},
		localValueTextOnly() {
			return this.localValue.replace(/(<([^>]+)>)/gi, '')
		},
		textLengthLimit() {
			return !!(this.column.textMaxLength && this.column.textMaxLength < this.localValueTextOnly.length)
		},
	},
	methods: {
		updateText(text) {
			this.localValue = text
		},
		setShowBigEditor(v) {
			this.showBigEditor = !!v
		},
	},
}
</script>
