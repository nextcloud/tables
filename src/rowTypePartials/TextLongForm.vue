<template>
	<div class="row">
		<div :class="{ mandatory: column.mandatory, 'fix-col-1': !showBigEditor, 'fix-col-4': showBigEditor }">
			<div class="row">
				<div class="fix-col-4">
					{{ column.title }}
				</div>
				<div v-if="column.textMaxLength !== -1" class="fix-col-4 p span" style="padding-bottom: 0; padding-top: 0;">
					{{ t('tables', 'length: {length}/{maxLength}', { length: localValue.length ? localValue.length : 0, maxLength: column.textMaxLength }) }}
				</div>
			</div>
		</div>
		<div class="margin-bottom" :class="{ 'fix-col-2': !showBigEditor, 'fix-col-4': showBigEditor }">
			<TiptapMenuBar :value.sync="localValue" @input="updateText" @big="setShowBigEditor" />
		</div>
		<div class="p span margin-bottom" :class="{ 'fix-col-1': !showBigEditor, 'fix-col-4': showBigEditor }">
			<div class="hint-padding-left">
				{{ column.description }}
			</div>
		</div>
	</div>
</template>

<script>
import TiptapMenuBar from '../partials/TiptapMenuBar'

export default {
	name: 'TextLongForm',
	components: {
		TiptapMenuBar,
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
		localValue: {
			get() {
				return (this.value && true)
					? this.value
					: ((this.column.textDefault !== undefined)
						? this.column.textDefault
						: '')
			},
			set(v) { this.$emit('update:value', v) },
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
<style scoped>

.hint-padding-left {
	padding-left: 20px;
	color: var(--color-text-lighter);
}

@media only screen and (max-width: 641px) {
	.hint-padding-left {
		padding-left: 0;
	}
}

</style>
