<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="element-description">
		<div v-show="mode !== 'hidden' && (!readOnly || description.length > 0)" class="description__editor">
			<div id="description-editor" ref="textEditor" />
		</div>
	</div>
</template>

<script>

import permissionsMixin from '../../../shared/components/ncTable/mixins/permissionsMixin.js'

export default {
	name: 'TableDescription',

	components: {
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

	data() {
		return {
			mode: 'view',
		}
	},
	watch: {
		mode() {
			this.editor.setReadOnly(this.mode === 'view')
		},
	},

	mounted() {
		if (!this.readOnly || this.description.length > 0) {
			this.setupEditor()
		}
	},
	async beforeDestroy() {
		await this.destroyEditor()
	},
	methods: {
		async setupEditor() {
			if (this?.editor) await this.destroyEditor()
			if (this.$refs.textEditor === undefined) {
				return
			}
			this.editor = await window.OCA.Text.createEditor({
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

.element-description {
	max-width: 100vw;
	width: var(--text-editor-max-width);
	padding-inline: min(60px,5vw);
}

:deep(.text-readonly-bar){
	display:none !important;
}

</style>
