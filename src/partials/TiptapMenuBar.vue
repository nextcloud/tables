<template>
	<div class="tiptap-wrapper">
		<div v-if="editor" class="menuBar">
			<button class="icon-undo" @click="editor.chain().focus().undo().run()" />
			<button class="icon-redo" @click="editor.chain().focus().redo().run()" />
			<button :class="{ 'is-active': editor.isActive('bold') }" class="icon-bold" @click="editor.chain().focus().toggleBold().run()" />
			<button :class="{ 'is-active': editor.isActive('italic') }" class="icon-italic" @click="editor.chain().focus().toggleItalic().run()" />
			<button v-if="big"
				:class="{ 'is-active': editor.isActive('strike') }"
				class="icon-strike"
				@click="editor.chain().focus().toggleStrike().run()" />
			<button :class="{ 'is-active': editor.isActive('bulletList') }" class="icon-ul" @click="editor.chain().focus().toggleBulletList().run()" />
			<button v-if="big"
				:class="{ 'is-active': editor.isActive('orderedList') }"
				class="icon-ol"
				@click="editor.chain().focus().toggleOrderedList().run()" />
			<button :class="{ 'is-active': editor.isActive('heading', { level: 1 }) }" class="icon-h1" @click="editor.chain().focus().toggleHeading({ level: 1 }).run()" />
			<button :class="{ 'is-active': editor.isActive('heading', { level: 2 }) }" class="icon-h2" @click="editor.chain().focus().toggleHeading({ level: 2 }).run()" />
			<button v-if="big"
				:class="{ 'is-active': editor.isActive('heading', { level: 3 }) }"
				class="icon-h3"
				@click="editor.chain().focus().toggleHeading({ level: 3 }).run()" />
			<button v-if="big"
				:class="{ 'is-active': editor.isActive('code') }"
				class="icon-code"
				@click="editor.chain().focus().toggleCode().run()" />
			<button class="icon-checkbox-mark" :class="{ 'is-active': editor.isActive('taskList') }" @click="editor.chain().focus().toggleTaskList().run()" />
			<button :class="{ 'is-active': big }" class="icon-fullscreen" @click="big = !big" />
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

export default {
	components: {
		EditorContent,
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
