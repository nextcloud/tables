<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<RowFormWrapper :title="column.title" :mandatory="isMandatory" :description="column.description" :width="2" :readonly="column.readonly">
		<NcSelect
			v-model="localValue"
			:options="relationOptions"
			:clearable="!isMandatory"
			:reduce="(option) => option.id"
			:loading="loading"
			:multiple="allowMultiple"
			:close-on-select="!allowMultiple"
			:aria-label-combobox="t('tables', 'Select relation value')"
			:label-outside="true"
			:disabled="column.readonly || isCardView"
			data-cy="relationRowSelect" />
	</RowFormWrapper>
</template>

<script>
import { NcSelect } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'
import { useDataStore } from '../../../../../store/data.js'
import { useTablesStore } from '../../../../../store/store.js'
import { mapState, mapActions } from 'pinia'
import RowFormWrapper from './RowFormWrapper.vue'

export default {
	name: 'RelationForm',
	components: {
		NcSelect,
		RowFormWrapper,
	},
	props: {
		column: {
			type: Object,
			required: true,
		},
		value: {
			type: [String, Number, Array],
			default: null,
		},
		isCardView: {
			type: Boolean,
			default: false,
		},
	},
	emits: [
		'update:value',
	],
	computed: {
		...mapState(useTablesStore, ['activeTable', 'activeView']),
		allowMultiple() {
			return !!this.column.customSettings?.allowMultiple
		},
		isMandatory() {
			return !!(this.column?.viewColumnInformation?.mandatory ?? this.column?.mandatory)
		},
		loading() {
			const dataStore = useDataStore()
			const elementId = this.activeView?.id ?? this.activeTable?.id ?? this.column?.tableId
			if (elementId) {
				return dataStore.getRelationsLoading(!!this.activeView, elementId)
			}
			return false
		},
		relationOptions() {
			const dataStore = useDataStore()
			const columnRelations = dataStore.getRelations(this.column?.id)
			return Object.values(columnRelations).map(option => ({
				...option,
				id: parseInt(option.id),
			}))
		},
		localValue: {
			get() {
				const ids = this.normalizeIds(this.value)
				if (this.allowMultiple) {
					return ids
				}
				return ids.length > 0 ? ids[0] : null
			},
			set(value) {
				let ids = this.normalizeIds(value)
				if (!this.allowMultiple) {
					ids = ids.slice(0, 1)
				}
				// Always persist as an array (multi-row cell storage)
				this.$emit('update:value', ids)
			},
		},
	},
	async mounted() {
		const viewId = this.activeView?.id ?? null
		const tableId = this.activeTable?.id ?? this.column?.tableId ?? null
		if (viewId || tableId) {
			await this.loadRelationsFromBE({
				tableId,
				viewId,
				force: true,
			})
		}
	},
	methods: {
		t,
		...mapActions(useDataStore, ['loadRelationsFromBE']),
		normalizeIds(value) {
			if (value === null || value === undefined || value === '') {
				return []
			}
			const list = Array.isArray(value) ? value : [value]
			return list
				.map(id => parseInt(id))
				.filter(id => !Number.isNaN(id))
		},
	},
}
</script>

<style lang="scss" scoped>
.relation-form {
	width: 100%;
}
</style>
