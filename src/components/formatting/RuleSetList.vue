<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="rule-set-list"
		@dragenter.prevent
		@dragover.prevent>
		<RuleSetListItem v-for="(ruleSet, index) in mutableRuleSets"
			:key="ruleSet.id"
			:rule-set="ruleSet"
			:index="index"
			:active="ruleSet.id === activeId"
			:columns="columns"
			@click="$emit('select', ruleSet.id)"
			@dragstart="dragStart(index)"
			@dragover="dragOver(index)"
			@dragend="dragEnd"
			@toggle-enabled="$emit('toggle-enabled', $event)"
			@delete="$emit('delete', $event)" />
		<p v-if="mutableRuleSets.length === 0" class="rule-set-list__empty">
			{{ t('tables', 'No rule sets yet. Create one to get started.') }}
		</p>
	</div>
</template>

<script>
import RuleSetListItem from './RuleSetListItem.vue'

export default {
	name: 'RuleSetList',

	components: {
		RuleSetListItem,
	},

	props: {
		ruleSets: {
			type: Array,
			required: true,
		},
		columns: {
			type: Array,
			default: () => [],
		},
		activeId: {
			type: String,
			default: null,
		},
	},

	emits: ['select', 'reorder', 'toggle-enabled', 'delete'],

	data() {
		return {
			mutableRuleSets: [...this.ruleSets],
			draggedItem: null,
			startDragIndex: null,
		}
	},

	watch: {
		ruleSets: {
			handler(val) {
				this.mutableRuleSets = [...val]
			},
			deep: true,
		},
	},

	methods: {
		dragStart(index) {
			this.draggedItem = this.mutableRuleSets[index]
			this.startDragIndex = index
		},

		dragOver(index) {
			if (this.draggedItem === null) return
			const draggedIndex = this.mutableRuleSets.indexOf(this.draggedItem)
			if (index !== draggedIndex) {
				this.mutableRuleSets.splice(draggedIndex, 1)
				this.mutableRuleSets.splice(index, 0, this.draggedItem)
			}
		},

		dragEnd() {
			if (this.draggedItem === null) return
			if (this.startDragIndex !== this.mutableRuleSets.indexOf(this.draggedItem)) {
				this.$emit('reorder', this.mutableRuleSets.map(rs => rs.id))
			}
			this.draggedItem = null
			this.startDragIndex = null
		},
	},
}
</script>

<style lang="scss" scoped>
.rule-set-list {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 1);

	&__empty {
		color: var(--color-text-lighter);
		font-style: italic;
		text-align: center;
		padding: calc(var(--default-grid-baseline) * 2) 0;
	}
}
</style>
