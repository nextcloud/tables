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
import formatting from '../../../shared/mixins/formatting.js'
import ShareTypes from '../../mixins/shareTypesMixin.js'
import '@nextcloud/dialogs/style.css'
import searchUserGroup from '../../../shared/mixins/searchUserGroup.js'

export default {

	components: {
		NcSelect,
	},

	mixins: [ShareTypes, formatting, searchUserGroup],

	props: {
		newOwnerUserId: {
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
				return this.newOwnerUserId
			},
			set(v) {
				this.$emit('update:newOwnerUserId', v)
			},
		},
	},

	methods: {
		addTransfer(selectedItem) {
			if (selectedItem) {
				this.localValue = selectedItem.user
			} else {
				this.localValue = ''
			}
		},
		filterOutUnwantedItems(list) {
			return this.filterOutCurrentUser(list)
		},
	},
}
</script>
