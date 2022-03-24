<template>
	<div class="row">
		<div class="fix-col-1" :class="{ mandatory: column.mandatory }">
			<div class="row">
				<div class="fix-col-4">
					{{ column.title }}
				</div>
				<div v-if="column.textMaxLength !== -1" class="fix-col-4 p span" style="padding-bottom: 0; padding-top: 0;">
					{{ t('tables', 'length: {length}/{maxLength}', { length: localValue.length ? localValue.length : 0, maxLength: column.textMaxLength }) }}
				</div>
			</div>
		</div>
		<div class="fix-col-2 margin-bottom">
			<VueSimplemde ref="markdownEditor"
				v-model="localValue"
				:configs="configs" />
		</div>
		<div class="fix-col-1 p span margin-bottom">
			<div class="hint-padding-left">
				{{ column.description }}
			</div>
		</div>
	</div>
</template>

<script>
import VueSimplemde from 'vue-simplemde'

export default {
	name: 'TextLongForm',
	components: {
		VueSimplemde,
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
			editor: null,
			configs: {
				toolbar: ['bold', 'italic', 'strikethrough', 'heading', '|', 'quote', 'code', 'unordered-list', 'ordered-list', 'link', '|', 'preview', 'fullscreen'],
				autoDownloadFontAwesome: false,
				placeholder: t('tables', 'Some text'),
				spellChecker: false,
				status: false,
			},
		}
	},
	computed: {
		simplemde() {
			return this.$refs.markdownEditor.simplemde
		},
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
}
</script>
<style scoped>
@import '@fortawesome/fontawesome-free/css/all.min.css';
@import '~simplemde/dist/simplemde.min.css';

.editor {
	padding-left: 3em;
	padding-top: 3em;
}

.editor-toolbar a {
	color: var(--color-main-text) !important;
}

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
<style>
.editor-toolbar.fullscreen{
	z-index: 10005;
}

.vue-simplemde {
	width: 100%;
}

.CodeMirror, .CodeMirror-scroll {
	min-height: 200px;
}

.CodeMirror-code.div[contenteditable=true] {
	border: none;
}
</style>
