<template>
	<RowFormWrapper :title="column.title" :mandatory="column.mandatory" :description="column.description" :width="2">
		<NcSelect v-model="localValue" style="width: 100%;" :loading="loading" :options="options"
			:placeholder="getPlaceholder()" :searchable="true" :get-option-key="(option) => option.key"
			label="displayName" :aria-label-combobox="getPlaceholder()"
			:user-select="column.usergroupSelectUsers" :group-select="column.usergroupSelectGroups"
			:close-on-select="false" :multiple="column.usergroupMultipleItems" @search="asyncFind"
			@input="addItem">
			<template #noResult>
				{{ noResultText }}
			</template>
		</NcSelect>
		<div v-if="canBeCleared" class="icon-close make-empty" @click="emptyValue" />
	</RowFormWrapper>
</template>

<script>
import { NcSelect } from '@nextcloud/vue'
import NcUserAndGroupPicker from '../../../ncUserAndGroupPicker/NcUserAndGroupPicker.vue'
import RowFormWrapper from './RowFormWrapper.vue'
import searchUserGroup from '../../../../mixins/searchUserGroup.js'
import ShareTypes from '../../../../mixins/shareTypesMixin.js'

export default {
	components: {
		NcUserAndGroupPicker,
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
	computed: {
		canBeCleared() {
			return !this.column.mandatory
		},
		localValue: {
			get() {
				return this.value
			},
			set(v) {
				//TODO update to get groups too
				let formattedValue = []
                if (Array.isArray(v)) {
                    formattedValue = v.map(o => {
                        return { displayName: o.displayName, isUser: o.isUser, id: o.user }
                    })
                } else {
                    formattedValue = [{ displayName: v.displayName, isUser: v.isUser, id: v.user }]
                }
				console.log(formattedValue)
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
		// TODO: filter properly
        filterOutUnwantedItems(list) {
            return list
        },
		emptyValue() {
			this.localValue = 'none'
		},
	},
}
</script>