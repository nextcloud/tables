<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div v-if="targets.length" class="tables-widget" data-cy="tablesWidget">
		<DataWidget v-for="target in targets"
			:key="target.type + target.id"
			class="tables-widget__element"
			:target="target" />
	</div>
	<NcEmptyContent v-else class="tables-widget__empty" :name="t('tables', 'No tables or views yet')" data-cy="tablesWidgetEmpty">
		<template #icon>
			<TableIcon :size="20" />
		</template>
	</NcEmptyContent>
</template>

<script>
import { NcEmptyContent } from '@nextcloud/vue'
import TableIcon from 'vue-material-design-icons/TableLarge.vue'
import DataWidget from './DataWidget.vue'
import { orderedNodeRelIds, widgetTarget } from '../gridLayout.js'

/**
 * Shows every table and view of the application, one below the other in the order of
 * "Edit application", as the start page did before it became a grid page. The start page of a new application begins with it.
 */
export default {
	name: 'TablesWidget',
	components: {
		DataWidget,
		NcEmptyContent,
		TableIcon,
	},
	props: {
		// The nodes of the application by relation id
		nodes: {
			type: Object,
			default: () => ({}),
		},
		// The content of the start page, which holds the order of the tables and views
		content: {
			type: Object,
			default: () => ({}),
		},
	},
	computed: {
		targets() {
			return orderedNodeRelIds(this.nodes, this.content)
				.map(nodeRelId => widgetTarget({ nodeRelId }, this.nodes))
				.filter(Boolean)
		},
	},
}
</script>

<style lang="scss" scoped>
.tables-widget {
	height: 100%;
	overflow: auto;

	&__element {
		height: auto;
		overflow: visible;

		& + & {
			margin-top: calc(6 * var(--default-grid-baseline));
		}
	}

	&__empty {
		height: 100%;
	}
}
</style>
