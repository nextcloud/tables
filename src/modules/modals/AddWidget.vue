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
				<NcSelect :model-value="selectedType"
					class="add-widget__input"
					:input-label="t('tables', 'Widget type')"
					:options="typeOptions"
					:clearable="false"
					:disabled="editingWidget !== null"
					label="label"
					data-cy="addWidgetType"
					@update:model-value="selectType" />
			</div>

			<div class="row space-T">
				<NcTextField v-model="title"
					class="add-widget__input"
					:label="t('tables', 'Title')"
					:helper-text="t('tables', 'Shown above the widget. Leave empty to show no title.')"
					data-cy="addWidgetTitle" />
			</div>

			<div class="row space-T">
				<IconField v-model="icon" :label="t('tables', 'Icon')" />
			</div>

			<div class="row space-T">
				<NcTextArea v-model="description"
					class="add-widget__input"
					:label="t('tables', 'Description')"
					resize="vertical"
					rows="2"
					data-cy="addWidgetDescription" />
			</div>

			<div v-if="hasSource" class="row space-T">
				<NcSelect :model-value="nodeOption"
					class="add-widget__input"
					:input-label="t('tables', 'Table or view')"
					:options="nodeOptions"
					:clearable="false"
					:placeholder="t('tables', 'Pick a table or view of this application')"
					label="label"
					data-cy="addWidgetNode"
					@update:model-value="option => nodeRelId = option?.id ?? null" />
				<p v-if="nodeOptions.length === 0" class="add-widget__hint">
					{{ t('tables', 'Add a table or view to the application first.') }}
				</p>
			</div>

			<SchemaForm v-if="widgetType"
				v-model="settings"
				:properties="widgetType.settings" />
		</div>
		<template #actions>
			<NcButton variant="primary" :disabled="!canSubmit" data-cy="addWidgetSubmit" @click="submit">
				{{ editingWidget ? t('tables', 'Save widget') : t('tables', 'Add widget') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { NcButton, NcDialog, NcSelect, NcTextArea, NcTextField } from '@nextcloud/vue'
import { mapActions, mapState } from 'pinia'
import SchemaForm from '../grid/SchemaForm.vue'
import IconField from '../../shared/components/ncIconPicker/IconField.vue'
import { useTablesStore } from '../../store/store.js'
import { applicationNodeOptions, defaultSettings, findWidgetType } from '../grid/gridLayout.js'
import { WIDGET_TYPE_TABS, getWidgetComponent } from '../grid/widgetRegistry.js'

/**
 * Collects the type, title, icon, description and settings of a widget; the settings form comes from the
 * schema of the type. A widget of a type with a source also gets one of the
 * application's own tables or views. The parent places the widget on the grid.
 */
export default {
	name: 'AddWidget',
	components: {
		IconField,
		NcButton,
		NcDialog,
		NcSelect,
		NcTextArea,
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
		// The nodes of the application by relation id: the only tables and views a widget can show
		nodes: {
			type: Object,
			default: () => ({}),
		},
		// False for a widget in a tab: tabs go one level deep
		allowTabs: {
			type: Boolean,
			default: true,
		},
	},
	emits: ['close', 'submit'],
	data() {
		return {
			selectedType: null,
			title: '',
			icon: null,
			description: '',
			settings: {},
			nodeRelId: null,
		}
	},
	computed: {
		...mapState(useTablesStore, ['widgetTypes', 'tables', 'views']),
		// A type the server knows but this frontend cannot show yet is not offered
		typeOptions() {
			return this.widgetTypes
				.filter(widgetType => getWidgetComponent(widgetType.type) !== null)
				.filter(widgetType => this.allowTabs || widgetType.type !== WIDGET_TYPE_TABS || widgetType.type === this.editingWidget?.type)
				.map(widgetType => ({ id: widgetType.type, label: widgetType.title }))
		},
		widgetType() {
			return findWidgetType(this.widgetTypes, this.selectedType?.id)
		},
		hasSource() {
			return !!this.widgetType?.hasSource
		},
		nodeOptions() {
			return applicationNodeOptions(this.nodes, this.tables, this.views)
		},
		nodeOption() {
			return this.nodeOptions.find(option => option.id === this.nodeRelId) ?? null
		},
		canSubmit() {
			if (!this.widgetType || (this.hasSource && !this.nodeOption)) {
				return false
			}
			return Object.entries(this.widgetType.settings).every(([name, setting]) => {
				return !setting.required || String(this.settings[name] ?? '').trim() !== ''
			})
		},
	},
	watch: {
		showModal(open) {
			if (open) {
				this.reset()
			}
		},
	},
	methods: {
		...mapActions(useTablesStore, ['loadWidgetTypes']),
		/**
		 * Fills the form for a new widget, or with the widget being configured. A widget of a type
		 * the server does not know keeps no type here, so it can never be saved as another one.
		 */
		async reset() {
			// Awaited only when the types are missing, so the common case fills the form before it renders
			if (this.widgetTypes.length === 0) {
				await this.loadWidgetTypes()
			}
			if (this.editingWidget) {
				this.selectedType = this.typeOptions.find(option => option.id === this.editingWidget.type) ?? null
				this.title = this.editingWidget.title ?? ''
				this.icon = this.editingWidget.icon ?? null
				this.description = this.editingWidget.description ?? ''
				this.settings = { ...defaultSettings(this.widgetType), ...JSON.parse(JSON.stringify(this.editingWidget.settings ?? {})) }
				this.nodeRelId = this.editingWidget.nodeRelId ?? null
				return
			}
			this.title = ''
			this.icon = null
			this.description = ''
			this.selectType(this.typeOptions[0] ?? null)
		},
		/**
		 * A type the user picks starts from the defaults of that type. Only this resets the form,
		 * so loading a widget to configure keeps its settings.
		 *
		 * @param {object|null} option the picked type
		 */
		selectType(option) {
			this.selectedType = option
			this.settings = defaultSettings(this.widgetType)
			this.nodeRelId = null
		},
		/**
		 * Hands the widget to the parent, which places it on the grid.
		 */
		submit() {
			if (!this.canSubmit) {
				return
			}
			this.$emit('submit', {
				type: this.widgetType.type,
				title: this.title.trim(),
				icon: this.icon,
				description: this.description.trim(),
				settings: JSON.parse(JSON.stringify(this.settings)),
				nodeRelId: this.hasSource ? this.nodeRelId : null,
			})
		},
	},
}
</script>

<style lang="scss" scoped>
.modal__content {
	padding-inline-end: 0 !important;

	.add-widget__input {
		width: 100%;
	}

	.add-widget__hint {
		color: var(--color-text-maxcontrast);
	}
}
</style>
