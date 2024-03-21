<template>
	<div class="element-description">
		<div class="row first-row">
			<h1>
				{{ activeElement.emoji }}&nbsp;{{ activeElement.title }}
			</h1>
			<div class="info">
				<div>
					<TextIcon :size="15" />
					{{ t('tables', 'Filtered view') }}&nbsp;&nbsp;
				</div>
				<NcSmallButton
					@click="resetLocalAdjustments">
					<template #icon>
						<FilterRemove :size="15" />
					</template>
					{{ t('tables', 'Reset local adjustments') }}
				</NcSmallButton>
			</div>
			<div class="user-bubble">
				<NcUserBubble
					:display-name="activeElement.ownerDisplayName"
					:show-user-status="false"
					:user="activeElement.ownership" />
			</div>
		</div>
		<div class="description">
			<div class="mode-switch">
				<div class="buttons-wrap">
					<NcButton v-if="canManageElement(table)" :type="mode === 'edit' ? 'secondary' : 'tertiary'" @click="() => mode='edit'">
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
				</div>
			</div>
			<div class="description__editor">
				<div id="description-editor" ref="textEditor" />
			</div>
		</div>
	</div>
</template>

<script>

import { NcButton, NcUserBubble } from '@nextcloud/vue'
import permissionsMixin from '../../../shared/components/ncTable/mixins/permissionsMixin.js'
import TextIcon from 'vue-material-design-icons/Text.vue'
import FilterRemove from 'vue-material-design-icons/FilterRemove.vue'
import NcSmallButton from '../../../shared/components/ncSmallButton/NcSmallButton.vue'
import IconPencil from 'vue-material-design-icons/Pencil.vue'
import IconEye from 'vue-material-design-icons/Eye.vue'

export default {
	name: 'ElementDescription',

	components: {
		NcUserBubble,
		TextIcon,
		FilterRemove,
		NcSmallButton,
		NcButton,
		IconPencil,
		IconEye,
	},
	mixins: [permissionsMixin],
	props: {
		viewSetting: {
			type: Object,
			default: null,
		},
		activeElement: {
			type: Object,
			default: null,
		},
		isTable: {
			type: Boolean,
			default: false,
		},
		table: {
			type: Object,
			default: null,
			required: true,
		},
	},

	data() {
		return {
			mode: 'view',
			descriptionLocal: '',
		}
	},
	computed: {
		isFiltered() {
			return this.activeElement.filter && this.activeElement.filter[0]?.length > 0
		},

		isViewSettingSet() {
			return !(!this.viewSetting || ((!this.viewSetting.hiddenColumns || this.viewSetting.hiddenColumns.length === 0) && (!this.viewSetting.sorting) && (!this.viewSetting.filter || this.viewSetting.filter.length === 0)))
		},
	},
	watch: {
		mode() {
			this.setupEditor()
		},
	},

	mounted() {
		alert(this.table.id)
		this.setupEditor()
	},
	async beforeDestroy() {
		await this.destroyEditor()
	},
	methods: {
		resetLocalAdjustments() {
			this.$emit('update:viewSetting', {})
		},
		async setupEditor() {
			if (this?.editor) await this.destroyEditor()
			this.descriptionLastEdited = 0
			this.descriptionLocal = this.table.description
			if (this.$refs.textEditor === undefined) {
				return
			}
			this.editor = await window.OCA.Text.createEditor({
				el: this.$refs.textEditor,
				content: this.table.description,
				readOnly: this.mode === 'view',
				onUpdate: ({ markdown }) => {
					if (this.descriptionLocal === markdown) {
						this.descriptionLastEdit = 0
						return
					}
					this.descriptionLocal = markdown
					this.updateDescription()
				},
			})
		},
		async saveDescription() {
			if (this.descriptionLastEdited !== 0) return
			this.descriptionSaving = true
			await this.$store.dispatch('updateTableProperty', { id: this.table.id, data: { description: this.descriptionLocal }, property: 'description' })
			this.descriptionLastEdit = 0
			this.descriptionSaving = false
		},
		updateDescription() {
			this.descriptionLastEdit = Date.now()
			clearTimeout(this.descriptionSaveTimeout)
			this.descriptionSaveTimeout = setTimeout(async () => {
				await this.saveDescription()
			}, 2500)
		},
		reloadEditor() {
			this?.editor?.destroy()
			this.setupEditor()
		},
		async destroyEditor() {
			await this.saveDescription()
			this?.editor?.destroy()
		},
	},
}
</script>

<style lang="scss" scoped>
.text-readonly-bar {
	background-color: red;
}
.description{
	width: var(--text-editor-max-width);
	margin-inline: auto;
}
.mode-switch{
	width: 100%;
	display: flex;
	align-items: center;
	align-self: flex-end;
	justify-content: flex-end;
	.buttons-wrap {
		display: flex;
		background: var(--color-main-background);
		border: 2px solid var(--color-border);
		border-radius: var(--border-radius-pill);
	}
}

.light {
	opacity: .3;
}

.first-row:hover .light {
	opacity: 1;
}

.row.first-row {
	width: var(--app-content-width, auto);
	position: sticky;
	left: 0;
	top: 0;
	z-index: 15;
	background-color: var(--color-main-background-translucent);
	align-items: center;
}

.user-bubble {
	padding-left: calc(var(--default-grid-baseline) * 2);
}

.info {
	display: inline-flex;
	margin-left: calc(var(--default-grid-baseline) * 2);
	align-items: center;
	color: var(--color-text-maxcontrast);
}

.info > div {
	display: inline-flex;
	width: max-content;
}

.info span {
	padding: calc(var(--default-grid-baseline) * 1);
}

</style>
