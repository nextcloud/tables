<!--
	- SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
	- SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<ActivityList v-if="activeElement"
		filter="tables"
		:object-id="activeElement.id"
		:object-type="isView ? 'view' : 'table'"
		type="tables" />
</template>

<script>
import ActivityList from '../../../shared/components/ActivityList.vue'
import { mapState } from 'pinia'
import { useTablesStore } from '../../../store/store.js'

export default {
	name: 'SidebarActivity',
	components: {
		ActivityList,
	},
	computed: {
		...mapState(useTablesStore, ['activeElement', 'isView']),
	},
}
</script>
