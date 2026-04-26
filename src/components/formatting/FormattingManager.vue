<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog size="large"
		:name="t('tables', 'Conditional Formatting')"
		@closing="onClose">
		<div class="formatting-manager">
			<div class="formatting-manager__list">
				<NcButton type="primary"
					:aria-label="t('tables', 'Add rule set')"
					@click="onCreateRuleSet">
					<template #icon>
						<Plus :size="20" />
					</template>
					{{ t('tables', 'New rule set') }}
				</NcButton>
				<RuleSetList :rule-sets="formattingStore.ruleSets"
					:columns="columns"
					:active-id="activeRuleSetId"
					@select="activeRuleSetId = $event"
					@reorder="onReorder"
					@toggle-enabled="onToggleEnabled"
					@delete="onDelete" />
			</div>
			<div class="formatting-manager__editor">
				<RuleSetEditor v-if="activeRuleSet"
					:rule-set="activeRuleSet"
					:view-id="viewId"
					:columns="columns"
					@saved="onRuleSetSaved" />
				<div v-else class="formatting-manager__placeholder">
					<p>{{ t('tables', 'Select a rule set to edit, or create a new one.') }}</p>
				</div>
			</div>
		</div>
	</NcDialog>
</template>

<script>
import { NcButton, NcDialog } from '@nextcloud/vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import { useFormattingStore } from '../../store/formatting.js'
import { useTablesStore } from '../../store/store.js'
import RuleSetList from './RuleSetList.vue'
import RuleSetEditor from './RuleSetEditor.vue'

export default {
	name: 'FormattingManager',

	components: {
		NcButton,
		NcDialog,
		Plus,
		RuleSetList,
		RuleSetEditor,
	},

	props: {
		viewId: {
			type: Number,
			required: true,
		},
		columns: {
			type: Array,
			default: () => [],
		},
	},

	emits: ['close'],

	setup() {
		return {
			formattingStore: useFormattingStore(),
			tablesStore: useTablesStore(),
		}
	},

	data() {
		return {
			activeRuleSetId: null,
		}
	},

	computed: {
		activeRuleSet() {
			return this.formattingStore.ruleSets.find(rs => rs.id === this.activeRuleSetId) ?? null
		},
	},

	methods: {
		onClose() {
			this.formattingStore.showFormattingManager = false
			this.$emit('close')
		},

		async onCreateRuleSet() {
			const created = await this.formattingStore.createRuleSet(this.viewId, {
				title: t('tables', 'New rule set'),
				targetType: 'row',
				targetCol: null,
				mode: 'first-match',
				enabled: true,
				rules: [],
			})
			if (created) {
				this.activeRuleSetId = created.id
			}
		},

		async onReorder(orderedIds) {
			await this.formattingStore.reorder(this.viewId, orderedIds)
		},

		async onToggleEnabled({ id, enabled }) {
			const rs = this.formattingStore.ruleSets.find(r => r.id === id)
			if (!rs) return
			await this.formattingStore.updateRuleSet(this.viewId, id, { ...rs, enabled })
		},

		async onDelete(id) {
			await this.formattingStore.deleteRuleSet(this.viewId, id)
			if (this.activeRuleSetId === id) {
				this.activeRuleSetId = null
			}
		},

		onRuleSetSaved(updated) {
			// RuleSetEditor handles the save via store — just keep activeRuleSetId stable
			this.activeRuleSetId = updated.id
		},
	},
}
</script>

<style lang="scss" scoped>
.formatting-manager {
	display: flex;
	min-height: 480px;

	&__list {
		display: flex;
		flex-direction: column;
		gap: var(--default-grid-baseline);
		width: 300px;
		min-width: 300px;
		border-inline-end: 1px solid var(--color-border);
		padding-inline-end: calc(var(--default-grid-baseline) * 2);
		margin-inline-end: calc(var(--default-grid-baseline) * 2);
	}

	&__editor {
		flex: 1;
		overflow-y: auto;
	}

	&__placeholder {
		display: flex;
		align-items: center;
		justify-content: center;
		height: 100%;
		color: var(--color-text-lighter);
	}
}
</style>
