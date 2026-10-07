<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="schema-form">
		<div v-for="(property, name) in properties" :key="name" class="schema-form__field" :data-cy="'widgetField-' + name">
			<NcTextField v-if="property.type === 'string'"
				:model-value="value(name)"
				:label="label(property)"
				@update:model-value="text => set(name, text)" />
			<NcTextArea v-else-if="property.type === 'text'"
				:model-value="value(name)"
				:label="label(property)"
				:placeholder="t('tables', 'Leave an empty line between paragraphs')"
				resize="vertical"
				rows="6"
				@update:model-value="text => set(name, text)" />
			<NcTextField v-else-if="property.type === 'integer'"
				:model-value="String(value(name) ?? '')"
				:label="label(property)"
				type="number"
				@update:model-value="text => set(name, text === '' ? null : parseInt(text))" />
			<NcCheckboxRadioSwitch v-else-if="property.type === 'boolean'"
				:model-value="!!value(name)"
				@update:model-value="checked => set(name, checked)">
				{{ label(property) }}
			</NcCheckboxRadioSwitch>
			<NcSelect v-else-if="property.type === 'enum'"
				:model-value="enumOption(property, value(name))"
				:input-label="label(property)"
				:options="property.options"
				:clearable="false"
				label="label"
				@update:model-value="option => set(name, option?.value ?? property.default)" />
			<NcSelect v-else-if="property.type === 'target'"
				:model-value="targetOption(value(name))"
				:input-label="label(property)"
				:options="targetOptions"
				:clearable="false"
				:searchable="true"
				label="label"
				@update:model-value="option => set(name, option ? { type: option.targetType, id: option.targetId } : null)" />
			<div v-else-if="property.type === 'color'" class="schema-form__color">
				<label>
					{{ label(property) }}
					<input :value="value(name) || '#000000'" type="color" @input="event => set(name, event.target.value)">
				</label>
				<NcButton v-if="value(name)" variant="tertiary" @click="set(name, '')">
					{{ t('tables', 'Use theme color') }}
				</NcButton>
				<span v-else class="schema-form__hint">{{ t('tables', 'Theme color') }}</span>
			</div>
		</div>
	</div>
</template>

<script>
import { NcButton, NcCheckboxRadioSwitch, NcSelect, NcTextArea, NcTextField } from '@nextcloud/vue'
import { mapState } from 'pinia'
import { useTablesStore } from '../../store/store.js'

/**
 * Renders the configuration form of a widget from the schema of its type, so a
 * new widget type needs a schema on the server and a renderer, not a dialog.
 */
export default {
	name: 'SchemaForm',
	components: {
		NcButton,
		NcCheckboxRadioSwitch,
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
	emits: ['update:modelValue'],
	computed: {
		...mapState(useTablesStore, ['tables', 'views']),
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
					label: (view.emoji ? view.emoji + ' ' : '') + view.title + ' (' + t('tables', 'view') + ')',
				}))
			return [...tables, ...views]
		},
	},
	methods: {
		value(name) {
			return this.modelValue[name]
		},
		set(name, value) {
			this.$emit('update:modelValue', { ...this.modelValue, [name]: value })
		},
		label(property) {
			return t('tables', property.title)
		},
		enumOption(property, value) {
			return property.options.find(option => option.value === value) ?? property.options.find(option => option.value === property.default) ?? null
		},
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
		margin-top: calc(2 * var(--default-grid-baseline, 4px));

		> :deep(.v-select),
		> :deep(.input-field),
		> :deep(.textarea) {
			width: 100%;
		}
	}

	&__color {
		display: flex;
		align-items: center;
		gap: calc(3 * var(--default-grid-baseline, 4px));

		label {
			display: inline-flex;
			align-items: center;
			gap: calc(2 * var(--default-grid-baseline, 4px));
		}
	}

	&__hint {
		color: var(--color-text-maxcontrast);
	}
}
</style>
