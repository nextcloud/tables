<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div v-if="previewRows.length > 0" class="synthetic-preview">
		<div :id="captionId" class="synthetic-preview__label">
			{{ t('tables', 'Preview') }}
		</div>
		<table class="synthetic-preview__table" :aria-describedby="captionId">
			<thead class="hidden-visually">
				<tr>
					<th v-for="header in headers" :key="header" scope="col">
						{{ header }}
					</th>
					<th scope="col">
						{{ t('tables', 'Matches the rule') }}
					</th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="(row, idx) in previewRows"
					:key="idx"
					:style="row.matches ? appliedStyle : {}">
					<td v-for="cell in row.cells"
						:key="cell.label"
						class="synthetic-preview__cell"
						:style="row.matches ? appliedStyle : {}">
						{{ cell.value }}
					</td>
					<td class="synthetic-preview__indicator">
						<span v-if="row.matches" class="synthetic-preview__match">{{ t('tables', 'Yes') }}</span>
						<span v-else class="synthetic-preview__no-match">{{ t('tables', 'No') }}</span>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
</template>

<script>
import { toCSS } from '../../store/formatting.js'
import { selectionOptionLabel } from '../../shared/components/ncTable/mixins/selectionReference.js'
import { FilterIds } from '../../shared/components/ncTable/mixins/filter.js'

/**
 * @param {string|number|null} value the condition value
 * @param {number} delta how far to move away from it
 * @return {number|string}
 */
function shiftNumber(value, delta) {
	const parsed = parseFloat(value)
	return Number.isFinite(parsed) ? parsed + delta : 'example'
}

/**
 * @param {string} operator one of FilterIds
 * @param {string|number|boolean|Array|null} value condition value, selection references
 *   already resolved to their label
 * @return {string|number|null} a cell value the condition matches
 */
function generatePreviewValue(operator, value) {
	switch (operator) {
	case FilterIds.IsEmpty: return null
	case FilterIds.IsNotEmpty: return 'example'
	case FilterIds.IsEqual:
	case FilterIds.ContainsItem: return value
	case FilterIds.IsNotEqual: return value === 'a' ? 'b' : 'a'
	case FilterIds.IsGreaterThan: return shiftNumber(value, 1)
	case FilterIds.IsGreaterThanOrEqual: return shiftNumber(value, 0)
	case FilterIds.IsLowerThan: return shiftNumber(value, -1)
	case FilterIds.IsLowerThanOrEqual: return shiftNumber(value, 0)
	case FilterIds.Contains: return value ? 'a ' + String(value) + ' b' : 'example'
	case FilterIds.DoesNotContain: return 'unrelated'
	case FilterIds.BeginsWith: return value ? String(value) + '…' : 'example'
	case FilterIds.EndsWith: return value ? '…' + String(value) : 'example'
	default: return 'example'
	}
}

/**
 * @param {string} operator one of FilterIds
 * @param {string|number|boolean|Array|null} value condition value, selection references
 *   already resolved to their label
 * @return {string|number|null} a cell value the condition does not match
 */
function generateNonMatchValue(operator, value) {
	switch (operator) {
	case FilterIds.IsEmpty: return 'non-empty'
	case FilterIds.IsNotEmpty: return null
	case FilterIds.IsEqual:
	case FilterIds.ContainsItem: return value === 'a' ? 'b' : String(value) + '_other'
	case FilterIds.IsNotEqual: return value
	case FilterIds.IsGreaterThan:
	case FilterIds.IsGreaterThanOrEqual: return shiftNumber(value, -1)
	case FilterIds.IsLowerThan:
	case FilterIds.IsLowerThanOrEqual: return shiftNumber(value, 1)
	case FilterIds.Contains:
	case FilterIds.BeginsWith:
	case FilterIds.EndsWith: return 'unrelated'
	case FilterIds.DoesNotContain: return value
	default: return 'other'
	}
}

export default {
	name: 'SyntheticPreview',

	props: {
		conditionSet: {
			type: Object,
			default: () => ({ groups: [] }),
		},
		format: {
			type: Object,
			default: () => ({}),
		},
		columns: {
			type: Array,
			default: () => [],
		},
	},

	data() {
		return {
			captionId: 'synthetic-preview-' + Math.random().toString(36).slice(2, 10),
		}
	},

	computed: {
		appliedStyle() {
			return toCSS(this.format)
		},

		headers() {
			return this.previewRows[0]?.cells.map(cell => cell.label) ?? []
		},

		firstCondition() {
			return this.conditionSet?.groups?.[0]?.conditions?.[0] ?? null
		},

		previewRows() {
			const cond = this.firstCondition
			if (!cond || !cond.columnId || !cond.operator) return []

			const col = this.columns.find(c => c.id === cond.columnId)
			const colTitle = col?.title ?? t('tables', 'Value')

			const condValue = col ? selectionOptionLabel(cond.value ?? cond.values, col) : (cond.value ?? cond.values)
			const matchVal = generatePreviewValue(cond.operator, condValue)
			const noMatchVal = generateNonMatchValue(cond.operator, condValue)

			const formatCell = v => {
				if (v === null || v === undefined) return t('tables', '(empty)')
				if (typeof v === 'boolean') return v ? t('tables', 'true') : t('tables', 'false')
				if (Array.isArray(v)) return v.map(formatCell).join(', ')
				if (typeof v === 'object') return String(v.label ?? v.id ?? '')
				return String(v)
			}

			return [
				{
					cells: [{ label: colTitle, value: formatCell(matchVal) }],
					matches: true,
				},
				{
					cells: [{ label: colTitle, value: formatCell(noMatchVal) }],
					matches: false,
				},
			]
		},
	},
}
</script>

<style lang="scss" scoped>
.synthetic-preview {
	border: 1px dashed var(--color-border);
	border-radius: var(--border-radius);
	padding: calc(var(--default-grid-baseline) * 1.5);

	&__label {
		font-size: 0.8em;
		color: var(--color-text-lighter);
		text-transform: uppercase;
		font-weight: 600;
		margin-block-end: var(--default-grid-baseline);
	}

	&__table {
		width: 100%;
		border-collapse: collapse;
	}

	&__cell {
		padding: 4px 8px;
		border: 1px solid var(--color-border);
		font-size: 0.9em;
	}

	&__indicator {
		padding: 4px 8px;
		text-align: center;
		font-size: 0.9em;
		border: 1px solid var(--color-border);
		width: 2em;
	}

	&__match {
		color: var(--color-success);
		font-weight: 700;
	}

	&__no-match {
		color: var(--color-text-lighter);
	}
}
</style>
