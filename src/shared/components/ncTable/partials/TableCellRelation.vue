<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="relation-cell" @click.stop>
		<div v-if="linkedRows.length > 0" class="relation-chips">
			<span v-for="row in linkedRows"
				:key="row.id"
				class="relation-chip"
				@click="navigateToRow(row)">
				{{ getDisplayValue(row) }}
			</span>
		</div>
		<span v-else class="relation-empty">—</span>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'TableCellRelation',
	props: {
		column: { type: Object, default: null },
		rowId: { type: Number, default: null },
		value: { type: [String, Number, Array], default: null },
		canEdit: { type: Boolean, default: false },
	},
	data() {
		return {
			linkedRows: [],
			targetRows: [],
		}
	},
	computed: {
		parsedValue() {
			if (!this.value) return []
			if (Array.isArray(this.value)) return this.value
			try {
				let parsed = JSON.parse(this.value)
				if (typeof parsed === 'string') parsed = JSON.parse(parsed)
				if (Array.isArray(parsed)) return parsed.map(Number)
				if (typeof parsed === 'number') return [parsed]
				return []
			} catch {
				return []
			}
		},
		displayColumnId() {
			return this.column?.relationDisplayColumnId || null
		},
	},
	watch: {
		value: {
			immediate: true,
			handler() {
				this.loadLinkedRows()
			},
		},
	},
	async mounted() {
		await this.fetchTargetRows()
		this.loadLinkedRows()
	},
	methods: {
		async fetchTargetRows() {
			if (!this.column?.relationTableId) return
			try {
				const res = await axios.get(generateUrl('/apps/tables/row/table/' + this.column.relationTableId))
				this.targetRows = res.data || []
			} catch {
				// Table might not be accessible
			}
		},
		loadLinkedRows() {
			const ids = this.parsedValue
			this.linkedRows = this.targetRows.filter(r => ids.includes(r.id))
		},
		getDisplayValue(row) {
			if (!row?.data || row.data.length === 0) return `Row ${row.id}`
			let cell = null
			if (this.displayColumnId) {
				cell = row.data.find(d => d.columnId === this.displayColumnId)
			}
			if (!cell) cell = row.data[0]
			if (!cell?.value) return `Row ${row.id}`
			try {
				const parsed = JSON.parse(cell.value)
				if (typeof parsed === 'string') return parsed
				return String(parsed)
			} catch {
				return String(cell.value)
			}
		},
		navigateToRow(row) {
			if (this.column?.relationTableId) {
				this.$router.push('/table/' + this.column.relationTableId).catch(() => {})
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.relation-cell {
	display: flex;
	align-items: center;
	min-height: 32px;
	padding: 2px 0;
}

.relation-chips {
	display: flex;
	flex-wrap: wrap;
	gap: 4px;
}

.relation-chip {
	display: inline-flex;
	align-items: center;
	padding: 2px 10px;
	border-radius: 12px;
	background: var(--color-primary-element-light);
	color: var(--color-primary-element);
	font-size: 13px;
	font-weight: 500;
	cursor: pointer;
	transition: background 0.15s;
	max-width: 200px;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;

	&:hover {
		background: var(--color-primary-element);
		color: var(--color-primary-element-text);
	}
}

.relation-empty {
	color: var(--color-text-maxcontrast);
}
</style>
