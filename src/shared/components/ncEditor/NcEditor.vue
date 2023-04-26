<template>
	<div class="editor-wrapper" :class="{ border: showBorder, 'hide-readonly-bar': !showReadonlyBar, 'height-small': height === 'small' }">
		<div v-if="textAppAvailable">
			<div ref="editor" />
		</div>
		<div v-else>
			<NcEmptyContent
				:title="t('tables', 'Error')"
				:description="t('tables', 'Could not load editor, text not available.')">
				<template #icon>
					<Alert :size="20" />
				</template>
			</NcEmptyContent>
		</div>
	</div>
</template>

<script>
import { NcEmptyContent } from '@nextcloud/vue'
import Alert from 'vue-material-design-icons/Alert.vue'

export default {

	components: {
		NcEmptyContent,
		Alert,
	},

	props: {
		canEdit: {
		      type: Boolean,
		      default: true,
		    },
		text: {
		      type: String,
		      default: '',
		    },
		showBorder: {
		      type: Boolean,
		      default: true,
		    },
		showReadonlyBar: {
		      type: Boolean,
		      default: true,
		    },
		height: {
		      type: String,
		      default: null, // null, 'small'
		    },
	},

	data() {
		return {
			textAppAvailable: !!window.OCA?.Text?.createEditor,
			editor: null,
			localValue: '',
		}
	},

	computed: {
		localText: {
			get() {
				return this.localValue
			},
			set(v) {
				this.localValue = v
				this.$emit('update:text', v)
			},
		},
	},

	watch: {
		text(value) {
			if (value.trim() !== this.localValue.trim()) {
				this.editor.setContent(value, false)
			}
		},
	},

	async mounted() {
		await this.setupEditor()
		this.localValue = this.text
		this.editor.setContent(this.localValue, false)
	},

	beforeDestroy() {
		this?.editor?.destroy()
	},

	methods: {
		async setupEditor() {
			this?.editor?.destroy()
			if (this.textAppAvailable) {
				this.editor = await window.OCA.Text.createEditor({
					el: this.$refs.editor,
					content: this.localText,
					readOnly: !this.canEdit,
					onUpdate: ({ markdown }) => {
						this.localText = markdown
					},
				})
			} else {
				console.debug('try to load editor, but not initialized')
			}
		},
	},
}
</script>
<style scoped>

	:deep(.text-editor__wrapper button.entry-action__image-upload) {
		display: none;
	}

	.editor-wrapper {
		width: 100%;
		min-width: 200px;
	}

	.editor-wrapper.border {
		border: 2px solid var(--color-border-maxcontrast);
		border-radius: var(--border-radius-large);
	}

	:deep(.text-readonly-bar) {
		display: none !important;
	}

	.height-small {
		max-height: 320px;
		overflow-y: auto;
	}

</style>
