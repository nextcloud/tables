<template>
	<div class="element-description">
		<div class="mode-switch">
			<div class="buttons-wrap">
				<NcButton v-if="canManageElement(activeElement)" :type="mode === 'edit' ? 'secondary' : 'tertiary'" @click="() => mode='edit'">
					<template #icon>
						<IconPencil :size="20" />
					</template>
					<template #default>
						{{ t('tables','Edit') }}
					</template>
				</NcButton>
				<NcButton :type="mode === 'view' ? 'secondary':'tertiary'" @click="() => mode='view'">
					<template #icon>
						<IconEye :size="20" />
					</template>
					<template #default>
						{{ t('tables','View') }}
					</template>
				</NcButton>
				<NcButton :type="mode === 'hidden' ? 'secondary':'tertiary'" @click="() => mode='hidden'">
					<template #icon>
						<IconEye :size="20" />
					</template>
					<template #default>
						{{ t('tables','Hide') }}
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
import IconEye from 'vue-material-design-icons/Eye.vue'

export default {
	name: 'TableDescription',

	components: {
		NcButton,
		IconPencil,
		IconEye,
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
			if (this.descriptionLastEdited !== 0 || this.description === this.activeElement.description) return
			this.descriptionSaving = true
			// await this.$store.dispatch('updateTableProperty', { id: this.activeElement.id, data: { description: this.description }, property: 'description' })
			this.$emit('updatedesc', this.description)
			this.descriptionLastEdit = 0
			this.descriptionSaving = false
		},
		updateDescription() {
			this.descriptionLastEdit = Date.now()
			clearTimeout(this.descriptionSaveTimeout)
			this.descriptionSaveTimeout = setTimeout(async () => {
				await this.saveDescription()
			}, 1000)
		},
		async destroyEditor() {
			await this.saveDescription()
			this?.editor?.destroy()
		},
	},
}
</script>

<style lang="scss" scoped>

.mode-switch{
	width: 100%;
	display: flex;
	align-items: center;
	.buttons-wrap {
		display: flex;
		background: var(--color-main-background);
		border: 2px solid var(--color-border);
		border-radius: var(--border-radius-pill);
	}
}

.element-description {
	width: var(--text-editor-max-width);
}

</style>
