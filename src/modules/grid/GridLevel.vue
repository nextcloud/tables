<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<GridCanvas :layout="layout" :editable="grid.editable()" @layout-change="grid.changeLayout">
		<template #widget="{ item, narrow }">
			<GridWidget :widget="grid.widgetsByKey()[item.widgetId]"
				:nodes="grid.nodes()"
				:start-page-content="grid.startPageContent()"
				:layout-item="item"
				:editable="grid.editable()"
				:arrangeable="!narrow"
				@configure="grid.configure"
				@remove="grid.remove"
				@move="grid.move" />
		</template>
	</GridCanvas>
</template>

<script>
import GridCanvas from './GridCanvas.vue'
import GridWidget from './GridWidget.vue'

/**
 * One level of the grid of a page: the widgets on the page itself, or those in one tab of a
 * tabs widget. The page provides the grid and handles every change to it.
 */
export default {
	name: 'GridLevel',
	components: {
		GridCanvas,
		GridWidget,
	},
	inject: {
		grid: { from: 'gridLevel' },
	},
	props: {
		// The key of the tabs widget whose tab this is; null for the page itself
		parentKey: {
			type: String,
			default: null,
		},
		// The key of the tab
		tab: {
			type: String,
			default: null,
		},
	},
	computed: {
		layout() {
			return this.grid.shownLayout(this.parentKey, this.tab)
		},
	},
}
</script>
