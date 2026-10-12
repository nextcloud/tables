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
				<NcTextField v-model="title" class="add-widget__input" :label="t('tables', 'Widget title')" data-cy="addWidgetTitle" />
			</div>
			<div class="row">
				<NcCheckboxRadioSwitch v-model="showTitle" data-cy="addWidgetShowTitle">
					{{ t('tables', 'Show the title above the widget') }}
				</NcCheckboxRadioSwitch>
			</div>

			<SchemaForm v-if="widgetType"
				v-model="content"
				:properties="widgetType.properties"
				@validity="isValid => formIsValid = isValid" />
		</div>
		<template #actions>
			<NcButton variant="primary" :disabled="!canSubmit" data-cy="addWidgetSubmit" @click="submit">
				{{ editingWidget ? t('tables', 'Save widget') : t('tables', 'Add widget') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { NcButton, NcCheckboxRadioSwitch, NcDialog, NcSelect, NcTextField } from '@nextcloud/vue'
import { mapActions, mapState } from 'pinia'
import SchemaForm from '../grid/SchemaForm.vue'
import { useTablesStore } from '../../store/store.js'
import { defaultConfiguration, defaultContent, findWidgetType } from '../grid/gridLayout.js'

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
			formIsValid: true,
		}
	},
	computed: {
		...mapState(useTablesStore, ['widgetTypes']),
		typeOptions() {
			return this.widgetTypes.map(widgetType => ({ id: widgetType.type, label: widgetType.title }))
		},
		widgetType() {
			return findWidgetType(this.widgetTypes, this.selectedType?.id)
		},
		canSubmit() {
			if (!this.widgetType || !this.formIsValid) {
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
				this.title = this.editingWidget.configuration?.title ?? ''
				this.showTitle = !!this.editingWidget.configuration?.showTitle
				this.content = { ...defaultContent(this.widgetType), ...JSON.parse(JSON.stringify(this.editingWidget.content ?? {})) }
				return
			}
			this.title = ''
			this.selectType(this.typeOptions[0] ?? null)
		},
		/**
		 * A type the user picks starts from the defaults of that type. Only this resets the form,
		 * so loading a widget to configure keeps its content.
		 *
		 * @param {object|null} option the picked type
		 */
		selectType(option) {
			this.selectedType = option
			this.showTitle = defaultConfiguration(this.widgetType).showTitle ?? false
			this.content = defaultContent(this.widgetType)
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
				// An empty title stays empty; the card shows the type name in the viewer's language instead
				configuration: { title: this.title.trim(), showTitle: this.showTitle },
				content: JSON.parse(JSON.stringify(this.content)),
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
}
</style>
