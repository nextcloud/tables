<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div :class="['rule-set-list-item', { 'rule-set-list-item--active': active }]"
		:draggable="true"
		@click="$emit('click')"
		@dragstart="$emit('dragstart', $event)"
		@dragover.prevent="$emit('dragover', $event)"
		@dragend="$emit('dragend')">
		<NcButton type="tertiary-no-background"
			class="rule-set-list-item__drag-handle"
			:aria-label="t('tables', 'Drag to reorder')"
			@click.stop>
			<template #icon>
				<DragHorizontalVariant :size="18" />
			</template>
		</NcButton>

		<span class="rule-set-list-item__order">{{ index + 1 }}</span>

		<span class="rule-set-list-item__name">
			<AlertCircle v-if="ruleSet.broken"
				:size="16"
				class="rule-set-list-item__broken"
				:title="t('tables', 'This rule set references a deleted or changed column')" />
			{{ ruleSet.title || t('tables', 'Untitled') }}
		</span>

		<span :class="['rule-set-list-item__target', targetBadgeClass]">
			{{ targetLabel }}
		</span>

		<NcCheckboxRadioSwitch :checked="ruleSet.enabled"
			:disabled="ruleSet.broken"
			@update:checked="$emit('toggle-enabled', { id: ruleSet.id, enabled: $event })"
			@click.stop />

		<NcButton type="tertiary-no-background"
			:aria-label="t('tables', 'Delete rule set')"
			@click.stop="$emit('delete', ruleSet.id)">
			<template #icon>
				<Delete :size="18" />
			</template>
		</NcButton>
	</div>
</template>

<script>
import { NcButton, NcCheckboxRadioSwitch } from '@nextcloud/vue'
import DragHorizontalVariant from 'vue-material-design-icons/DragHorizontalVariant.vue'
import AlertCircle from 'vue-material-design-icons/AlertCircle.vue'
import Delete from 'vue-material-design-icons/Delete.vue'

export default {
	name: 'RuleSetListItem',

	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		DragHorizontalVariant,
		AlertCircle,
		Delete,
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
	},

	emits: ['click', 'dragstart', 'dragover', 'dragend', 'toggle-enabled', 'delete'],

	computed: {
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
		text-overflow: ellipsis;
		white-space: nowrap;
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
