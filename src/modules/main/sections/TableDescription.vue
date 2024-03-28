<template>
	<div class="element-description">
		<div class="mode-switch">
			<div class="buttons-wrap">
				<NcButton v-if="mode !== 'edit' && canManageElement(activeElement)" @click="() => mode='edit'">
					<template #icon>
						<IconPencil :size="15" />
					</template>
					<template #default>
						{{ t('tables','Edit') }}
					</template>
				</NcButton>
				<NcButton v-if="mode !== 'view'" :size="15" @click="() => mode='view'">
					<template #icon>
						<IconCheck :size="15" />
					</template>
					<template #default>
						{{ t('tables','Done') }}
					</template>
				</NcButton>
			</div>
		</div>
		<div v-show="mode !== 'hidden'" class="description__editor">
			<div id="description-editor" ref="textEditor" />
		</div>
	</div>
</template>

<script>

import { NcButton } from '@nextcloud/vue'
import permissionsMixin from '../../../shared/components/ncTable/mixins/permissionsMixin.js'
import IconPencil from 'vue-material-design-icons/Pencil.vue'
import IconCheck from 'vue-material-design-icons/Check.vue'

export default {
	name: 'TableDescription',

	components: {
		NcButton,
		IconPencil,
		IconCheck,
	},
	mixins: [permissionsMixin],
	props: {
		activeElement: {
			type: Object,
			default: null,
		},
	},

	data() {
		return {
			mode: 'view',
			description: '',
		}
	},
	watch: {
		mode() {
			this.editor.setReadOnly(this.mode === 'view')
		},
	},

	mounted() {
		this.setupEditor()
	},
	async beforeDestroy() {
		await this.destroyEditor()
	},
	methods: {
		async setupEditor() {
			if (this?.editor) await this.destroyEditor()
			this.descriptionLastEdited = 0
			this.description = this.activeElement.description
			if (this.$refs.textEditor === undefined) {
				return
			}
			this.editor = await window.OCA.Text.createEditor({
				el: this.$refs.textEditor,
				content: this.activeElement.description,
				readOnly: !this.canManageElement(this.activeElement),
				onUpdate: ({ markdown }) => {
					if (this.description === markdown) {
						this.descriptionLastEdit = 0
						return
					}
					this.description = markdown
					this.updateDescription()
				},
			})
			this.editor.setReadOnly(true)
		},
		async saveDescription() {
			if (this.descriptionLastEdited !== 0 || this.description === this.activeElement.description || !this.canManageElement(this.activeElement)) {
				return
			}
			this.descriptionSaving = true
			await this.$store.dispatch('updateTableProperty', { id: this.activeElement.id, data: { description: this.description }, property: 'description' })
			this.descriptionLastEdit = 0
			this.descriptionSaving = false
		},
		updateDescription() {
			this.descriptionLastEdit = Date.now()
			clearTimeout(this.descriptionSaveTimeout)
			this.descriptionSaveTimeout = setTimeout(this.saveDescription, 1000)
		},
		async destroyEditor() {
			await this.saveDescription()
			this?.editor?.destroy()
		},
	},
}
</script>

<style lang="scss" scoped>

.description__editor :deep(.tiptap.ProseMirror){
	padding-bottom: 0 !important;
}

.mode-switch{
	margin-left: 14px;
	width: 100%;
	display: flex;
	align-items: center;
	.buttons-wrap {
		display: flex;
		background: var(--color-main-background);
		border: 2px solid var(--color-border);
		border-radius: var(--border-radius-pill);
		z-index: 10022;
		:deep(.button-vue){
			max-height: 30px !important;
			min-height: unset !important;
		}
	}
}

.element-description {
	max-width: 100vw;
	width: var(--text-editor-max-width);
	padding-inline: min(60px,5vw)
}

:deep(.text-readonly-bar){
	display:none !important;
}

</style>
