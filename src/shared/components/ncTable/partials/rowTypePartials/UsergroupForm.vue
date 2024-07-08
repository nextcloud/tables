<template>
	<RowFormWrapper :title="column.title" :mandatory="column.mandatory" :description="column.description" :width="2">
		<NcSelect v-model="localValue" style="width: 100%;" :loading="loading" :options="options"
			:placeholder="getPlaceholder()" :searchable="true" :get-option-key="(option) => option.key"
			label="id" :aria-label-combobox="getPlaceholder()"
			:user-select="true"
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

export default {
	components: {
		RowFormWrapper,
		NcSelect,
	},
	mixins: [ShareTypes, searchUserGroup],
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
		}
	},
	computed: {
		localValue: {
			get() {
				return this.value
			},
			set(v) {
				let formattedValue = null
				if (Array.isArray(v)) {
					formattedValue = v
				} else {
					formattedValue = [v]
				}
				this.$emit('update:value', formattedValue)
			},
		},
	},
	methods: {
		addItem(selectedItem) {
			if (selectedItem) {
				this.localValue = selectedItem
			} else {
				this.localValue = []
			}
		},

		filterOutUnwantedItems(list) {
			return list
		},

		formatResult(autocompleteResult) {
			return {
				id: autocompleteResult.id,
				type: autocompleteResult.source.startsWith('users') ? 0 : 1,
				key: autocompleteResult.id,
			}
		},

	},
}
</script>
