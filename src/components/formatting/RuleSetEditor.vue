<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="rule-set-editor">
		<div class="rule-set-editor__meta">
			<div class="rule-set-editor__field">
				<label class="rule-set-editor__label">{{ t('tables', 'Name') }}</label>
				<input v-model="localTitle"
					class="rule-set-editor__input"
					type="text"
					:placeholder="t('tables', 'Rule set name')"
					@change="onMetaChange">
			</div>

			<div class="rule-set-editor__field">
				<label class="rule-set-editor__label">{{ t('tables', 'Target') }}</label>
				<NcSelect v-model="selectedTargetType"
					:options="targetTypeOptions"
					label="label"
					:clearable="false"
					@input="onTargetTypeChange" />
			</div>

			<div v-if="localTargetType === 'column'" class="rule-set-editor__field">
				<label class="rule-set-editor__label">{{ t('tables', 'Column') }}</label>
				<NcSelect v-model="selectedTargetColumn"
					:options="columns"
					label="title"
					:clearable="false"
					@input="onTargetColumnChange" />
			</div>

			<div class="rule-set-editor__field">
				<label class="rule-set-editor__label">{{ t('tables', 'Mode') }}</label>
				<NcSelect v-model="selectedMode"
					:options="modeOptions"
					label="label"
					:clearable="false"
					@input="onMetaChange" />
			</div>

			<div class="rule-set-editor__actions">
				<NcButton type="primary"
					:aria-label="t('tables', 'Save rule set')"
					:disabled="saving"
					@click="saveRuleSet">
					{{ t('tables', 'Save rule set') }}
				</NcButton>
			</div>
		</div>

		<div class="rule-set-editor__rules">
			<div class="rule-set-editor__rules-header">
				<span class="rule-set-editor__label">{{ t('tables', 'Rules') }}</span>
				<NcButton type="tertiary"
					:aria-label="t('tables', 'Add rule')"
					@click="addRule">
					<template #icon>
						<Plus :size="18" />
					</template>
					{{ t('tables', 'Add rule') }}
				</NcButton>
			</div>

			<div class="rule-set-editor__rules-list"
				@dragenter.prevent
				@dragover.prevent>
				<RuleEditor v-for="(rule, idx) in localRules"
					:key="rule.id || 'new-' + idx"
					:rule="rule"
					:view-id="viewId"
					:rule-set-id="ruleSet.id"
					:columns="columns"
					:draggable="true"
					@dragstart="ruleDragStart(idx)"
					@dragover.prevent="ruleDragOver(idx)"
					@dragend="ruleDragEnd"
					@saved="onRuleSaved(idx, $event)"
					@delete="deleteRule(idx)" />
			</div>

			<p v-if="localRules.length === 0" class="rule-set-editor__empty">
				{{ t('tables', 'No rules yet. Add one to apply formatting.') }}
			</p>
		</div>
	</div>
</template>

<script>
import { NcButton, NcSelect } from '@nextcloud/vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import { useFormattingStore } from '../../store/formatting.js'
import RuleEditor from './RuleEditor.vue'

