<template>
	<div class="tiptap-wrapper">
		<div v-if="editor" class="menuBar">
			<Button type="tertiary-no-background" @click="editor.chain().focus().undo().run()">
				<template #icon>
					<Undo :size="20" />
				</template>
			</Button>
			<Button type="tertiary-no-background" @click="editor.chain().focus().redo().run()">
				<template #icon>
					<Redo :size="20" />
				</template>
			</Button>
			<Button :class="{ 'is-active': editor.isActive('bold') }" type="tertiary-no-background" @click="editor.chain().focus().toggleBold().run()">
				<template #icon>
					<FormatBold :size="20" />
				</template>
			</Button>
			<Button :class="{ 'is-active': editor.isActive('italic') }" type="tertiary-no-background" @click="editor.chain().focus().toggleItalic().run()">
				<template #icon>
					<FormatItalic :size="20" />
				</template>
			</Button>
			<Button :class="{ 'is-active': editor.isActive('bulletList') }" type="tertiary-no-background" @click="editor.chain().focus().toggleBulletList().run()">
				<template #icon>
					<FormatListBulletedSquare :size="20" />
				</template>
			</Button>
			<Button v-if="big"
				:class="{ 'is-active': editor.isActive('orderedList') }"
				type="tertiary-no-background"
				@click="editor.chain().focus().toggleOrderedList().run()">
				<template #icon>
					<FormatListNumbered :size="20" />
				</template>
			</Button>
			<Button v-if="big"
				:class="{ 'is-active': editor.isActive('strike') }"
				type="tertiary-no-background"
				@click="editor.chain().focus().toggleStrike().run()">
				<template #icon>
					<FormatStrikethrough :size="20" />
				</template>
			</Button>
			<Button v-if="big"
				type="tertiary-no-background"
				:class="{ 'is-active': editor.isActive('heading', { level: 1 }) }"
				@click="editor.chain().focus().toggleHeading({ level: 1 }).run()">
				<template #icon>
					<FormatHeader1 :size="20" />
				</template>
			</Button>
			<Button v-if="big"
				type="tertiary-no-background"
				:class="{ 'is-active': editor.isActive('heading', { level: 2 }) }"
				@click="editor.chain().focus().toggleHeading({ level: 2 }).run()">
				<template #icon>
					<FormatHeader2 :size="20" />
				</template>
			</Button>
			<Button v-if="big"
				type="tertiary-no-background"
				:class="{ 'is-active': editor.isActive('heading', { level: 3 }) }"
				@click="editor.chain().focus().toggleHeading({ level: 3 }).run()">
				<template #icon>
					<FormatHeader3 :size="20" />
				</template>
			</Button>
			<Button v-if="big"
				type="tertiary-no-background"
				:class="{ 'is-active': editor.isActive('code') }"
				@click="editor.chain().focus().toggleCode().run()">
				<template #icon>
					<CodeTags :size="20" />
				</template>
			</Button>
			<Button v-if="big"
				type="tertiary-no-background"
				:class="{ 'is-active': editor.isActive('taskList') }"
				@click="editor.chain().focus().toggleTaskList().run()">
				<template #icon>
					<CheckboxMultipleMarkedOutline :size="20" />
				</template>
			</Button>
			<Button type="tertiary-no-background" :class="{ 'is-active': big }" @click="big = !big">
				<template #icon>
					<Fullscreen :size="20" />
				</template>
			</Button>
		</div>
		<EditorContent :editor="editor" />
	</div>
</template>

<script>
import { Editor, EditorContent } from '@tiptap/vue-2'
import StarterKit from '@tiptap/starter-kit'
// eslint-disable-next-line import/no-named-as-default
import TaskList from '@tiptap/extension-task-list'
// eslint-disable-next-line import/no-named-as-default
import TaskItem from '@tiptap/extension-task-item'
import Button from '@nextcloud/vue/dist/Components/Button'
import Undo from 'vue-material-design-icons/Undo'
import Redo from 'vue-material-design-icons/Redo'
import FormatBold from 'vue-material-design-icons/FormatBold'
import FormatItalic from 'vue-material-design-icons/FormatItalic'
import FormatStrikethrough from 'vue-material-design-icons/FormatStrikethrough'
import FormatListBulletedSquare from 'vue-material-design-icons/FormatListBulletedSquare'
import FormatListNumbered from 'vue-material-design-icons/FormatListNumbered'
import FormatHeader1 from 'vue-material-design-icons/FormatHeader1'
import FormatHeader2 from 'vue-material-design-icons/FormatHeader2'
import FormatHeader3 from 'vue-material-design-icons/FormatHeader3'
import CodeTags from 'vue-material-design-icons/CodeTags'
import CheckboxMultipleMarkedOutline from 'vue-material-design-icons/CheckboxMultipleMarkedOutline'
import Fullscreen from 'vue-material-design-icons/Fullscreen'

export default {
	components: {
		EditorContent,
		Button,
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
		Fullscreen,
	},

	props: {
		value: {
			type: String,
			default: '',
		},
	},

	data() {
		return {
			editor: null,
			big: false,
		}
	},

	watch: {
		big() {
			this.$emit('big', this.big)
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
}
</script>

<style lang="scss">
.menuBar {
	display: inline-flex;
}

/* Basic editor styles */
.tiptap-wrapper .ProseMirror, .tabulator-cell .ProseMirror {
	> * + * {
		/*margin-top: 0.75em;*/
	}

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
		padding-left: 1rem;
		border-left: 2px solid rgba(#0D0D0D, 0.1);
	}
}

.tiptap-wrapper ul[data-type='taskList'], .tabulator-cell ul[data-type='taskList'] {
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
			margin-right: 0.5rem;
			user-select: none;
		}
		> div {
			flex: 1 1 auto;
		}
	}
}

.tiptap-wrapper ul, .tabulator-cell ul {
	li {
		list-style-type: disc;
		margin-left: 15px;

		label input {
			max-height: 30px;
		}
	}
}
</style>
