<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="table-wrapper">
		<EmptyView v-if="columns.length === 0" :view="element" />
		<TableView v-else
			:rows="rows"
			:columns="columns"
			:element="element"
			:can-read-rows="true"
			:can-create-rows="false"
			:can-edit-rows="false"
			:can-delete-rows="false"
			:can-create-columns="false"
			:can-edit-columns="false"
			:can-delete-columns="false"
			:can-delete-table="false">
			<template #actions>
				<NcActions :force-menu="true" type="tertiary">
					<NcActionButton :close-after-click="true"
						icon="icon-download"
						data-cy="dataTableExportBtn"
						@click="$emit('download-csv')">
						{{ t('tables', 'Export as CSV') }}
					</NcActionButton>
				</NcActions>
			</template>
		</TableView>
	</div>
</template>

<script>
import TableView from '../partials/TableView.vue'
import EmptyView from './EmptyView.vue'
import { NcActions, NcActionButton } from '@nextcloud/vue'

export default {
	name: 'PublicElement',
	components: {
		EmptyView,
		TableView,
		NcActions,
		NcActionButton,
	},

	props: {
		element: {
			type: Object,
			default: null,
		},
		columns: {
			type: Array,
			default: () => [],
		},
		rows: {
			type: Array,
			default: () => [],
		},
	},
}
</script>
