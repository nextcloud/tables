<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="condition-group-builder">
		<template v-for="(group, groupIdx) in mutableGroups" :key="keyFor(group)">
			<div v-if="groupIdx > 0" class="condition-group-builder__or-separator">
				{{ t('tables', 'OR') }}
			</div>
			<div class="condition-group-builder__group"
				role="group"
				:aria-label="groupLabel(groupIdx)">
				<div class="condition-group-builder__group-header" aria-hidden="true">
					{{ t('tables', '... that meet all of the following conditions') }}
				</div>
				<FilterEntry v-for="(condition, condIdx) in group.conditions"
					:key="keyFor(condition)"
					v-model:filter-entry="group.conditions[condIdx]"
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
		</template>

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

const listKeys = new WeakMap()
let nextListKey = 0

/**
 * @param {object} item a group or a condition held in mutableGroups
 * @return {number} a key that stays with this object for as long as it exists
 */
function keyFor(item) {
	if (!listKeys.has(item)) {
		listKeys.set(item, ++nextListKey)
	}
	return listKeys.get(item)
}

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
			syncingFromProp: false,
		}
	},

	watch: {
		mutableGroups: {
			handler() {
				if (!this.syncingFromProp) {
					this.emitUpdate()
				}
			},
			deep: true,
		},
		conditionSet: {
			handler(val) {
				const incoming = this.cloneGroups(val?.groups ?? [])
				if (JSON.stringify(incoming) === JSON.stringify(this.mutableGroups)) {
					return
				}
				this.syncingFromProp = true
				this.mutableGroups = incoming
				this.$nextTick(() => {
					this.syncingFromProp = false
				})
			},
			deep: true,
		},
	},

	methods: {
		keyFor,

		cloneGroups(groups) {
			return groups.map(g => ({
				conditions: g.conditions.map(c => ({ ...c })),
			}))
		},

		getColumnType(columnId) {
			return this.columns.find(c => c.id === columnId)?.type ?? ''
		},

		groupLabel(groupIdx) {
			return t('tables', 'Condition group {position} of {count}', {
				position: groupIdx + 1,
				count: this.mutableGroups.length,
			})
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
			if (this.mutableGroups[groupIdx].conditions.length === 0 && this.mutableGroups.length > 1) {
				this.mutableGroups.splice(groupIdx, 1)
			}
			this.emitUpdate()
		},

		addGroup() {
			this.mutableGroups.push({ conditions: [{ columnId: null, operator: null, value: '', columnType: '' }] })
			this.emitUpdate()
		},

		emitUpdate() {
			for (const group of this.mutableGroups) {
				for (const condition of group.conditions) {
					const columnType = condition.columnId != null ? this.getColumnType(condition.columnId) : ''
					if (columnType !== '' && condition.columnType !== columnType) {
						condition.columnType = columnType
					}
				}
			}
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
