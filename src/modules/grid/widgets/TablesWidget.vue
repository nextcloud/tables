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
import { widgetTarget } from '../gridLayout.js'

/**
 * Shows every table and view of the application, one below the other, as the start page
 * did before it became a grid page. The start page of a new application begins with it.
 */
export default {
	name: 'TablesWidget',
	components: {
		DataWidget,
		NcEmptyContent,
		TableIcon,
	},
	props: {
		// The nodes of the application by relation id, in the order they were added
		nodes: {
			type: Object,
			default: () => ({}),
		},
	},
	computed: {
		targets() {
			return Object.keys(this.nodes ?? {})
				.map(nodeRelId => widgetTarget({ nodeRelId: parseInt(nodeRelId) }, this.nodes))
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
