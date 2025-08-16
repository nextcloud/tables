<!--
	- SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
	- SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog v-if="showModal"
		data-cy="rowActivityModal"
		:name="t('tables', 'Row activity')"
		size="large"
		@closing="actionCancel">
		<div class="modal__content">
			<ActivityList filter="tables"
				:object-id="rowId"
				object-type="tables_row"
				type="tables" />
		</div>
	</NcDialog>
</template>

<script>
import ActivityList from '../../shared/components/ActivityList.vue'
import { NcDialog } from '@nextcloud/vue'

export default {
	name: 'RowActivity',
	components: {
		ActivityList,
		NcDialog,
	},
	props: {
		showModal: {
			type: Boolean,
			default: false,
		},
		rowId: {
			type: Number,
			required: true,
		},
	},
	methods: {
		actionCancel() {
			this.$emit('close')
		},
	},
}
</script>
