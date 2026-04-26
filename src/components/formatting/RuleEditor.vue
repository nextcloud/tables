<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="rule-editor">
		<div class="rule-editor__header">
			<NcButton type="tertiary-no-background"
				:aria-label="t('tables', 'Drag to reorder')"
				class="rule-editor__drag-handle"
				@click.stop>
				<template #icon>
					<DragHorizontalVariant :size="18" />
				</template>
			</NcButton>

			<input v-model="localTitle"
				class="rule-editor__title-input"
				type="text"
				:placeholder="t('tables', 'Rule name')"
				@change="onMetaChange" />

			<NcCheckboxRadioSwitch :checked="localEnabled"
				@update:checked="onEnabledChange" />

			<NcButton type="tertiary-no-background"
				:aria-label="t('tables', 'Delete rule')"
				@click="$emit('delete')">
				<template #icon>
					<Delete :size="18" />
				</template>
			</NcButton>
		</div>

		<div class="rule-editor__body">
			<div class="rule-editor__section-label">
				{{ t('tables', 'Conditions') }}
			</div>
			<ConditionGroupBuilder :condition-set.sync="localCondition"
				:columns="columns"
				@update:conditionSet="onConditionChange" />

			<div class="rule-editor__section-label">
				{{ t('tables', 'Style') }}
			</div>
			<FormatStylePicker :format.sync="localFormat"
				@update:format="onFormatChange" />

			<SyntheticPreview :condition-set="localCondition"
				:format="localFormat"
				:columns="columns" />

			<div class="rule-editor__actions">
				<NcButton type="primary"
					:aria-label="t('tables', 'Save rule')"
					:disabled="saving"
					@click="saveRule">
					{{ t('tables', 'Save') }}
				</NcButton>
			</div>
		</div>
	</div>
</template>

<script>
import { NcButton, NcCheckboxRadioSwitch } from '@nextcloud/vue'
import DragHorizontalVariant from 'vue-material-design-icons/DragHorizontalVariant.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import { useFormattingStore } from '../../store/formatting.js'
import ConditionGroupBuilder from './ConditionGroupBuilder.vue'
import FormatStylePicker from './FormatStylePicker.vue'
import SyntheticPreview from './SyntheticPreview.vue'

export default {
	name: 'RuleEditor',

	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		DragHorizontalVariant,
		Delete,
		ConditionGroupBuilder,
		FormatStylePicker,
		SyntheticPreview,
	},

	props: {
		rule: {
			type: Object,
			required: true,
		},
		viewId: {
			type: Number,
			required: true,
		},
		ruleSetId: {
			type: String,
			required: true,
		},
		columns: {
			type: Array,
			default: () => [],
		},
	},

	emits: ['saved', 'delete'],

	setup() {
		return { formattingStore: useFormattingStore() }
	},

	data() {
		return {
			localTitle: this.rule.title,
			localEnabled: this.rule.enabled,
			localCondition: this.rule.condition ? JSON.parse(JSON.stringify(this.rule.condition)) : { groups: [] },
			localFormat: this.rule.format ? { ...this.rule.format } : {},
			saving: false,
		}
	},

	watch: {
		rule: {
			handler(val) {
				this.localTitle = val.title
				this.localEnabled = val.enabled
				this.localCondition = val.condition ? JSON.parse(JSON.stringify(val.condition)) : { groups: [] }
				this.localFormat = val.format ? { ...val.format } : {}
			},
			deep: true,
		},
	},

	methods: {
		onMetaChange() {
			// deferred to explicit Save
		},

		onEnabledChange(val) {
			this.localEnabled = val
		},

		onConditionChange(val) {
			this.localCondition = val
		},

		onFormatChange(val) {
			this.localFormat = val
		},

		async saveRule() {
			this.saving = true
			const data = {
				title: this.localTitle,
				enabled: this.localEnabled,
				condition: this.localCondition,
				format: this.localFormat,
			}
			let saved
			if (this.rule.id) {
				saved = await this.formattingStore.updateRule(this.viewId, this.ruleSetId, this.rule.id, data)
			} else {
				saved = await this.formattingStore.createRule(this.viewId, this.ruleSetId, data)
			}
			this.saving = false
			if (saved) this.$emit('saved', saved)
		},
	},
}
</script>

<style lang="scss" scoped>
.rule-editor {
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	margin-block-end: calc(var(--default-grid-baseline) * 2);

	&__header {
		display: flex;
		align-items: center;
		gap: var(--default-grid-baseline);
		padding: calc(var(--default-grid-baseline) * 1) calc(var(--default-grid-baseline) * 2);
		background-color: var(--color-background-dark);
		border-radius: var(--border-radius-large) var(--border-radius-large) 0 0;
	}

	&__drag-handle {
		cursor: grab;
		flex-shrink: 0;
	}

	&__title-input {
		flex: 1;
		border: none;
		background: transparent;
		color: var(--color-main-text);
		font-size: 1em;

		&:focus {
			outline: 2px solid var(--color-primary-element);
			border-radius: var(--border-radius);
		}
	}

	&__body {
		padding: calc(var(--default-grid-baseline) * 2);
		display: flex;
		flex-direction: column;
		gap: calc(var(--default-grid-baseline) * 2);
	}

	&__section-label {
		font-weight: 600;
		font-size: 0.9em;
		color: var(--color-text-lighter);
		text-transform: uppercase;
	}

	&__actions {
		display: flex;
		justify-content: flex-end;
	}
}
</style>
