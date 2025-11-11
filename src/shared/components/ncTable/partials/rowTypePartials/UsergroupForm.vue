<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<RowFormWrapper :title="column.title" :mandatory="isMandatory(column)" :description="column.description" :width="2">
		<NcSelect v-model="localValue" style="width: 100%;" :loading="loading" :options="options"
			:placeholder="getPlaceholder()" :searchable="true" :get-option-key="(option) => option.key"
			label="displayName" :aria-label-combobox="getPlaceholder()"
			:user-select="true"
			:disabled="column.viewColumnInformation?.readonly"
			:close-on-select="false" :multiple="column.usergroupMultipleItems" data-cy="usergroupRowSelect"
			@search="asyncFind" @input="addItem">
			<template #noResult>
				{{ noResultText }}
			</template>
		</NcSelect>
	</RowFormWrapper>
</template>

<script>
import { NcSelect } from '@nextcloud/vue'
import RowFormWrapper from './RowFormWrapper.vue'
import searchUserGroup from '../../../../mixins/searchUserGroup.js'
import ShareTypes from '../../../../mixins/shareTypesMixin.js'
import rowHelper from '../../../../components/ncTable/mixins/rowHelper.js'
export default {
	components: {
		RowFormWrapper,
		NcSelect,
	},
	mixins: [ShareTypes, searchUserGroup, rowHelper],
	props: {
		column: {
			type: Object,
			default: null,
		},
		value: {
			type: Array,
			default: () => ([]),
		},
	},
	data() {
		return {
			selectUsers: this.column.usergroupSelectUsers,
			selectGroups: this.column.usergroupSelectGroups,
			internalLocalValue: [],
		}
	},
	computed: {
		localValue: {
			get() {
				return this.internalLocalValue
			},
			set(v) {
				let formattedValue = null
				if (Array.isArray(v)) {
					formattedValue = v
				} else {
					formattedValue = [v]
				}
				this.internalLocalValue = formattedValue
				this.$emit('update:value', formattedValue)
			},
		},
	},
	created() {
		// Update the circles-related data once the component is created
		// Doing this in data() doesn't work due to timing issues,
		// since the data() function runs before the capabilities are fully initialized
		this.selectCircles = this.isCirclesEnabled ? this.column.usergroupSelectTeams : false

		let initialValue = this.value
		if (!initialValue || (Array.isArray(initialValue) && initialValue.length === 0)) {
			initialValue = this.column.usergroupDefault || []
		}

		const formatted = (Array.isArray(initialValue) ? initialValue : []).map(item => ({
			...(item ?? {}),
			// Adding a unique key such that removing items works correctly
			key: this.getKeyPrefix(item?.type) + (item?.id ?? ''),
		}))
		this.internalLocalValue = formatted

		if (formatted.length > 0) {
			this.$emit('update:value', formatted)
		}
	},
	methods: {
		addItem(selectedItem) {
			if (selectedItem) {
				this.localValue = selectedItem
			} else {
				this.localValue = []
			}
		},

		getKeyPrefix(type) {
			switch (type) {
			case 0: return 'users-'
			case 1: return 'groups-'
			case 2: return 'circles-'
			default: return 'unknown-'
			}
		},

		filterOutUnwantedItems(list) {
			return list
		},

		formatResult(autocompleteResult) {
			return {
				id: autocompleteResult.id,
				type: this.getType(autocompleteResult.source),
				key: autocompleteResult.source + '-' + autocompleteResult.id,
				displayName: autocompleteResult.label,
			}
		},

	},
}
</script>
