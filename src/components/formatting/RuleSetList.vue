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
			:ref="'item-' + ruleSet.id"
			:rule-set="ruleSet"
			:index="index"
			:count="mutableRuleSets.length"
			:active="ruleSet.id === activeId"
			:columns="columns"
			@click="$emit('select', ruleSet.id)"
			@dragstart="dragStart(index)"
			@dragover="dragOver(index)"
			@dragend="dragEnd"
			@move="move(index, $event)"
			@toggle-enabled="$emit('toggle-enabled', $event)"
			@delete="$emit('delete', $event)" />
		<p v-if="mutableRuleSets.length === 0" class="rule-set-list__empty">
			{{ t('tables', 'No rule sets yet. Create one to get started.') }}
		</p>
		<p class="hidden-visually" role="status">
			{{ reorderAnnouncement }}
		</p>
	</div>
</template>

<script>
import debounce from 'debounce'
import RuleSetListItem from './RuleSetListItem.vue'
import dragReorderMixin from '../../shared/mixins/dragReorderMixin.js'

export default {
	name: 'RuleSetList',

	components: {
		RuleSetListItem,
	},

	mixins: [dragReorderMixin],

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
			reorderAnnouncement: '',
		}
	},

	computed: {
		reorderableList() {
			return this.mutableRuleSets
		},
	},

	watch: {
		ruleSets: {
			handler(val) {
				this.mutableRuleSets = [...val]
			},
			deep: true,
		},
	},

	created() {
		this.debouncedReorder = debounce(() => {
			this.$emit('reorder', this.mutableRuleSets.map(rs => rs.id))
		}, 400)
	},

	beforeUnmount() {
		this.debouncedReorder.clear()
	},

	methods: {
		onReorderCommitted() {
			this.$emit('reorder', this.mutableRuleSets.map(rs => rs.id))
		},

		move(index, delta) {
			const target = index + delta
			if (target < 0 || target >= this.mutableRuleSets.length) {
				return
			}
			const moved = this.mutableRuleSets.splice(index, 1)[0]
			this.mutableRuleSets.splice(target, 0, moved)
			this.debouncedReorder()
			this.reorderAnnouncement = t('tables', '{title} moved to position {position} of {count}.', {
				title: moved.title || t('tables', 'Untitled'),
				position: target + 1,
				count: this.mutableRuleSets.length,
			})
			this.$nextTick(() => {
				const item = this.$refs['item-' + moved.id]
				const instance = Array.isArray(item) ? item[0] : item
				instance?.focusDragHandle?.()
			})
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
