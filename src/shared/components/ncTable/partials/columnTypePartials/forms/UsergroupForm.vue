<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div style="width: 100%">
		<div>
			<div class="col-4 selections space-T space-L">
				<NcCheckboxRadioSwitch
					v-for="key in Object.keys(selectOptions)"
					:key="key"
					:checked.sync="checkedValue"
					:value="key"
					name="usergroupTypeSelection"
					:data-cy="`${selectOptions[key].toLowerCase()}Switch`"
					:disabled="isOnlyChecked(key)">
					{{ t('tables', selectOptions[key]) }}
				</NcCheckboxRadioSwitch>
			</div>

			<div class="row space-T">
				<div class="fix-col-4">
					{{ t('tables', 'Default') }}
				</div>
				<div class="fix-col-4 space-B">
					<NcSelect v-model="value" style="width: 100%;" :loading="loading" :options="options"
						:placeholder="getPlaceholder()" :searchable="true" :get-option-key="(option) => option.key"
						label="displayName" :aria-label-combobox="getPlaceholder()"
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
			selectUsers: this.column.usergroupSelectUsers,
			selectGroups: this.column.usergroupSelectGroups,
			selectCircles: false,
			selectOptions: {
				'usergroup-user': 'Users',
				'usergroup-group': 'Groups',
			},
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
				const values = []
				if (this.selectUsers) {
					values.push('usergroup-user')
				}
				if (this.selectGroups) {
					values.push('usergroup-group')
				}
				if (this.selectCircles) {
					values.push('usergroup-team')
				}
				return values
			},
			set(newValue) {
				if (!Array.isArray(newValue)) {
					return
				}
				this.selectUsers = newValue.includes('usergroup-user')
				this.mutableColumn.usergroupSelectUsers = newValue.includes('usergroup-user')

				this.selectGroups = newValue.includes('usergroup-group')
				this.mutableColumn.usergroupSelectGroups = newValue.includes('usergroup-group')

				this.selectCircles = newValue.includes('usergroup-team')
				this.mutableColumn.usergroupSelectTeams = newValue.includes('usergroup-team')
			},
		},
	},
	watch: {
		column() {
			this.mutableColumn = this.column
		},
	},
	created() {
		// Update the circles-related data once the component is created
		// Doing this in data() doesn't work due to timing issues,
		// since the data() function runs before the capabilities are fully initialized
		this.selectCircles = this.isCirclesEnabled ? this.column.usergroupSelectTeams : false
		if (this.isCirclesEnabled) {
			this.selectOptions['usergroup-team'] = 'Teams'
		}
	},
	methods: {
		isOnlyChecked(key) {
			// Assuming checkedValue is an array of checked keys
			return this.checkedValue.length === 1 && this.checkedValue.includes(key)
		},

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
				type: this.getType(autocompleteResult.source),
				key: autocompleteResult.source + '-' + autocompleteResult.id,
				displayName: autocompleteResult.label,
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
    padding-inline-end: 21px;
}
</style>
