<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<!-- <div v-if="!view" class="icon-loading" />
    <div v-else class="main-view-view"> -->
	<div>
		<ElementTitle :active-element="view" :is-table="false" :view-setting.sync="localViewSetting" />
		<div class="table-wrapper">
			<EmptyView v-if="columns.length === 0" :view="view" />
			<TableView v-else
				:rows="rows"
				:columns="columns"
				:element="view"
				:view-setting.sync="localViewSetting"
				:is-view="true"
				:can-read-rows="true"
				:can-create-rows="true"
				:can-edit-rows="true"
				:can-delete-rows="false"
				:can-create-columns="false"
				:can-edit-columns="false"
				:can-delete-columns="false"
				:can-delete-table="false" />
		</div>
	</div>
</template>

<script>
import TableView from '../partials/TableView.vue'

import EmptyView from './EmptyView.vue'
import permissionsMixin from '../../../shared/components/ncTable/mixins/permissionsMixin.js'
import ElementTitle from './ElementTitle.vue'

export default {
	components: {
		EmptyView,
		TableView,
		ElementTitle,
	},

	mixins: [permissionsMixin],

	props: {
		view: {
			type: Object,
			default: null,
		},
		columns: {
			type: Array,
			default: null,
		},
		rows: {
			type: Array,
			default: null,
		},
	},

	data() {
		return {
			localLoading: false,
			lastActiveViewId: null,
			localViewSetting: {},
		}
	},
}
</script>
