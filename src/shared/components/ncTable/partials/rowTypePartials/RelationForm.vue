<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<RowFormWrapper :title="column.title" :mandatory="column.mandatory" :description="column.description" :width="2" :readonly="column.readonly">
		<NcSelect
			v-model="localValue"
			:options="relationOptions"
			:clearable="!column.mandatory"
			:reduce="(option) => option.id"
			:loading="loading"
			:aria-label-combobox="t('tables', 'Select relation value')"
			:label-outside="true"
			:disabled="column.readonly || isCardView" />
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
			type: [String, Number],
			default: null,
		},
		isCardView: {
			type: Boolean,
			default: false,
		},
	},
	computed: {
		...mapState(useTablesStore, ['activeTable', 'activeView']),
		loading() {
			const dataStore = useDataStore()
			const activeElement = this.activeView || this.activeTable
			if (activeElement) {
				return dataStore.getRelationsLoading(!!this.activeView, activeElement.id)
			}
			return false
		},
		relationOptions() {
			const dataStore = useDataStore()
			const activeElement = this.activeView || this.activeTable
			if (activeElement) {
				const columnRelations = dataStore.getRelations(this.column.id)
				return Object.values(columnRelations)
			}
			return []
		},
		localValue: {
			get() {
				return this.value ? parseInt(this.value) : null
			},
			set(value) {
				this.$emit('update:value', value)
			},
		},
	},
	async mounted() {
		const activeElement = this.activeView || this.activeTable
		if (activeElement) {
			await this.loadRelationsFromBE({
				tableId: this.activeTable?.id,
				viewId: this.activeView?.id,
			})
		}
	},
	methods: {
		...mapActions(useDataStore, ['loadRelationsFromBE']),
		t,
	},
}
</script>

<style lang="scss" scoped>
.relation-form {
	width: 100%;
}
</style>
