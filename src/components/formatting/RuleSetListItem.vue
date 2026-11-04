<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="rule-set-list-item" :class="[{ 'rule-set-list-item--active': active }]"
		:draggable="true"
		@click="$emit('click')"
		@dragstart="$emit('dragstart', $event)"
		@dragover.prevent="$emit('dragover', $event)"
		@dragend="$emit('dragend')">
		<NcButton ref="dragHandle"
			type="tertiary-no-background"
			class="rule-set-list-item__drag-handle"
			:aria-label="reorderLabel"
			@click.stop
			@keydown.up.prevent.stop="$emit('move', -1)"
			@keydown.down.prevent.stop="$emit('move', 1)">
			<template #icon>
				<DragHorizontalVariant :size="18" />
			</template>
		</NcButton>

		<span class="rule-set-list-item__order">{{ index + 1 }}</span>

		<span class="rule-set-list-item__name">
			<AlertCircleOutline v-if="ruleSet.broken"
				:size="16"
				class="rule-set-list-item__broken"
				role="img"
				:aria-label="t('tables', 'This rule set references a deleted or changed column')"
				:title="t('tables', 'This rule set references a deleted or changed column')" />
			<button type="button"
				class="rule-set-list-item__title"
				:aria-current="active ? 'true' : undefined"
				@click.stop="$emit('click')">
				{{ ruleSet.title || t('tables', 'Untitled') }}
			</button>
		</span>

		<span class="rule-set-list-item__target" :class="[targetBadgeClass]">
			{{ targetLabel }}
		</span>

		<NcCheckboxRadioSwitch :model-value="ruleSet.enabled"
			:disabled="ruleSet.broken"
			:aria-label="t('tables', 'Rule set enabled')"
			@update:model-value="$emit('toggle-enabled', { id: ruleSet.id, enabled: $event })"
			@click.stop />

		<NcButton type="tertiary-no-background"
			:aria-label="t('tables', 'Delete rule set')"
			@click.stop="$emit('delete', ruleSet.id)">
			<template #icon>
				<DeleteOutline :size="18" />
			</template>
		</NcButton>
	</div>
</template>

<script>
import { NcButton, NcCheckboxRadioSwitch } from '@nextcloud/vue'
import DragHorizontalVariant from 'vue-material-design-icons/DragHorizontalVariant.vue'
import AlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import DeleteOutline from 'vue-material-design-icons/DeleteOutline.vue'

export default {
	name: 'RuleSetListItem',

	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		DragHorizontalVariant,
		AlertCircleOutline,
		DeleteOutline,
	},

	props: {
		ruleSet: {
			type: Object,
			required: true,
		},
		index: {
			type: Number,
			required: true,
		},
		active: {
			type: Boolean,
			default: false,
		},
		columns: {
			type: Array,
			default: () => [],
		},
		count: {
			type: Number,
			default: 1,
		},
	},

	emits: ['click', 'dragstart', 'dragover', 'dragend', 'toggle-enabled', 'delete', 'move'],

	computed: {
		reorderLabel() {
			return t('tables', 'Rule set {position} of {count}. Drag, or use the arrow keys, to reorder.', {
				position: this.index + 1,
				count: this.count,
			})
		},

		targetColumn() {
			if (this.ruleSet.targetType !== 'column' || !this.ruleSet.targetCol) return null
			return this.columns.find(c => c.id === this.ruleSet.targetCol) ?? null
		},

		targetLabel() {
			if (this.ruleSet.targetType === 'row') return t('tables', 'ROW')
			return this.targetColumn?.title ?? t('tables', 'Column')
		},

		targetBadgeClass() {
			return this.ruleSet.targetType === 'row'
				? 'rule-set-list-item__target--row'
				: 'rule-set-list-item__target--col'
		},
	},

	methods: {
		focusDragHandle() {
			const root = this.$refs.dragHandle?.$el
			const button = root?.matches?.('button') ? root : root?.querySelector?.('button')
			button?.focus()
		},
	},
}
</script>

<style lang="scss" scoped>
.rule-set-list-item {
	display: flex;
	align-items: center;
	gap: calc(var(--default-grid-baseline) * 1);
	padding: calc(var(--default-grid-baseline) * 1);
	border-radius: var(--border-radius-large);
	cursor: pointer;
	transition: background-color 0.1s ease;

	&:hover,
	&--active {
		background-color: var(--color-background-hover);
	}

	&--active {
		background-color: var(--color-primary-element-light);
	}

	&__drag-handle {
		cursor: grab;
		flex-shrink: 0;
	}

	&__order {
		min-width: 1.5em;
		text-align: center;
		color: var(--color-text-lighter);
		font-size: 0.85em;
		flex-shrink: 0;
	}

	&__name {
		flex: 1;
		display: flex;
		align-items: center;
		gap: calc(var(--default-grid-baseline) * 0.5);
		overflow: hidden;
	}

	&__title {
		flex: 1;
		min-width: 0;
		padding: 0;
		border: none;
		background: none;
		font: inherit;
		color: inherit;
		text-align: start;
		cursor: pointer;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;

		&:focus-visible {
			outline: 2px solid var(--color-primary-element);
			outline-offset: 2px;
			border-radius: var(--border-radius);
		}
	}

	&__broken {
		color: var(--color-warning);
		flex-shrink: 0;
	}

	&__target {
		font-size: 0.75em;
		padding: 2px 6px;
		border-radius: var(--border-radius-pill);
		flex-shrink: 0;
		font-weight: 600;
		text-transform: uppercase;

		&--row {
			background-color: var(--color-primary-element-light);
			color: var(--color-primary-element);
		}

		&--col {
			background-color: var(--color-background-dark);
			color: var(--color-text-maxcontrast);
		}
	}
}
</style>
