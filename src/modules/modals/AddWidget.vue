<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog v-if="showModal"
		:name="editingWidget ? t('tables', 'Configure widget') : t('tables', 'Add widget')"
		size="normal"
		data-cy="addWidgetModal"
		@closing="$emit('close')">
		<div class="modal__content">
			<div class="row space-T">
				<NcSelect v-model="selectedType"
					:input-label="t('tables', 'Widget type')"
					:options="typeOptions"
					:clearable="false"
					:disabled="editingWidget !== null"
					label="label"
					data-cy="addWidgetType" />
			</div>

			<div class="row space-T">
				<NcTextField v-model="title" :label="t('tables', 'Widget title')" data-cy="addWidgetTitle" />
			</div>
			<div class="row">
				<NcCheckboxRadioSwitch v-model="showTitle" data-cy="addWidgetShowTitle">
					{{ t('tables', 'Show the title above the widget') }}
				</NcCheckboxRadioSwitch>
			</div>

			<template v-if="type === 'header'">
				<div class="row space-T">
					<NcTextField v-model="content.title" :label="t('tables', 'Heading')" data-cy="addWidgetHeading" />
				</div>
				<div class="row space-T">
					<NcTextField v-model="content.subtitle" :label="t('tables', 'Subheading')" />
				</div>
				<div class="row space-T">
					<NcSelect v-model="textAlign"
						:input-label="t('tables', 'Text alignment')"
						:options="alignOptions"
						:clearable="false"
						label="label" />
				</div>
				<div class="row space-T color-row">
					<label>
						{{ t('tables', 'Background color') }}
						<input v-model="content.backgroundColor" type="color" data-cy="addWidgetBackground">
					</label>
					<label>
						{{ t('tables', 'Text color') }}
						<input v-model="content.textColor" type="color">
					</label>
					<NcButton variant="tertiary" @click="content.backgroundColor = ''; content.textColor = ''">
						{{ t('tables', 'Use theme colors') }}
					</NcButton>
				</div>
			</template>

			<template v-if="type === 'text'">
				<div class="row space-T">
					<NcTextArea v-model="content.text"
						:label="t('tables', 'Text')"
						:placeholder="t('tables', 'Leave an empty line between paragraphs')"
						resize="vertical"
						rows="6"
						data-cy="addWidgetText" />
				</div>
			</template>

			<template v-if="type === 'data'">
				<div class="row space-T">
					<NcSelect v-model="dataTarget"
						:input-label="t('tables', 'Table or view')"
						:options="dataOptions"
						:clearable="false"
						:searchable="true"
						label="label"
						data-cy="addWidgetData" />
				</div>
			</template>

			<div class="row space-T">
				<div class="fix-col-4 end">
					<NcButton variant="primary" :disabled="!canSubmit" data-cy="addWidgetSubmit" @click="submit">
						{{ editingWidget ? t('tables', 'Save widget') : t('tables', 'Add widget') }}
					</NcButton>
				</div>
			</div>
		</div>
	</NcDialog>
</template>

<script>
import { NcButton, NcCheckboxRadioSwitch, NcDialog, NcSelect, NcTextArea, NcTextField } from '@nextcloud/vue'
import { mapState } from 'pinia'
import { useTablesStore } from '../../store/store.js'
import { getWidgetType, listWidgetTypes, WIDGET_TYPE_HEADER } from '../grid/widgetRegistry.js'

/**
 * Collects the type, title and content of a widget. The parent places it on the grid.
 */
export default {
	name: 'AddWidget',
	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		NcDialog,
		NcSelect,
		NcTextArea,
		NcTextField,
	},
	props: {
		showModal: {
			type: Boolean,
			default: false,
		},
		editingWidget: {
			type: Object,
			default: null,
		},
	},
	emits: ['close', 'submit'],
	data() {
		return {
			selectedType: null,
			title: '',
			showTitle: false,
			content: {},
		}
	},
	computed: {
		...mapState(useTablesStore, ['tables', 'views']),
		typeOptions() {
			return listWidgetTypes()
		},
		type() {
			return this.selectedType?.id ?? null
		},
		alignOptions() {
			return [
				{ id: 'left', label: t('tables', 'Left') },
				{ id: 'center', label: t('tables', 'Centered') },
				{ id: 'right', label: t('tables', 'Right') },
			]
		},
		textAlign: {
			get() {
				return this.alignOptions.find(option => option.id === this.content.textAlign) ?? this.alignOptions[0]
			},
			set(option) {
				this.content.textAlign = option?.id ?? 'left'
			},
		},
		dataOptions() {
			const tables = this.tables.map(table => ({
				id: 'table-' + table.id,
				targetType: 'table',
				targetId: table.id,
				label: (table.emoji ? table.emoji + ' ' : '') + table.title,
			}))
			const views = this.views
				.filter(view => view.type !== 'grid')
				.map(view => ({
					id: 'view-' + view.id,
					targetType: 'view',
					targetId: view.id,
					label: (view.emoji ? view.emoji + ' ' : '') + view.title + ' (' + t('tables', 'view') + ')',
				}))
			return [...tables, ...views]
		},
		dataTarget: {
			get() {
				return this.dataOptions.find(option => option.targetType === this.content.targetType && option.targetId === this.content.targetId) ?? null
			},
			set(option) {
				this.content.targetType = option?.targetType ?? 'table'
				this.content.targetId = option?.targetId ?? null
				if (option && this.title === '') {
					this.title = option.label
				}
			},
		},
		canSubmit() {
			if (!this.type) {
				return false
			}
			if (this.type === 'data') {
				return !!this.content.targetId
			}
			return true
		},
	},
	watch: {
		showModal(open) {
			if (open) {
				this.reset()
			}
		},
		selectedType(option, previous) {
			if (option && previous && option.id !== previous.id) {
				this.content = getWidgetType(option.id).defaultContent()
				this.showTitle = getWidgetType(option.id).showTitle
			}
		},
	},
	methods: {
		reset() {
			if (this.editingWidget) {
				this.selectedType = this.typeOptions.find(option => option.id === this.editingWidget.type) ?? this.typeOptions[0]
				this.title = this.editingWidget.title ?? ''
				this.showTitle = !!this.editingWidget.showTitle
				this.content = { ...getWidgetType(this.editingWidget.type)?.defaultContent(), ...JSON.parse(JSON.stringify(this.editingWidget.content ?? {})) }
				return
			}
			this.selectedType = this.typeOptions.find(option => option.id === WIDGET_TYPE_HEADER) ?? this.typeOptions[0]
			this.title = ''
			this.showTitle = getWidgetType(this.selectedType.id).showTitle
			this.content = getWidgetType(this.selectedType.id).defaultContent()
		},
		submit() {
			if (!this.canSubmit) {
				return
			}
			this.$emit('submit', {
				type: this.type,
				title: this.title.trim() || this.selectedType.label,
				showTitle: this.showTitle,
				content: JSON.parse(JSON.stringify(this.content)),
			})
		},
	},
}
</script>

<style lang="scss" scoped>
.modal__content {
	padding-inline-end: 0 !important;

	.row > :deep(.v-select),
	.row > :deep(.input-field),
	.row > :deep(.textarea) {
		width: 100%;
	}

	.color-row {
		display: flex;
		align-items: center;
		gap: calc(4 * var(--default-grid-baseline, 4px));

		label {
			display: inline-flex;
			align-items: center;
			gap: calc(2 * var(--default-grid-baseline, 4px));
		}
	}
}
</style>
