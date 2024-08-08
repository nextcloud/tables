<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="row space-B">
		<NcSelect v-model="value" style="width: 100%;" :loading="loading" :options="options"
			:placeholder="getPlaceholder()" :searchable="true" :get-option-key="(option) => option.key"
			label="displayName" :aria-label-combobox="getPlaceholder()" :user-select="true" @search="asyncFind"
			@input="addTransfer">
			<template #noResult>
				{{ noResultText }}
			</template>
		</NcSelect>
	</div>
</template>

<script>
import { NcSelect } from '@nextcloud/vue'
import formatting from '../../mixins/formatting.js'
import '@nextcloud/dialogs/style.css'
import searchUserGroup from '../../mixins/searchUserGroup.js'

export default {

	components: {
		NcSelect,
	},

	mixins: [formatting, searchUserGroup],

	props: {
		selectedUserId: {
			type: String,
			default: '',
		},
		selectUsers: {
			type: Boolean,
			default: true,
		},
		selectGroups: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			value: '',
		}
	},

	computed: {
		localValue: {
			get() {
				return this.selectedUserId
			},
			set(v) {
				this.$emit('update:selectedUserId', v?.id)
			},
		},
	},

	methods: {
		addTransfer(selectedItem) {
			this.localValue = selectedItem
		},

		filterOutUnwantedItems(items) {
			return items.filter((item) => !(item.isUser && item.id === this.currentUserId))
		},

		formatResult(autocompleteResult) {
			return {
				id: autocompleteResult.id,
				displayName: autocompleteResult.label,
				icon: autocompleteResult.icon,
				isUser: autocompleteResult.source.startsWith('users'),
				key: autocompleteResult.source + '-' + autocompleteResult.id,
			}
		},
	},
}
</script>
