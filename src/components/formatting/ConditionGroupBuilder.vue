<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="condition-group-builder">
		<div v-for="(group, groupIdx) in mutableGroups"
			:key="groupIdx"
			class="condition-group-builder__group">
			<div class="condition-group-builder__group-header">
				{{ t('tables', '... that meet all of the following conditions') }}
			</div>
			<FilterEntry v-for="(condition, condIdx) in group.conditions"
				:key="condIdx"
				:filter-entry.sync="group.conditions[condIdx]"
				:columns="columns"
				@delete-filter="deleteCondition(groupIdx, condIdx)"
				@update:filter-entry="onConditionUpdate(groupIdx, condIdx, $event)" />
			<NcButton type="tertiary"
				:aria-label="t('tables', 'Add condition')"
				@click="addCondition(groupIdx)">
				<template #icon>
					<Plus :size="20" />
				</template>
				{{ t('tables', 'Add condition') }}
			</NcButton>
		</div>

		<div v-if="mutableGroups.length > 1"
			v-for="(_, idx) in mutableGroups.slice(1)"
			:key="'or-' + idx"
			class="condition-group-builder__or-separator">
			{{ t('tables', 'OR') }}
		</div>

		<NcButton type="tertiary"
			:aria-label="t('tables', 'Add condition group')"
			@click="addGroup">
			<template #icon>
				<Plus :size="20" />
			</template>
			{{ t('tables', 'Add condition group') }}
		</NcButton>
	</div>
</template>

<script>
import { NcButton } from '@nextcloud/vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import FilterEntry from '../../modules/main/partials/editViewPartials/filter/FilterEntry.vue'

export default {
	name: 'ConditionGroupBuilder',

	components: {
		NcButton,
		Plus,
		FilterEntry,
	},

	props: {
		conditionSet: {
			type: Object,
			default: () => ({ groups: [] }),
		},
		columns: {
			type: Array,
			default: () => [],
		},
	},

	emits: ['update:conditionSet'],

	data() {
		return {
			mutableGroups: this.cloneGroups(this.conditionSet?.groups ?? []),
		}
	},

	watch: {
		conditionSet: {
			handler(val) {
				this.mutableGroups = this.cloneGroups(val?.groups ?? [])
			},
			deep: true,
		},
	},

	methods: {
		cloneGroups(groups) {
			return groups.map(g => ({
				conditions: g.conditions.map(c => ({ ...c })),
			}))
		},

		getColumnType(columnId) {
			return this.columns.find(c => c.id === columnId)?.type ?? ''
		},

		onConditionUpdate(groupIdx, condIdx, updated) {
			const enriched = { ...updated, columnType: this.getColumnType(updated.columnId) }
			this.mutableGroups[groupIdx].conditions.splice(condIdx, 1, enriched)
			this.emitUpdate()
		},

		addCondition(groupIdx) {
			this.mutableGroups[groupIdx].conditions.push({ columnId: null, operator: null, value: '', columnType: '' })
			this.emitUpdate()
		},

		deleteCondition(groupIdx, condIdx) {
			this.mutableGroups[groupIdx].conditions.splice(condIdx, 1)
			if (this.mutableGroups[groupIdx].conditions.length === 0) {
				this.mutableGroups.splice(groupIdx, 1)
			}
			this.emitUpdate()
		},

		addGroup() {
			this.mutableGroups.push({ conditions: [{ columnId: null, operator: null, value: '', columnType: '' }] })
			this.emitUpdate()
		},

		emitUpdate() {
			this.$emit('update:conditionSet', { groups: this.cloneGroups(this.mutableGroups) })
		},
	},
}
</script>

<style lang="scss" scoped>
.condition-group-builder {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 2);

	&__group {
		border-inline-start: 4px solid var(--color-primary);
		padding-inline-start: calc(var(--default-grid-baseline) * 2);
		display: flex;
		flex-direction: column;
		gap: var(--default-grid-baseline);
	}

	&__group-header {
		color: var(--color-text-lighter);
		font-size: 0.85em;
	}

	&__or-separator {
		text-align: center;
		color: var(--color-text-lighter);
		font-weight: 600;
		font-size: 0.9em;
	}
}
</style>
