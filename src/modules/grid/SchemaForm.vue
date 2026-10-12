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
				:label="property.title"
				@update:model-value="text => set(name, text)" />
			<NcTextArea v-else-if="property.type === 'text'"
				class="schema-form__input"
				:model-value="value(name)"
				:label="property.title"
				:placeholder="t('tables', 'Leave an empty line between paragraphs')"
				resize="vertical"
				rows="6"
				@update:model-value="text => set(name, text)" />
			<NcTextField v-else-if="property.type === 'integer'"
				class="schema-form__input"
				:model-value="String(value(name) ?? '')"
				:label="property.title"
				:error="invalidIntegers.includes(name)"
				:helper-text="invalidIntegers.includes(name) ? t('tables', 'Enter a whole number') : ''"
				type="number"
				@update:model-value="text => setInteger(name, text)" />
			<NcCheckboxRadioSwitch v-else-if="property.type === 'boolean'"
				type="switch"
				:model-value="!!value(name)"
				@update:model-value="checked => set(name, checked)">
				{{ property.title }}
			</NcCheckboxRadioSwitch>
			<NcSelect v-else-if="property.type === 'enum'"
				class="schema-form__input"
				:model-value="enumOption(property, value(name))"
				:input-label="property.title"
				:options="property.options"
				:clearable="false"
				label="label"
				@update:model-value="option => set(name, option?.value ?? property.default)" />
			<NcSelect v-else-if="property.type === 'target'"
				class="schema-form__input"
				:model-value="targetOption(value(name))"
				:input-label="property.title"
				:options="targetOptions"
				:clearable="false"
				:searchable="true"
				label="label"
				@update:model-value="option => set(name, option ? { type: option.targetType, id: option.targetId } : null)" />
			<div v-else-if="property.type === 'color'" class="schema-form__color">
				<NcColorPicker :model-value="colorValue(name)"
					advanced-fields
					clearable
					@update:model-value="color => set(name, color ?? '')">
					<NcButton variant="secondary">
						<template #icon>
							<span class="schema-form__swatch" :style="{ backgroundColor: colorValue(name) ?? 'transparent' }" />
						</template>
						{{ property.title }}
					</NcButton>
				</NcColorPicker>
				<span class="schema-form__hint">{{ colorValue(name) ?? t('tables', 'Theme color') }}</span>
			</div>
		</div>
	</div>
</template>

<script>
import { NcButton, NcCheckboxRadioSwitch, NcColorPicker, NcSelect, NcTextArea, NcTextField } from '@nextcloud/vue'
import { mapState } from 'pinia'
import { useTablesStore } from '../../store/store.js'
import { isHexColor } from '../../shared/utils/gridColors.js'

/**
 * Renders the configuration form of a widget from the schema of its type, so a
 * new widget type needs a schema on the server and a renderer, not a dialog.
 */
export default {
	name: 'SchemaForm',
	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		NcColorPicker,
		NcSelect,
		NcTextArea,
		NcTextField,
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
	emits: ['update:modelValue', 'validity'],
	data() {
		return {
			invalidIntegers: [],
		}
	},
	computed: {
		...mapState(useTablesStore, ['tables', 'views']),
		isValid() {
			return this.invalidIntegers.length === 0
		},
		targetOptions() {
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
					label: t('tables', '{title} (view)', { title: (view.emoji ? view.emoji + ' ' : '') + view.title }, undefined, { escape: false }),
				}))
			return [...tables, ...views]
		},
	},
	watch: {
		properties() {
			// Another widget type has other fields, so earlier invalid input no longer applies
			this.invalidIntegers = []
		},
		isValid: {
			immediate: true,
			handler(isValid) {
				this.$emit('validity', isValid)
			},
		},
	},
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
		 * @return {string|number|boolean|object|null} the current value of the property
		 */
		value(name) {
			return this.modelValue[name]
		},
		/**
		 * @param {string} name property name
		 * @param {string|number|boolean|object|null} value the new value of the property
		 */
		set(name, value) {
			this.$emit('update:modelValue', { ...this.modelValue, [name]: value })
		},
		/**
		 * Stores a whole number, or nothing for an empty field. Anything else is marked
		 * invalid instead of being rounded.
		 *
		 * @param {string} name property name
		 * @param {string} text what was typed
		 */
		setInteger(name, text) {
			const number = Number(text)
			const isValid = text === '' || Number.isInteger(number)
			this.invalidIntegers = isValid ? this.invalidIntegers.filter(invalid => invalid !== name) : [...new Set([...this.invalidIntegers, name])]
			if (isValid) {
				this.set(name, text === '' ? null : number)
			}
		},
		/**
		 * @param {string} name property name
		 * @return {string|undefined} the stored color, or undefined for the theme color
		 */
		colorValue(name) {
			return isHexColor(this.value(name)) ? this.value(name) : undefined
		},
		/**
		 * @param {object} property the enum property
		 * @param {string} value the stored value
		 * @return {object|null} the option of the value, else the option of the default
		 */
		enumOption(property, value) {
			return property.options.find(option => option.value === value) ?? property.options.find(option => option.value === property.default) ?? null
		},
		/**
		 * @param {{type: string, id: number}|null} target the stored target
		 * @return {object|null} the option of the target, or null when it is not in the lists
		 */
		targetOption(target) {
			if (!target) {
				return null
			}
			return this.targetOptions.find(option => option.targetType === target.type && option.targetId === target.id) ?? null
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

	&__color {
		display: flex;
		align-items: center;
		gap: calc(3 * var(--default-grid-baseline));
	}

	&__swatch {
		display: inline-block;
		width: calc(5 * var(--default-grid-baseline));
		height: calc(5 * var(--default-grid-baseline));
		border: 1px solid var(--color-border-maxcontrast);
		border-radius: var(--border-radius);
	}

	&__hint {
		color: var(--color-text-maxcontrast);
	}
}
</style>
