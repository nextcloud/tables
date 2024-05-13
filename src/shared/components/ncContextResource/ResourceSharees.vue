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
			localSharees: this.receivers.map(userObject => userObject.id),
		}
	},

	computed: {
		localValue: {
			get() {
				return this.localSharees
			},
			set(v) {
				this.localSharees = v.map(userObject => userObject.id)
				this.$emit('update', v)
			},
		},
	},

	methods: {
		addShare(selectedItem) {
			this.localValue = selectedItem
		},

		filterOutUnwantedItems(list) {
			list = this.filterOutCurrentUser(list)
			return this.filterOutSelectedUsers(list)
		},

		filterOutSelectedUsers(list) {
			return list.filter((item) => !(item.isUser && this.localSharees.includes(item.id)))
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
