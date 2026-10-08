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

			<SchemaForm v-if="widgetType" v-model="content" :properties="widgetType.properties" />

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
import { NcButton, NcCheckboxRadioSwitch, NcDialog, NcSelect, NcTextField } from '@nextcloud/vue'
import { mapState } from 'pinia'
import SchemaForm from '../grid/SchemaForm.vue'
import { useTablesStore } from '../../store/store.js'
import { defaultConfiguration, defaultContent, findWidgetType } from '../grid/widgetRegistry.js'

/**
 * Collects the type, configuration and content of a widget; the content form comes
 * from the schema of the type. The parent places the widget on the grid.
 */
export default {
	name: 'AddWidget',
	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		NcDialog,
		NcSelect,
		NcTextField,
		SchemaForm,
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
		...mapState(useTablesStore, ['widgetTypes']),
		typeOptions() {
			return this.widgetTypes.map(widgetType => ({ id: widgetType.type, label: t('tables', widgetType.title) }))
		},
		widgetType() {
			return findWidgetType(this.widgetTypes, this.selectedType?.id)
		},
		canSubmit() {
			if (!this.widgetType) {
				return false
			}
			return Object.entries(this.widgetType.properties).every(([name, property]) => {
				if (!property.required) {
					return true
				}
				const value = this.content[name]
				return property.type === 'target' ? !!value?.id : String(value ?? '').trim() !== ''
			})
		},
	},
	watch: {
		showModal(open) {
			if (open) {
				this.reset()
			}
		},
		selectedType(option, previous) {
			if (option && previous && option.id !== previous.id && this.widgetType) {
				this.content = defaultContent(this.widgetType)
				this.showTitle = defaultConfiguration(this.widgetType).showTitle ?? false
			}
		},
	},
	methods: {
		reset() {
			if (this.editingWidget) {
				this.selectedType = this.typeOptions.find(option => option.id === this.editingWidget.type) ?? this.typeOptions[0]
				this.title = this.editingWidget.configuration?.title ?? ''
				this.showTitle = !!this.editingWidget.configuration?.showTitle
				this.content = { ...defaultContent(this.widgetType), ...JSON.parse(JSON.stringify(this.editingWidget.content ?? {})) }
				return
			}
			this.selectedType = this.typeOptions[0] ?? null
			this.title = ''
			this.showTitle = defaultConfiguration(this.widgetType).showTitle ?? false
			this.content = defaultContent(this.widgetType)
		},
		submit() {
			if (!this.canSubmit) {
				return
			}
			this.$emit('submit', {
				type: this.widgetType.type,
				configuration: { title: this.title.trim() || t('tables', this.widgetType.title), showTitle: this.showTitle },
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
	.row > :deep(.input-field) {
		width: 100%;
	}
}
</style>
