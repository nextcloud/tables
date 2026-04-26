<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="rule-set-editor">
		<div class="rule-set-editor__meta">
			<div class="rule-set-editor__field">
				<label class="rule-set-editor__label" :for="titleInputId">{{ t('tables', 'Name') }}</label>
				<input :id="titleInputId"
					v-model="localTitle"
					class="rule-set-editor__input"
					type="text"
					:placeholder="t('tables', 'Rule set name')">
			</div>

			<div class="rule-set-editor__field">
				<span class="rule-set-editor__label" aria-hidden="true">{{ t('tables', 'Target') }}</span>
				<NcSelect v-model="selectedTargetType"
					:options="targetTypeOptions"
					label="label"
					:aria-label-combobox="t('tables', 'Target')"
					:clearable="false"
					@input="onTargetTypeChange" />
			</div>

			<div v-if="localTargetType === 'column'" class="rule-set-editor__field">
				<span class="rule-set-editor__label" aria-hidden="true">{{ t('tables', 'Column') }}</span>
				<NcSelect v-model="selectedTargetColumn"
					:options="columns"
					label="title"
					:aria-label-combobox="t('tables', 'Column')"
					:clearable="false" />
			</div>

			<div class="rule-set-editor__field">
				<span class="rule-set-editor__label" aria-hidden="true">{{ t('tables', 'Mode') }}</span>
				<NcSelect v-model="selectedMode"
					:options="modeOptions"
					label="label"
					:aria-label-combobox="t('tables', 'Mode')"
					:clearable="false" />
			</div>

			<div class="rule-set-editor__field">
				<span class="rule-set-editor__label" aria-hidden="true">{{ t('tables', 'Marker') }}</span>
				<NcSelect v-model="selectedMarker"
					:options="markerOptions"
					label="label"
					:aria-label-combobox="t('tables', 'Marker shown on what this rule set paints')"
					:clearable="false">
					<template #option="option">
						<span class="rule-set-editor__marker-option">
							<component :is="option.component" v-if="option.component" :size="18" />
							{{ option.label }}
						</span>
					</template>
				</NcSelect>
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
					:key="ruleKey(rule)"
					:ref="'rule-' + ruleKey(rule)"
					:rule="rule"
					:view-id="viewId"
					:rule-set-id="ruleSet.id"
					:columns="columns"
					:position="idx + 1"
					:count="localRules.length"
					:draggable="true"
					@dragstart="ruleDragStart(idx)"
					@dragover.prevent="ruleDragOver(idx)"
					@dragend="ruleDragEnd"
					@move="moveRule(idx, $event)"
					@saved="onRuleSaved(idx, $event)"
					@delete="deleteRule(idx)" />
			</div>

			<p v-if="localRules.length === 0" class="rule-set-editor__empty">
				{{ t('tables', 'No rules yet. Add one to apply formatting.') }}
			</p>
			<p class="hidden-visually" role="status">
				{{ reorderAnnouncement }}
			</p>
		</div>
	</div>
</template>

<script>
import { NcButton, NcSelect } from '@nextcloud/vue'
import { showError } from '@nextcloud/dialogs'
import Plus from 'vue-material-design-icons/Plus.vue'
import { useFormattingStore } from '../../store/formatting.js'
import { MARKERS } from './markers.js'
import { cleanConditionSet } from './conditionSet.js'
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

	setup() {
		return { formattingStore: useFormattingStore() }
	},

	data() {
		return {
			titleInputId: 'rule-set-title-' + this.ruleSet.id,
			localTitle: this.ruleSet.title,
			localTargetType: this.ruleSet.targetType,
			localTargetCol: this.ruleSet.targetCol,
			localMode: this.ruleSet.mode,
			localMarker: this.ruleSet.marker ?? null,
			localRules: this.ruleSet.rules ? JSON.parse(JSON.stringify(this.ruleSet.rules)) : [],
			saving: false,
			draggedRuleIdx: null,
			nextLocalKey: 0,
			reorderAnnouncement: '',
			targetTypeOptions: [
				{ id: 'row', label: t('tables', 'Row') },
				{ id: 'column', label: t('tables', 'Column') },
			],
			modeOptions: [
				{ id: 'first-match', label: t('tables', 'First match') },
				{ id: 'all-matches', label: t('tables', 'All matches') },
			],
			markerOptions: [
				{ id: null, label: t('tables', 'No marker'), component: null },
				...Object.entries(MARKERS).map(([id, marker]) => ({
					id,
					label: marker.label(),
					component: marker.component,
				})),
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
		selectedMarker: {
			get() { return this.markerOptions.find(o => o.id === this.localMarker) ?? this.markerOptions[0] },
			set(val) { this.localMarker = val?.id ?? null },
		},
	},

	watch: {
		ruleSet: {
			handler(val) {
				this.localTitle = val.title
				this.localTargetType = val.targetType
				this.localTargetCol = val.targetCol
				this.localMode = val.mode
				this.localMarker = val.marker ?? null

				const incoming = new Map((val.rules ?? []).map(rule => [rule.id, rule]))
				const merged = []
				for (const rule of this.localRules) {
					if (!rule.id) {
						merged.push(rule)
					} else if (incoming.has(rule.id)) {
						merged.push(JSON.parse(JSON.stringify(incoming.get(rule.id))))
						incoming.delete(rule.id)
					}
				}
				for (const rule of incoming.values()) {
					merged.push(JSON.parse(JSON.stringify(rule)))
				}
				this.localRules = merged
			},
			deep: true,
		},
	},

	methods: {
		/**
		 * @param {object} rule a rule held in localRules
		 * @return {string} a key that stays with this rule for as long as it exists
		 */
		ruleKey(rule) {
			return rule.id || rule.localKey
		},

		onTargetTypeChange() {
			if (this.localTargetType === 'row') {
				this.localTargetCol = null
			}
		},

		addRule() {
			this.localRules.push({
				id: null,
				localKey: 'new-' + (this.nextLocalKey++),
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

		moveRule(idx, delta) {
			const target = idx + delta
			if (target < 0 || target >= this.localRules.length) {
				return
			}
			const moved = this.localRules.splice(idx, 1)[0]
			this.localRules.splice(target, 0, moved)
			this.reorderAnnouncement = t('tables', '{title} moved to position {position} of {count}.', {
				title: moved.title || t('tables', 'Untitled'),
				position: target + 1,
				count: this.localRules.length,
			})
			this.$nextTick(() => {
				const item = this.$refs['rule-' + this.ruleKey(moved)]
				const instance = Array.isArray(item) ? item[0] : item
				instance?.focusDragHandle?.()
			})
		},

		async saveRuleSet() {
			const cleaned = this.localRules.map(rule => ({ ...rule, condition: cleanConditionSet(rule.condition) }))
			const complete = cleaned.filter(rule => rule.condition.groups.length > 0)
			if (cleaned.some(rule => rule.id && rule.condition.groups.length === 0)) {
				showError(t('tables', 'Complete or delete the rules without a condition before saving.'))
				return
			}

			this.saving = true
			const data = {
				title: this.localTitle,
				targetType: this.localTargetType,
				targetCol: this.localTargetCol,
				mode: this.localMode,
				marker: this.localMarker,
				enabled: this.ruleSet.enabled,
				rules: complete,
			}
			await this.formattingStore.updateRuleSet(this.viewId, this.ruleSet.id, data)
			this.saving = false
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

		&:focus-visible {
			border-color: var(--color-primary-element);
			outline: 2px solid var(--color-primary-element);
			outline-offset: 2px;
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
