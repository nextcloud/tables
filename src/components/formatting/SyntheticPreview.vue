<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div v-if="previewRows.length > 0" class="synthetic-preview">
		<div class="synthetic-preview__label">
			{{ t('tables', 'Preview') }}
		</div>
		<table class="synthetic-preview__table">
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
						<span v-if="row.matches" class="synthetic-preview__match">✓</span>
						<span v-else class="synthetic-preview__no-match">✗</span>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
</template>

<script>
import { toCSS } from '../../store/formatting.js'

function generatePreviewValue(columnType, operator, value) {
	switch (operator) {
	case 'isEmpty':
	case 'isNotEmpty':
		return operator === 'isEmpty' ? null : 'example'
	case 'isTrue':
	case 'isFalse':
		return operator === 'isTrue' ? true : false
	case 'eq':    return value
	case 'neq':   return value === 'a' ? 'b' : 'a'
	case 'gt':    return Number(value) + 1
	case 'lt':    return Number(value) - 1
	case 'gte':   return Number(value)
	case 'lte':   return Number(value)
	case 'between': return value
	case 'contains':   return value ? String(value) + ' extra' : 'example'
	case 'startsWith': return value ? String(value) + '_suffix' : 'example'
	case 'before': return new Date(new Date(value).getTime() - 86400000).toISOString().slice(0, 10)
	case 'after':  return new Date(new Date(value).getTime() + 86400000).toISOString().slice(0, 10)
	case 'in':     return Array.isArray(value) && value.length > 0 ? value[0] : 'example'
	default:       return 'example'
	}
}

function generateNonMatchValue(columnType, operator, value) {
	switch (operator) {
	case 'isEmpty':    return 'non-empty'
	case 'isNotEmpty': return null
	case 'isTrue':     return false
	case 'isFalse':    return true
	case 'eq':    return value === 'a' ? 'b' : String(value) + '_other'
	case 'neq':   return value
	case 'gt':    return Number(value) - 1
	case 'lt':    return Number(value) + 1
	case 'gte':   return Number(value) - 1
	case 'lte':   return Number(value) + 1
	case 'between': return Number(Array.isArray(value) ? value[0] : value) - 1
	case 'contains':   return 'unrelated'
	case 'startsWith': return 'different'
	case 'before': return new Date(new Date(value).getTime() + 86400000).toISOString().slice(0, 10)
	case 'after':  return new Date(new Date(value).getTime() - 86400000).toISOString().slice(0, 10)
	case 'in':     return 'not_in_list'
	default:       return 'other'
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

	computed: {
		appliedStyle() {
			return toCSS(this.format)
		},

		firstCondition() {
			return this.conditionSet?.groups?.[0]?.conditions?.[0] ?? null
		},

		previewRows() {
			const cond = this.firstCondition
			if (!cond || !cond.columnId || !cond.operator) return []

			const col = this.columns.find(c => c.id === cond.columnId)
			const colTitle = col?.title ?? t('tables', 'Value')

			const matchVal = generatePreviewValue(cond.columnType, cond.operator, cond.value ?? cond.values)
			const noMatchVal = generateNonMatchValue(cond.columnType, cond.operator, cond.value ?? cond.values)

			const formatCell = v => {
				if (v === null || v === undefined) return t('tables', '(empty)')
				if (typeof v === 'boolean') return v ? t('tables', 'true') : t('tables', 'false')
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
