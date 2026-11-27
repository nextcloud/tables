<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="schema-form">
		<div v-for="(property, name) in properties"
			:key="name"
			class="schema-form__field"
			:class="{ 'schema-form__field--required': property.required }"
			:data-cy="fieldDataCy(name)">
			<NcTextField v-if="property.type === 'string'"
				class="schema-form__input"
				:model-value="value(name)"
				:label="property.title"
				:required="!!property.required"
				:helper-text="property.hint"
				@update:model-value="text => set(name, text)" />
			<NcTextArea v-else-if="property.type === 'text'"
				class="schema-form__input"
				:model-value="value(name)"
				:label="property.title"
				:required="!!property.required"
				:helper-text="property.hint"
				resize="vertical"
				rows="6"
				@update:model-value="text => set(name, text)" />
			<NcSelect v-else-if="property.type === 'enum'"
				class="schema-form__input"
				:model-value="enumOption(property, value(name))"
				:input-label="property.title"
				:required="!!property.required"
				:options="property.options"
				:clearable="false"
				label="label"
				@update:model-value="option => set(name, option?.value ?? property.default)">
				<template #option="{ label }">
					<SelectOptionLabel :label="label" />
				</template>
				<template #selected-option="{ label }">
					<SelectOptionLabel :label="label" />
				</template>
			</NcSelect>
			<TabsEditor v-else-if="property.type === 'tabs'"
				:model-value="value(name) ?? []"
				:label="property.title"
				:required="!!property.required"
				:max-items="property.maxItems"
				@update:model-value="tabs => set(name, tabs)" />
		</div>
	</div>
</template>

<script>
import { NcSelect, NcTextArea, NcTextField } from '@nextcloud/vue'
import SelectOptionLabel from '../../shared/components/SelectOptionLabel.vue'
import TabsEditor from './TabsEditor.vue'

/**
 * Renders the settings form of a widget from the schema of its type, so a
 * new widget type needs a schema on the server and a renderer, not a dialog.
 */
export default {
	name: 'SchemaForm',
	components: {
		NcSelect,
		NcTextArea,
		NcTextField,
		SelectOptionLabel,
		TabsEditor,
	},
	props: {
		properties: {
			type: Object,
			required: true,
		},
		modelValue: {
			type: Object,
			required: true,
		},
	},
	emits: ['update:modelValue'],
	methods: {
		/**
		 * @param {string} name property name
		 * @return {string} the data-cy of the field, in camelCase
		 */
		fieldDataCy(name) {
			return 'widgetField' + name.charAt(0).toUpperCase() + name.slice(1)
		},
		/**
		 * @param {string} name property name
		 * @return {string|Array|null} the current value of the property
		 */
		value(name) {
			return this.modelValue[name]
		},
		/**
		 * @param {string} name property name
		 * @param {string|Array|null} value the new value of the property
		 */
		set(name, value) {
			this.$emit('update:modelValue', { ...this.modelValue, [name]: value })
		},
		/**
		 * @param {object} property the enum property
		 * @param {string} value the stored value
		 * @return {object|null} the option of the value, else the option of the default
		 */
		enumOption(property, value) {
			return property.options.find(option => option.value === value) ?? property.options.find(option => option.value === property.default) ?? null
		},
	},
}
</script>

<style lang="scss" scoped>
.schema-form {
	&__field {
		margin-top: calc(2 * var(--default-grid-baseline));
	}

	&__input {
		width: 100%;
	}
}

// The mark has an empty alternative text, so it stays out of the accessible name
.schema-form__field--required > .schema-form__input :deep(:is(.input-field__label, .textarea__label))::after {
	content: ' *' / '';
}
</style>
