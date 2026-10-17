<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="element-description">
		<!-- The Text app is not available (disabled, or its editor failed to
		     load): show the stored markdown instead of an empty block. -->
		<template v-if="!textEditorAvailable">
			<div v-if="description.trim().length > 0" class="description__fallback">
				<NcRichText :text="description"
					:use-markdown="true"
					:autolink="true"
					:reference-limit="0" />
			</div>
			<p v-if="!readOnly" class="description__unavailable">
				{{ t('tables', 'Editing the description requires the Text app.') }}
			</p>
		</template>
		<div v-else v-show="mode !== 'hidden' && (!readOnly || description.length > 0)" class="description__editor">
			<div id="description-editor" ref="textEditor" />
		</div>
	</div>
</template>

<script>

import { NcRichText } from '@nextcloud/vue'
import permissionsMixin from '../../../shared/components/ncTable/mixins/permissionsMixin.js'

// The editor is provided by the Text app. When that app is disabled
// `window.OCA.Text` does not exist.
function isTextEditorAvailable() {
	return typeof window.OCA?.Text?.createEditor === 'function'
}

export default {
	name: 'TableDescription',

	components: {
		NcRichText,
	},
	mixins: [permissionsMixin],
	props: {
		description: {
			type: String,
			default: '',
		},
		readOnly: {
			type: Boolean,
			default: false,
		},
	},

	emits: [
		'update:description',
	],
	data() {
		return {
			mode: 'view',
			textEditorAvailable: isTextEditorAvailable(),
		}
	},
	watch: {
		mode() {
			this.editor?.setReadOnly(this.mode === 'view')
		},
	},

	mounted() {
		if (this.textEditorAvailable && (!this.readOnly || this.description.length > 0)) {
			this.setupEditor()
		}
	},
	async beforeUnmount() {
		this.isUnmounted = true
		await this.destroyEditor()
	},
	methods: {
		async setupEditor() {
			if (!this.textEditorAvailable) {
				return
			}
			if (this?.editor) await this.destroyEditor()
			if (this.$refs.textEditor === undefined) {
				return
			}
			try {
				const editor = await window.OCA.Text.createEditor({
					el: this.$refs.textEditor,
					content: this.description,
					readOnly: this.readOnly,
					onUpdate: ({ markdown }) => {
						if (this.description === markdown) {
							this.descriptionLastEdit = 0
							return
						}
						this.$emit('update:description', markdown)
					},
				})
				if (this.isUnmounted) {
					// Unmounted while the editor was loading (e.g. a short
					// hover on a tab): do not leave it running.
					editor?.destroy()
					return
				}
				this.editor = editor
			} catch (error) {
				console.error('Could not load the Text editor, showing the plain description instead', error)
				this.textEditorAvailable = false
			}
		},
		async destroyEditor() {
			this?.editor?.destroy()
		},
	},
}
</script>

<style lang="scss" scoped>

.description__editor :deep(.tiptap.ProseMirror){
	padding-bottom: 0 !important;
}

.description__fallback {
	overflow-wrap: anywhere;
}

.description__unavailable {
	color: var(--color-text-maxcontrast);
}

.element-description {
	max-width: 100vw;
	width: var(--text-editor-max-width);
	padding-inline: min(60px,5vw);
}

:deep(.text-readonly-bar){
	display:none !important;
}

</style>