export default {
	name: 'RuleSetEditor',

	components: {
		NcButton,
		NcSelect,
		Plus,
		RuleEditor,
	},

	props: {
		ruleSet: {
			type: Object,
			required: true,
		},
		viewId: {
			type: Number,
			required: true,
		},
		columns: {
			type: Array,
			default: () => [],
		},
	},

	emits: ['saved'],

	setup() {
		return { formattingStore: useFormattingStore() }
	},

	data() {
		return {
			localTitle: this.ruleSet.title,
			localTargetType: this.ruleSet.targetType,
			localTargetCol: this.ruleSet.targetCol,
			localMode: this.ruleSet.mode,
			localRules: this.ruleSet.rules ? JSON.parse(JSON.stringify(this.ruleSet.rules)) : [],
			saving: false,
			draggedRuleIdx: null,
			targetTypeOptions: [
				{ id: 'row', label: t('tables', 'Row') },
				{ id: 'column', label: t('tables', 'Column') },
			],
			modeOptions: [
				{ id: 'first-match', label: t('tables', 'First match') },
				{ id: 'all-matches', label: t('tables', 'All matches') },
			],
		}
	},

	computed: {
		selectedTargetType: {
			get() { return this.targetTypeOptions.find(o => o.id === this.localTargetType) ?? null },
			set(val) { this.localTargetType = val?.id ?? 'row' },
		},
		selectedTargetColumn: {
			get() { return this.columns.find(c => c.id === this.localTargetCol) ?? null },
			set(val) { this.localTargetCol = val?.id ?? null },
		},
		selectedMode: {
			get() { return this.modeOptions.find(o => o.id === this.localMode) ?? null },
			set(val) { this.localMode = val?.id ?? 'first-match' },
		},
	},

	watch: {
		ruleSet: {
			handler(val) {
				this.localTitle = val.title
				this.localTargetType = val.targetType
				this.localTargetCol = val.targetCol
				this.localMode = val.mode
				this.localRules = val.rules ? JSON.parse(JSON.stringify(val.rules)) : []
			},
			deep: true,
		},
	},

	methods: {
		onMetaChange() {
			// deferred to explicit save
		},

		onTargetTypeChange() {
			if (this.localTargetType === 'row') {
				this.localTargetCol = null
			}
		},

		onTargetColumnChange() {
			// handled by selectedTargetColumn setter
		},

		addRule() {
			this.localRules.push({
				id: null,
				title: '',
				enabled: true,
				broken: false,
				condition: { groups: [{ conditions: [] }] },
				format: {},
			})
		},

		async deleteRule(idx) {
			const rule = this.localRules[idx]
			if (rule.id) {
				await this.formattingStore.deleteRule(this.viewId, this.ruleSet.id, rule.id)
			}
			this.localRules.splice(idx, 1)
		},

		onRuleSaved(idx, saved) {
			this.localRules.splice(idx, 1, saved)
		},

		ruleDragStart(idx) {
			this.draggedRuleIdx = idx
		},

		ruleDragOver(idx) {
			if (this.draggedRuleIdx === null || idx === this.draggedRuleIdx) return
			const item = this.localRules.splice(this.draggedRuleIdx, 1)[0]
			this.localRules.splice(idx, 0, item)
			this.draggedRuleIdx = idx
		},

		ruleDragEnd() {
			this.draggedRuleIdx = null
		},

		async saveRuleSet() {
			this.saving = true
			const data = {
				title: this.localTitle,
				targetType: this.localTargetType,
				targetCol: this.localTargetCol,
				mode: this.localMode,
				enabled: this.ruleSet.enabled,
				rules: this.localRules.filter(r => r.id),
			}
			const saved = await this.formattingStore.updateRuleSet(this.viewId, this.ruleSet.id, data)
			this.saving = false
			if (saved) this.$emit('saved', saved)
		},
	},
}
</script>

<style lang="scss" scoped>
.rule-set-editor {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 3);

	&__meta {
		display: flex;
		flex-direction: column;
		gap: calc(var(--default-grid-baseline) * 2);
	}

	&__field {
		display: flex;
		align-items: center;
		gap: calc(var(--default-grid-baseline) * 2);
	}

	&__label {
		min-width: 80px;
		font-weight: 600;
		font-size: 0.9em;
		color: var(--color-text-lighter);
	}

	&__input {
		flex: 1;
		border: 2px solid var(--color-border-maxcontrast);
		border-radius: var(--border-radius);
		padding: 6px 10px;
		background-color: var(--color-main-background);
		color: var(--color-main-text);

		&:focus {
			border-color: var(--color-primary-element);
			outline: none;
		}
	}

	&__actions {
		display: flex;
		justify-content: flex-end;
	}

	&__rules-header {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-block-end: var(--default-grid-baseline);
	}

	&__empty {
		color: var(--color-text-lighter);
		font-style: italic;
	}
}
</style>
