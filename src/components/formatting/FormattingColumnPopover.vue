<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcPopover v-if="ruleSetsForColumn.length > 0" :triggers="['click']" popup-role="dialog">
		<template #trigger="{ attrs }">
			<button v-bind="attrs"
				type="button"
				class="formatting-column-popover__dot"
				:aria-label="dotLabel" />
		</template>

		<div class="formatting-column-popover"
			role="dialog"
			:aria-label="dotLabel">
			<div v-for="rs in ruleSetsForColumn"
				:key="rs.id"
				class="formatting-column-popover__ruleset">
				<div class="formatting-column-popover__ruleset-header">
					<AlertCircleOutline v-if="rs.broken"
						:size="14"
						class="formatting-column-popover__broken"
						role="img"
						:aria-label="t('tables', 'Broken rule set')"
						:title="t('tables', 'Broken rule set')" />
					<span class="formatting-column-popover__ruleset-name">{{ rs.title }}</span>
					<NcCheckboxRadioSwitch :model-value="rs.enabled"
						:disabled="rs.broken || !canManage"
						:aria-label="t('tables', 'Rule set enabled')"
						@update:model-value="onToggleRuleSet(rs, $event)" />
				</div>

				<div v-for="rule in rs.rules"
					:key="rule.id"
					class="formatting-column-popover__rule">
					<AlertCircleOutline v-if="rule.broken"
						:size="12"
						class="formatting-column-popover__broken"
						role="img"
						:aria-label="t('tables', 'Broken rule')"
						:title="t('tables', 'Broken rule')" />
					<span class="formatting-column-popover__rule-name">{{ rule.title || t('tables', 'Unnamed rule') }}</span>
					<NcCheckboxRadioSwitch :model-value="rule.enabled"
						:disabled="rule.broken || !canManage"
						:aria-label="t('tables', 'Rule enabled')"
						@update:model-value="onToggleRule(rs, rule, $event)" />
				</div>
			</div>

			<div v-if="canManage" class="formatting-column-popover__footer">
				<NcButton type="tertiary"
					:aria-label="t('tables', 'Manage all formatting rules')"
					@click="openManager">
					{{ t('tables', 'Manage all rules →') }}
				</NcButton>
			</div>
		</div>
	</NcPopover>
</template>

<script>
import { NcButton, NcCheckboxRadioSwitch, NcPopover } from '@nextcloud/vue'
import AlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import { translate as t } from '@nextcloud/l10n'
import { useFormattingStore } from '../../store/formatting.js'

export default {
	name: 'FormattingColumnPopover',

	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		NcPopover,
		AlertCircleOutline,
	},

	props: {
		columnId: {
			type: Number,
			required: true,
		},
		viewId: {
			type: Number,
			required: true,
		},
		canManage: {
			type: Boolean,
			default: false,
		},
	},

	setup() {
		return { formattingStore: useFormattingStore() }
	},

	computed: {
		ruleSetsForColumn() {
			if (!this.formattingStore.appliesTo(this.viewId)) {
				return []
			}
			return this.formattingStore.ruleSets.filter(rs =>
				(rs.targetType === 'column' && rs.targetCol === this.columnId)
				|| rs.targetType === 'row',
			)
		},

		dotLabel() {
			return this.ruleSetsForColumn.some(rs => rs.targetType === 'column')
				? t('tables', 'This column has formatting rules')
				: t('tables', 'Formatting rules apply to the rows of this table')
		},
	},

	methods: {
		async onToggleRuleSet(rs, enabled) {
			await this.formattingStore.updateRuleSet(this.viewId, rs.id, { ...rs, enabled })
		},

		async onToggleRule(rs, rule, enabled) {
			await this.formattingStore.updateRule(this.viewId, rs.id, rule.id, { ...rule, enabled })
		},

		openManager() {
			this.formattingStore.showFormattingManager = true
		},
	},
}
</script>

<style lang="scss" scoped>
.formatting-column-popover {
	min-width: 220px;
	max-width: 300px;
	padding: calc(var(--default-grid-baseline) * 2);
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 2);

	&__ruleset {
		display: flex;
		flex-direction: column;
		gap: var(--default-grid-baseline);
	}

	&__ruleset-header {
		display: flex;
		align-items: center;
		gap: var(--default-grid-baseline);
		font-weight: 600;
	}

	&__ruleset-name,
	&__rule-name {
		flex: 1;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	&__rule {
		display: flex;
		align-items: center;
		gap: var(--default-grid-baseline);
		padding-inline-start: calc(var(--default-grid-baseline) * 3);
		font-size: 0.9em;
		color: var(--color-text-maxcontrast);
	}

	&__broken {
		color: var(--color-warning);
		flex-shrink: 0;
	}

	&__footer {
		border-block-start: 1px solid var(--color-border);
		padding-block-start: var(--default-grid-baseline);
	}

	&__dot {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 24px;
		height: 24px;
		padding: 0;
		border: none;
		border-radius: 50%;
		background: none;
		cursor: pointer;

		&::before {
			content: '';
			width: 8px;
			height: 8px;
			border-radius: 50%;
			background-color: var(--color-primary-element);
		}

		&:focus-visible {
			outline: 2px solid var(--color-primary-element);
			outline-offset: 2px;
		}
	}
}
</style>
