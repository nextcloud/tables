<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="row space-B">
		<div class="col-4">
			{{ t('tables', 'Share with accounts') }}
		</div>
		<NcSelect v-model="preExistingSharees" style="width: 100%;" :loading="loading" :options="options"
			:placeholder="getPlaceholder()"
			:searchable="true" :get-option-key="(option) => option.key"
			label="displayName"
			:aria-label-combobox="getPlaceholder()" :user-select="true"
			:close-on-select="false"
			:multiple="true"
			@search="asyncFind" @input="addShare">
			<template #noResult>
				{{ noResultText }}
			</template>
		</NcSelect>
	</div>
</template>

<script>
import { NcSelect } from '@nextcloud/vue'
import formatting from '../../../shared/mixins/formatting.js'
import searchUserGroup from '../../../shared/mixins/searchUserGroup.js'
import '@nextcloud/dialogs/style.css'

export default {

	components: {
		NcSelect,
	},

	mixins: [formatting, searchUserGroup],

	props: {
		receivers: {
			type: Array,
			default: () => ([]),
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
			preExistingSharees: [...this.receivers],
			localSharees: this.receivers.map(obj => ({
				id: obj.id,
				displayName: obj.displayName,
				icon: obj.icon,
				isUser: obj.isUser,
				key: obj.key,
			})),
		}
	},

	computed: {
		localValue: {
			get() {
				return this.localSharees
			},
			set(v) {
				this.localSharees = v.map(obj => ({
					id: obj.id,
					displayName: obj.displayName,
					icon: obj.icon,
					isUser: obj.isUser,
					key: obj.key,
				}))
				this.$emit('update', v)
			},
		},
	},

	methods: {
		addShare(selectedItem) {
			this.localValue = selectedItem
		},

		filterOutUnwantedItems(items) {
			// Filter out existing items
			const filteredItems = []
			items.forEach((item) => {
				const alreadyExists = this.localSharees.find((localItem) => item.id === localItem.id && item.isUser === localItem.isUser)
				if (!alreadyExists) {
					filteredItems.push(item)
				}
			})
			// Filter out current user
			return filteredItems.filter((item) => !(item.isUser && item.id === this.currentUserId))
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

<style lang="scss" scoped>
.multiselect {
	width: 100% !important;
	max-width: 100% !important;
}
</style>
