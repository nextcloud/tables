<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div ref="gridElement" class="grid-stack grid-canvas" :class="{ 'grid-canvas--editing': editable }">
		<div v-for="item in layout"
			:key="item.widgetId"
			class="grid-stack-item"
			:gs-id="item.widgetId"
			:gs-x="item.gridX"
			:gs-y="item.gridY"
			:gs-w="item.gridWidth"
			:gs-h="item.gridHeight"
			:gs-min-w="2"
			data-cy="grid-item">
			<div class="grid-stack-item-content">
				<slot name="widget" :item="item" />
			</div>
		</div>
	</div>
</template>

<script>
import { GridStack } from 'gridstack'
import 'gridstack/dist/gridstack.min.css'
import { GRID_CELL_HEIGHT, GRID_COLUMNS } from './widgetRegistry.js'

/**
 * Places widgets on a 12 column grid. In edit mode the widgets can be dragged and resized;
 * every change is emitted as a full layout, the parent decides when to persist it.
 */
export default {
	name: 'GridCanvas',
	props: {
		layout: {
			type: Array,
			required: true,
		},
		editable: {
			type: Boolean,
			default: false,
		},
	},
	emits: ['layout-change'],
	data() {
		return {
			grid: null,
			resizeObserver: null,
		}
	},
	watch: {
		editable(editable) {
			this.grid?.setStatic(!editable)
		},
		layout: {
			handler(layout, previousLayout) {
				this.syncGridItems(layout, previousLayout ?? [])
			},
		},
	},
	mounted() {
		this.grid = GridStack.init({
			column: GRID_COLUMNS,
			cellHeight: GRID_CELL_HEIGHT,
			margin: 8,
			float: true,
			animate: true,
			staticGrid: !this.editable,
			minRow: 1,
		}, this.$refs.gridElement)
		this.grid.on('change', (event, changedNodes) => this.onGridChange(changedNodes))
		// 12 columns on a wide canvas, 6 on a medium one and a single column on a phone,
		// driven by the canvas width so the grid restores when the window grows again
		this.resizeObserver = new ResizeObserver(entries => this.applyColumns(entries[0]?.contentRect.width ?? 0))
		this.resizeObserver.observe(this.$refs.gridElement)
	},
	beforeUnmount() {
		this.resizeObserver?.disconnect()
		this.resizeObserver = null
		this.grid?.destroy(false)
		this.grid = null
	},
	methods: {
		/**
		 * Tells GridStack about widgets Vue added or removed. Runs before Vue touches the DOM,
		 * so a removed widget's element still exists when GridStack forgets it.
		 *
		 * @param {Array} layout new layout
		 * @param {Array} previousLayout the layout before the change
		 */
		syncGridItems(layout, previousLayout) {
			if (!this.grid) {
				return
			}
			const currentIds = new Set(layout.map(item => item.widgetId))
			const previousIds = new Set(previousLayout.map(item => item.widgetId))

			for (const node of [...this.grid.engine.nodes]) {
				if (!currentIds.has(node.id)) {
					this.grid.removeWidget(node.el, false, false)
				}
			}

			const addedIds = layout.filter(item => !previousIds.has(item.widgetId)).map(item => item.widgetId)
			if (addedIds.length === 0) {
				return
			}
			this.$nextTick(() => {
				for (const widgetId of addedIds) {
					const element = this.$refs.gridElement?.querySelector(`.grid-stack-item[gs-id="${widgetId}"]`)
					if (element && !element.gridstackNode) {
						this.grid.makeWidget(element)
					}
				}
			})
		},
		/**
		 * @param {number} width current width of the canvas in pixels
		 */
		applyColumns(width) {
			if (!this.grid || width === 0) {
				return
			}
			const columns = width < 560 ? 1 : (width < 960 ? 6 : GRID_COLUMNS)
			if (this.grid.getColumn() !== columns) {
				this.grid.column(columns, 'moveScale')
			}
		},
		onGridChange(changedNodes) {
			if (!Array.isArray(changedNodes) || changedNodes.length === 0) {
				return
			}
			// A reflow to fewer columns is only how the grid is shown on this screen, never the stored layout
			if (this.grid.getColumn() !== GRID_COLUMNS) {
				return
			}
			const positions = Object.fromEntries(changedNodes.map(node => [node.id, node]))
			const updated = this.layout.map(item => {
				const node = positions[item.widgetId]
				if (!node) {
					return item
				}
				return {
					...item,
					gridX: node.x,
					gridY: node.y,
					gridWidth: node.w,
					gridHeight: node.h,
				}
			})
			this.$emit('layout-change', updated)
		},
	},
}
</script>

<style lang="scss" scoped>
.grid-canvas {
	min-height: 160px;
}

.grid-stack-item-content {
	display: flex;
	flex-direction: column;
	overflow: hidden;
	background-color: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
}

.grid-canvas--editing .grid-stack-item-content {
	outline: 2px dashed var(--color-border-dark);
	outline-offset: -2px;
	cursor: move;
}
</style>
