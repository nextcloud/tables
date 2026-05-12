<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcPopover v-if="ruleSetsForColumn.length > 0" trigger="hover focus">
		<template #trigger>
			<span class="formatting-column-popover__dot"
				:aria-label="t('tables', 'This column has formatting rules')" />
		</template>

		<div class="formatting-column-popover">
			<div v-for="rs in ruleSetsForColumn"
				:key="rs.id"
				class="formatting-column-popover__ruleset">
				<div class="formatting-column-popover__ruleset-header">
					<AlertCircle v-if="rs.broken"
						:size="14"
						class="formatting-column-popover__broken"
						:title="t('tables', 'Broken rule set')" />
					<span class="formatting-column-popover__ruleset-name">{{ rs.title }}</span>
					<NcCheckboxRadioSwitch :checked="rs.enabled"
						:disabled="rs.broken"
						@update:checked="onToggleRuleSet(rs, $event)" />
				</div>

				<div v-for="rule in rs.rules"
					:key="rule.id"
					class="formatting-column-popover__rule">
					<AlertCircle v-if="rule.broken"
						:size="12"
						class="formatting-column-popover__broken"
						:title="t('tables', 'Broken rule')" />
					<span class="formatting-column-popover__rule-name">{{ rule.title || t('tables', 'Unnamed rule') }}</span>
					<NcCheckboxRadioSwitch :checked="rule.enabled"
						:disabled="rule.broken"
						@update:checked="onToggleRule(rs, rule, $event)" />
				</div>
			</div>

			<div class="formatting-column-popover__footer">
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
import AlertCircle from 'vue-material-design-icons/AlertCircle.vue'
import { useFormattingStore } from '../../store/formatting.js'

export default {
	name: 'FormattingColumnPopover',

	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		NcPopover,
		AlertCircle,
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
	},

	emits: ['open-manager'],

	setup() {
		return { formattingStore: useFormattingStore() }
	},

	computed: {
		ruleSetsForColumn() {
			return this.formattingStore.ruleSets.filter(rs =>
				(rs.targetType === 'column' && rs.targetCol === this.columnId)
				|| rs.targetType === 'row',
			)
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
			this.$emit('open-manager')
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
		display: inline-block;
		width: 8px;
		height: 8px;
		border-radius: 50%;
		background-color: var(--color-primary-element);
		cursor: pointer;
	}
}
</style>
