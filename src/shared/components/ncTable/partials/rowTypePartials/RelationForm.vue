<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<RowFormWrapper :title="column.title" :mandatory="isMandatory(column)" :description="column.description">
		<div class="relation-form">
			<div v-if="linkedRows.length > 0" class="relation-chips">
				<span v-for="row in linkedRows" :key="row.id" class="relation-chip">
					{{ getDisplayValue(row) }}
					<button class="relation-chip-remove" @click="removeLink(row.id)">
						&times;
					</button>
				</span>
			</div>
			<NcSelect
				v-if="canAddMore"
				:value="null"
				:options="searchResults"
				:loading="loading"
				label="label"
				track-by="id"
				:placeholder="t('tables', 'Search rows to link...')"
				:multiple="false"
				@search="onSearch"
				@input="addLink" />
			<div v-if="relationType" class="relation-type-badge">
				{{ relationType }}
			</div>
		</div>
	</RowFormWrapper>
</template>

<script>
import { NcSelect } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'
import RowFormWrapper from './RowFormWrapper.vue'
import rowHelper from '../../../../components/ncTable/mixins/rowHelper.js'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'RelationForm',
	components: { NcSelect, RowFormWrapper },
	mixins: [rowHelper],
	props: {
		column: {
			type: Object,
			default: null,
		},
		value: {
			type: [String, Array, Number],
			default: null,
		},
	},
	data() {
		return {
			loading: false,
			linkedRows: [],
			allTargetRows: [],
			searchResults: [],
			linkedRowIds: [],
		}
	},
	computed: {
		canAddMore() {
			const type = this.column?.relationType || 'many-to-many'
			if (type === 'one-to-one' && this.linkedRows.length >= 1) return false
			return true
		},
		relationType() {
			const type = this.column?.relationType
			if (!type) return ''
			return type.split('-').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ')
		},
		displayColumnId() {
			return this.column?.relationDisplayColumnId || null
		},
	},
	async mounted() {
		await this.fetchTargetRows()
		await this.loadLinkedRows()
	},
	methods: {
		t,
		async fetchTargetRows() {
			if (!this.column?.relationTableId) return
			this.loading = true
			try {
				const res = await axios.get(generateUrl('/apps/tables/row/table/' + this.column.relationTableId))
				this.allTargetRows = res.data || []
			} catch (e) {
				console.error('Failed to fetch target rows', e)
			}
			this.loading = false
		},
		async loadLinkedRows() {
			// Parse linked IDs from value (which may come as JSON array from the relation API)
			this.linkedRowIds = this.parseValue()
			this.linkedRows = this.allTargetRows.filter(r => this.linkedRowIds.includes(r.id))
			this.updateSearchResults('')
		},
		parseValue() {
			if (!this.value) return []
			if (Array.isArray(this.value)) return this.value
			try {
				const parsed = JSON.parse(this.value)
				if (Array.isArray(parsed)) return parsed.map(Number)
				if (typeof parsed === 'number') return [parsed]
				// Try double-parsed JSON
				if (typeof parsed === 'string') {
					const inner = JSON.parse(parsed)
					if (Array.isArray(inner)) return inner.map(Number)
				}
				return []
			} catch {
				return []
			}
		},
		getDisplayValue(row) {
			if (!row?.data || row.data.length === 0) return `Row ${row.id}`
			// Find the display column value
			let cell = null
			if (this.displayColumnId) {
				cell = row.data.find(d => d.columnId === this.displayColumnId)
			}
			if (!cell) {
				cell = row.data[0]
			}
			if (!cell?.value) return `Row ${row.id}`
			try {
				const parsed = JSON.parse(cell.value)
				if (typeof parsed === 'string') return parsed
				return String(parsed)
			} catch {
				return String(cell.value)
			}
		},
		async addLink(row) {
			if (!row) return
			const ids = [...this.linkedRowIds]
			if (this.column?.relationType === 'one-to-one') {
				ids.length = 0
			}
			if (!ids.includes(row.id)) {
				ids.push(row.id)
			}
			this.linkedRowIds = ids
			this.linkedRows = this.allTargetRows.filter(r => ids.includes(r.id))
			this.updateSearchResults('')
			this.$emit('update:value', JSON.stringify(ids))
		},
		removeLink(rowId) {
			const ids = this.linkedRowIds.filter(id => id !== rowId)
			this.linkedRowIds = ids
			this.linkedRows = this.allTargetRows.filter(r => ids.includes(r.id))
			this.updateSearchResults('')
			this.$emit('update:value', JSON.stringify(ids))
		},
		onSearch(query) {
			this.updateSearchResults(query)
		},
		updateSearchResults(query) {
			this.searchResults = this.allTargetRows
				.filter(r => !this.linkedRowIds.includes(r.id))
				.filter(r => !query || this.getDisplayValue(r).toLowerCase().includes(query.toLowerCase()))
				.map(r => ({ id: r.id, label: this.getDisplayValue(r) }))
		},
	},
}
</script>

<style lang="scss" scoped>
.relation-form {
	width: 100%;
}

.relation-chips {
	display: flex;
	flex-wrap: wrap;
	gap: 4px;
	margin-bottom: 8px;
}

.relation-chip {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	padding: 2px 10px;
	border-radius: 12px;
	background: var(--color-primary-element-light);
	color: var(--color-primary-element);
	font-size: 13px;
	font-weight: 500;
}

.relation-chip-remove {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 16px;
	height: 16px;
	border: none;
	background: none;
	cursor: pointer;
	font-size: 14px;
	color: var(--color-primary-element);
	padding: 0;
	line-height: 1;
	border-radius: 50%;

	&:hover {
		background: var(--color-primary-element);
		color: var(--color-primary-element-text);
	}
}

.relation-type-badge {
	margin-top: 4px;
	font-size: 11px;
	color: var(--color-text-maxcontrast);
	font-style: italic;
}
</style>
