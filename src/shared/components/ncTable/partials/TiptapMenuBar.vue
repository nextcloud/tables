<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="tables-tiptap-wrapper">
		<div v-if="editor" class="menuBar">
			<NcButton type="tertiary-no-background" :aria-label="t('tables', 'Undo')" @click="editor.chain().focus().undo().run()">
				<template #icon>
					<Undo :size="20" />
				</template>
			</NcButton>
			<NcButton type="tertiary-no-background" :aria-label="t('tables', 'Redo')" @click="editor.chain().focus().redo().run()">
				<template #icon>
					<Redo :size="20" />
				</template>
			</NcButton>
			<NcButton :class="{ 'is-active': editor.isActive('bold') }" type="tertiary-no-background" :aria-label="t('tables', 'Bold')" @click="editor.chain().focus().toggleBold().run()">
				<template #icon>
					<FormatBold :size="20" />
				</template>
			</NcButton>
			<NcButton :class="{ 'is-active': editor.isActive('italic') }" type="tertiary-no-background" :aria-label="t('tables', 'Italic')" @click="editor.chain().focus().toggleItalic().run()">
				<template #icon>
					<FormatItalic :size="20" />
				</template>
			</NcButton>
			<NcButton :class="{ 'is-active': editor.isActive('bulletList') }" type="tertiary-no-background" :aria-label="t('tables', 'Bullet list')" @click="editor.chain().focus().toggleBulletList().run()">
				<template #icon>
					<FormatListBulletedSquare :size="20" />
				</template>
			</NcButton>
			<NcButton
				:class="{ 'is-active': editor.isActive('orderedList') }"
				type="tertiary-no-background"
				:aria-label="t('tables', 'Ordered list')"
				@click="editor.chain().focus().toggleOrderedList().run()">
				<template #icon>
					<FormatListNumbered :size="20" />
				</template>
			</NcButton>
			<NcButton
				:class="{ 'is-active': editor.isActive('strike') }"
				type="tertiary-no-background"
				:aria-label="t('tables', 'Strike')"
				@click="editor.chain().focus().toggleStrike().run()">
				<template #icon>
					<FormatStrikethrough :size="20" />
				</template>
			</NcButton>
			<NcButton
				type="tertiary-no-background"
				:class="{ 'is-active': editor.isActive('heading', { level: 1 }) }"
				:aria-label="t('tables', 'Heading 1')"
				@click="editor.chain().focus().toggleHeading({ level: 1 }).run()">
				<template #icon>
					<FormatHeader1 :size="20" />
				</template>
			</NcButton>
			<NcButton
				type="tertiary-no-background"
				:class="{ 'is-active': editor.isActive('heading', { level: 2 }) }"
				:aria-label="t('tables', 'Heading 2')"
				@click="editor.chain().focus().toggleHeading({ level: 2 }).run()">
				<template #icon>
					<FormatHeader2 :size="20" />
				</template>
			</NcButton>
			<NcButton
				type="tertiary-no-background"
				:class="{ 'is-active': editor.isActive('heading', { level: 3 }) }"
				:aria-label="t('tables', 'Heading 3')"
				@click="editor.chain().focus().toggleHeading({ level: 3 }).run()">
				<template #icon>
					<FormatHeader3 :size="20" />
				</template>
			</NcButton>
			<NcButton
				type="tertiary-no-background"
				:class="{ 'is-active': editor.isActive('code') }"
				:aria-label="t('tables', 'Code')"
				@click="editor.chain().focus().toggleCode().run()">
				<template #icon>
					<CodeTags :size="20" />
				</template>
			</NcButton>
			<NcButton
				type="tertiary-no-background"
				:class="{ 'is-active': editor.isActive('taskList') }"
				:aria-label="t('tables', 'Task list')"
				@click="editor.chain().focus().toggleTaskList().run()">
				<template #icon>
					<CheckboxMultipleMarkedOutline :size="20" />
				</template>
			</NcButton>
		</div>
		<EditorContent :editor="editor" />
		<div v-if="editor && textLengthLimit" class="character-count p span end">
			{{ editor.storage.characterCount.characters() }}/{{ textLengthLimit }}
		</div>
	</div>
</template>

