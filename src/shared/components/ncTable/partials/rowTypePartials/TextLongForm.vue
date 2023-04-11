<template>
	<RowFormWrapper :title="column.title" :mandatory="column.mandatory" :description="column.description">
		<TiptapMenuBar
			:value.sync="localValue"
			:text-length-limit="getTextLimit"
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
		getTextLimit() {
			if (this.column.textMaxLength === -1) {
				return null
			} else {
				return this.column.textMaxLength
			}
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
