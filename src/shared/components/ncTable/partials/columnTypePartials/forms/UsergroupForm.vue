<template>
	<div style="width: 100%">
		<div>
			<div class="col-4 selections space-T space-L">
				<NcCheckboxRadioSwitch :checked.sync="checkedValue" value="usergroup-user" name="usergroupTypeSelection"
					type="radio" data-cy="createColumnUserSwitch">
					{{ t('tables', 'Users') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch :checked.sync="checkedValue" value="usergroup-group"
					name="usergroupTypeSelection" type="radio" data-cy="createColumnGroupSwitch">
					{{ t('tables', 'Groups') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch :checked.sync="checkedValue" value="usergroup" name="usergroupTypeSelection"
					type="radio" data-cy="createColumnUserAndGroupSwitch">
					{{ t('tables', 'Users and groups') }}
				</NcCheckboxRadioSwitch>
			</div>

			<div class="row space-T">
				<div class="fix-col-4">
					{{ t('tables', 'Default') }}
				</div>
				<div class="fix-col-4 space-B">
					<!-- TODO: Add prop for single or multiple -->
					<NcSelect v-model="value" style="width: 100%;" :loading="loading" :options="options"
						:placeholder="getPlaceholder()" :searchable="true" :get-option-key="(option) => option.key"
						label="displayName" :aria-label-combobox="getPlaceholder()"
						:user-select="mutableColumn.usergroupSelectUsers"
						:group-select="mutableColumn.usergroupSelectGroups" :close-on-select="false"
						:multiple="mutableColumn.usergroupMultipleItems" @search="asyncFind" @input="addItem">
						<template #noResult>
							{{ noResultText }}
						</template>
					</NcSelect>
				</div>
				<div class="row ">
					<div class="fix-col-4 title">
						{{ t('tables', 'Select multiple items') }}
					</div>
					<div class="fix-col-4 space-L-small">
						<NcCheckboxRadioSwitch type="switch" :checked.sync="mutableColumn.usergroupMultipleItems" />
					</div>
				</div>
			</div>
			<!-- TODO: Make usergroupSelectUsers reactive -->
			<div v-if="mutableColumn.usergroupSelectUsers" class="row">
				<div class="fix-col-4 title">
					{{ t('tables', 'Show user status') }}
				</div>
				<div class="fix-col-4 space-L-small">
					<NcCheckboxRadioSwitch type="switch" :checked.sync="mutableColumn.showUserStatus" />
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import NcUserAndGroupPicker from '../../../../ncUserAndGroupPicker/NcUserAndGroupPicker.vue'
import { translate as t } from '@nextcloud/l10n'
import { NcCheckboxRadioSwitch, NcSelect } from '@nextcloud/vue'
import searchUserGroup from '../../../../../mixins/searchUserGroup.js'
import ShareTypes from '../../../../../mixins/shareTypesMixin.js'

export default {
	name: 'UsergroupForm',
	components: {
		NcUserAndGroupPicker,
		NcCheckboxRadioSwitch,
		NcSelect,
	},
	mixins: [ShareTypes, searchUserGroup],
	props: {
		column: {
			type: Object,
			default: null,
		},
		canSave: {
			type: Boolean,
			default: true,
		},
	},
	data() {
		return {
			mutableColumn: this.column,
			value: [],
		}
	},
	computed: {
		localValue: {
			get() {
				return this.column.usergroupDefault
			},
			set(v) {
				// TODO update to get groups too
				if (Array.isArray(v)) {
					this.column.usergroupDefault = v.map(o => {
						return {type: o.isUser ? 0 : 1, id: o.user }
					})
				} else {
					this.column.usergroupDefault = [{ type: v.isUser ? 0 : 1, id: v.user }]
				}

			},
		},
		checkedValue: {
			get() {
				if (this.mutableColumn.usergroupSelectUsers && !this.mutableColumn.usergroupSelectGroups) {
					return 'usergroup-user'
				} else if (!this.mutableColumn.usergroupSelectUsers && this.mutableColumn.usergroupSelectGroups) {
					return 'usergroup-group'
				} else {
					return 'usergroup'
				}
			},
			set(newValue) {
				if (newValue === 'usergroup-user') {
					this.mutableColumn.usergroupSelectUsers = true
					this.mutableColumn.usergroupSelectGroups = false
				} else if (newValue === 'usergroup-group') {
					this.mutableColumn.usergroupSelectUsers = false
					this.mutableColumn.usergroupSelectGroups = true
				} else {
					this.mutableColumn.usergroupSelectUsers = true
					this.mutableColumn.usergroupSelectGroups = true
				}
			},
		},
	},
	watch: {
		column() {
			this.mutableColumn = this.column
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
	},

}
</script>
<style lang="scss" scoped>
.selections {
    display: inline-flex;
    flex-wrap: wrap;
}

.selections span {
    padding-right: 21px;
}
</style>