<script>
import { Editor, EditorContent } from '@tiptap/vue-2'
import { CharacterCount } from '@tiptap/extension-character-count'
import { StarterKit } from '@tiptap/starter-kit'
import { TaskList } from '@tiptap/extension-task-list'
import { TaskItem } from '@tiptap/extension-task-item'
import { NcButton } from '@nextcloud/vue'
import Undo from 'vue-material-design-icons/Undo.vue'
import Redo from 'vue-material-design-icons/Redo.vue'
import FormatBold from 'vue-material-design-icons/FormatBold.vue'
import FormatItalic from 'vue-material-design-icons/FormatItalic.vue'
import FormatStrikethrough from 'vue-material-design-icons/FormatStrikethrough.vue'
import FormatListBulletedSquare from 'vue-material-design-icons/FormatListBulletedSquare.vue'
import FormatListNumbered from 'vue-material-design-icons/FormatListNumbered.vue'
import FormatHeader1 from 'vue-material-design-icons/FormatHeader1.vue'
import FormatHeader2 from 'vue-material-design-icons/FormatHeader2.vue'
import FormatHeader3 from 'vue-material-design-icons/FormatHeader3.vue'
import CodeTags from 'vue-material-design-icons/CodeTags.vue'
import CheckboxMultipleMarkedOutline from 'vue-material-design-icons/CheckboxMultipleMarkedOutline.vue'
import { translate as t } from '@nextcloud/l10n'

export default {
	components: {
		EditorContent,
		NcButton,
		Undo,
		Redo,
		FormatBold,
		FormatItalic,
		FormatStrikethrough,
		FormatListBulletedSquare,
		FormatListNumbered,
		FormatHeader1,
		FormatHeader2,
		FormatHeader3,
		CodeTags,
		CheckboxMultipleMarkedOutline,
	},

	props: {
		value: {
			type: String,
			default: '',
		},
		textLengthLimit: {
		      type: Number,
		      default: null,
		    },
	},

	data() {
		return {
			editor: null,
		}
	},

	watch: {
		value(value) {
			const isSame = this.editor.getHTML() === value
			if (isSame) {
				return
			}
			this.editor.commands.setContent(value, false)
		},
	},

	mounted() {
		this.editor = new Editor({
			extensions: [
				StarterKit,
				TaskList,
				TaskItem.configure({
					nested: true,
				}),
				CharacterCount.configure({
					limit: this.textLengthLimit,
				}),
			],
			onUpdate: () => {
				this.$emit('input', this.editor.getHTML())
			},
			content: this.value,
			editorProps: {
				attributes: {
					spellcheck: 'false',
				},
			},
		})
	},

	beforeUnmount() {
		this.editor.destroy()
	},

	methods: {
		t,
	},
}
</script>

<style lang="scss">

.character-count {
	display: flex;
	margin-inline-end: calc(var(--default-grid-baseline) * 3);
	padding: 0;
}

.menuBar {
	display: inline-flex;
	flex-wrap: wrap;
}

/* Basic editor styles */
.tables-tiptap-wrapper .ProseMirror, .tabulator-cell .ProseMirror {

	ul,
	ol {
		padding: 0 1rem;
	}

	h1,
	h2,
	h3,
	h4,
	h5,
	h6 {
		line-height: 1.1;
		font-size: unset;
		font-size: revert;
	}

	pre {
		background: #0D0D0D;
		color: #FFF;
		font-family: 'JetBrainsMono', monospace;
		padding: 0.75rem 1rem;
		border-radius: 0.5rem;
	}

	img {
		max-width: 100%;
		height: auto;
	}

	hr {
		margin: 1rem 0;
	}

	blockquote {
		padding-inline-start: 1rem;
		border-inline-start: 2px solid rgba(#0D0D0D, 0.1);
	}
}

.tables-tiptap-wrapper ul[data-type='taskList'], .tabulator-cell ul[data-type='taskList'] {
	list-style: none;
	padding: 0;
	p {
		margin: 0;
		padding-top: 7px;
	}
	li {
		display: flex;
		> label {
			flex: 0 0 auto;
			margin-inline-end: 0.5rem;
			user-select: none;
		}
		> div {
			flex: 1 1 auto;
		}
	}
}

.tables-tiptap-wrapper ol, .tabulator-cell ol {
	li {
		list-style-type: decimal;
		margin-inline-start: 15px;
	}
}

.tables-tiptap-wrapper ul, .tabulator-cell ul {
	li {
		list-style-type: disc;
		margin-inline-start: 15px;

		label input {
			max-height: 30px;
		}
	}
}
</style>
