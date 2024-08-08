<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div style="width: 100%">
		<div>
			<div class="col-4 selections space-T space-L">
				<NcCheckboxRadioSwitch :checked.sync="checkedValue" value="usergroup-user" name="usergroupTypeSelection"
					type="radio" data-cy="userSwitch">
					{{ t('tables', 'Users') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch :checked.sync="checkedValue" value="usergroup-group"
					name="usergroupTypeSelection" type="radio" data-cy="groupSwitch">
					{{ t('tables', 'Groups') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch :checked.sync="checkedValue" value="usergroup" name="usergroupTypeSelection"
					type="radio" data-cy="userAndGroupSwitch">
					{{ t('tables', 'Users and groups') }}
				</NcCheckboxRadioSwitch>
			</div>

			<div class="row space-T">
				<div class="fix-col-4">
					{{ t('tables', 'Default') }}
				</div>
				<div class="fix-col-4 space-B">
					<NcSelect v-model="value" style="width: 100%;" :loading="loading" :options="options"
						:placeholder="getPlaceholder()" :searchable="true" :get-option-key="(option) => option.key"
						label="id" :aria-label-combobox="getPlaceholder()"
						:user-select="true" :close-on-select="false"
						:multiple="mutableColumn.usergroupMultipleItems" data-cy="usergroupDefaultSelect" @search="asyncFind" @input="addItem">
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
						<NcCheckboxRadioSwitch type="switch" :checked.sync="mutableColumn.usergroupMultipleItems" data-cy="usergroupMultipleSwitch" />
					</div>
				</div>
			</div>
			<div v-if="selectUsers" class="row">
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
import { NcCheckboxRadioSwitch, NcSelect } from '@nextcloud/vue'
import searchUserGroup from '../../../../../mixins/searchUserGroup.js'
import ShareTypes from '../../../../../mixins/shareTypesMixin.js'

export default {
	name: 'UsergroupForm',
	components: {
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
			value: this.column.usergroupDefault,
			// Used in searchUserGroup mixin to decide types to search for
			selectUsers: this.column.usergroupSelectUsers,
			selectGroups: this.column.usergroupSelectGroups,
		}
	},
	computed: {
		localValue: {
			get() {
				return this.mutableColumn.usergroupDefault
			},
			set(v) {
				if (Array.isArray(v)) {
					this.mutableColumn.usergroupDefault = v
				} else {
					this.mutableColumn.usergroupDefault = [v]
				}

			},
		},
		checkedValue: {
			get() {
				if (this.selectUsers && !this.selectGroups) {
					return 'usergroup-user'
				} else if (!this.selectUsers && this.selectGroups) {
					return 'usergroup-group'
				} else {
					return 'usergroup'
				}
			},
			set(newValue) {
				if (newValue === 'usergroup-user') {
					this.selectUsers = true
					this.mutableColumn.usergroupSelectUsers = true

					this.selectGroups = false
					this.mutableColumn.usergroupSelectGroups = false
				} else if (newValue === 'usergroup-group') {
					this.selectUsers = false
					this.mutableColumn.usergroupSelectUsers = false

					this.selectGroups = true
					this.mutableColumn.usergroupSelectGroups = true
				} else {
					this.selectUsers = true
					this.mutableColumn.usergroupSelectUsers = true

					this.selectGroups = true
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
<style lang="scss" scoped>
.selections {
    display: inline-flex;
    flex-wrap: wrap;
}

.selections span {
    padding-right: 21px;
}
</style>
