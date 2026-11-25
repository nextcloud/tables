<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="schema-form">
		<div v-for="(property, name) in properties" :key="name" class="schema-form__field" :data-cy="fieldDataCy(name)">
			<NcTextField v-if="property.type === 'string'"
				class="schema-form__input"
				:model-value="value(name)"
				:label="fieldLabel(property)"
				:required="!!property.required"
				:helper-text="property.hint"
				@update:model-value="text => set(name, text)" />
			<NcTextArea v-else-if="property.type === 'text'"
				class="schema-form__input"
				:model-value="value(name)"
				:label="fieldLabel(property)"
				:required="!!property.required"
				:helper-text="property.hint"
				resize="vertical"
				rows="6"
				@update:model-value="text => set(name, text)" />
			<NcSelect v-else-if="property.type === 'enum'"
				class="schema-form__input"
				:model-value="enumOption(property, value(name))"
				:input-label="fieldLabel(property)"
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
		</div>
	</div>
</template>

<script>
import { NcSelect, NcTextArea, NcTextField } from '@nextcloud/vue'
import SelectOptionLabel from '../../shared/components/SelectOptionLabel.vue'
import { requiredLabel } from './gridLayout.js'

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
		 * @param {object} property a property of the schema
		 * @return {string} its title, marked when the property is required
		 */
		fieldLabel(property) {
			return property.required ? requiredLabel(property.title) : property.title
		},
		/**
		 * @param {string} name property name
		 * @return {string|null} the current value of the property
		 */
		value(name) {
			return this.modelValue[name]
		},
		/**
		 * @param {string} name property name
		 * @param {string|null} value the new value of the property
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
</style>
