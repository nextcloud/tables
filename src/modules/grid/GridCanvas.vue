<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="grid-canvas" :class="{ 'grid-canvas--editing': isArranging }">
		<p v-if="editable && isNarrow && !isNested" class="grid-canvas__hint" data-cy="gridNarrowHint">
			{{ t('tables', 'Widen the window to arrange the widgets. On a narrow screen the page is only shown.') }}
		</p>
		<div ref="gridElement" class="grid-stack grid-canvas__stack">
			<div v-for="item in layout"
				:key="item.widgetId"
				class="grid-stack-item"
				:gs-id="item.widgetId"
				:gs-x="item.gridX"
				:gs-y="item.gridY"
				:gs-w="item.gridWidth"
				:gs-h="item.gridHeight"
				:gs-min-w="minWidth"
				data-cy="gridItem">
				<div class="grid-stack-item-content">
					<slot name="widget" :item="item" :narrow="isNarrow" />
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import { computed } from 'vue'
import { GridStack } from 'gridstack'
import { isRTL } from '@nextcloud/l10n'
import 'gridstack/dist/gridstack.min.css'
import { GRID_CELL_HEIGHT, GRID_COLUMNS, GRID_MARGIN, GRID_MIN_WIDTH, applyNodePositions, columnsForWidth } from './gridLayout.js'

/**
 * @param {{x: number, y: number, w: number, h: number}} node a GridStack node
 * @param {object} item the layout item of its widget
 * @return {boolean} whether the node sits where the item says
 */
function isAtPosition(node, item) {
	return node.x === item.gridX && node.y === item.gridY && node.w === item.gridWidth && node.h === item.gridHeight
}

/**
 * Places widgets on a 12 column grid. In edit mode the widgets can be dragged and resized;
 * every change is emitted as a full layout, the parent decides when to persist it.
 *
 * On a narrower canvas the grid shows a reflow of the layout. That reflow is never stored,
 * so widgets can only be arranged at full width; the widget slot gets narrow to know it.
 */
export default {
	name: 'GridCanvas',
	inject: {
		// The column mode of the canvas around this one, as for the canvas of a tab
		parentNarrow: { from: 'gridNarrow', default: null },
	},
	provide() {
		return {
			gridNarrow: computed(() => this.isNarrow),
		}
	},
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
			columns: GRID_COLUMNS,
		}
	},
	computed: {
		isNested() {
			return this.parentNarrow !== null
		},
		// A nested canvas arranges whenever the page does, however narrow it is itself
		isNarrow() {
			return this.isNested ? this.parentNarrow : this.columns !== GRID_COLUMNS
		},
		isArranging() {
			return this.editable && !this.isNarrow
		},
	},
	watch: {
		isArranging(isArranging) {
			if (isArranging) {
				this.grid?.enable()
			} else {
				this.grid?.disable()
			}
		},
		layout(layout, previousLayout) {
			this.syncGridItems(layout, previousLayout ?? [])
		},
		isNarrow() {
			this.applyColumns(this.width)
		},
	},
	created() {
		// Not declared in data(): GridStack and the observer must stay plain objects, a reactive
		// proxy around GridStack makes it ignore every drop
		this.grid = null
		this.resizeObserver = null
		this.minWidth = GRID_MIN_WIDTH
		// Set while the grid takes a layout from the prop, whose changes are not the user's
		this.applyingLayout = false
		this.width = 0
	},
	mounted() {
		this.grid = GridStack.init({
			column: GRID_COLUMNS,
			cellHeight: GRID_CELL_HEIGHT,
			margin: GRID_MARGIN,
			float: true,
			animate: !window.matchMedia('(prefers-reduced-motion: reduce)').matches,
			rtl: isRTL(),
			disableDrag: !this.isArranging,
			disableResize: !this.isArranging,
			minRow: 1,
		}, this.$refs.gridElement)
		// GridStack takes a grid inside an item of another grid for its sub grid and hands its
		// changes to the outer one; the canvas of a tab handles its own
		if (this.grid.parentGridNode) {
			delete this.grid.parentGridNode.subGrid
			delete this.grid.parentGridNode
		}
		this.grid.on('change', (event, changedNodes) => this.onGridChange(changedNodes))
		// The column count follows the canvas width, so the grid restores when the window grows again
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
		 * Tells GridStack about widgets Vue added, removed or moved. Runs before Vue touches the DOM,
		 * so a removed widget's element still exists when GridStack forgets it.
		 *
		 * @param {Array} layout new layout
		 * @param {Array} previousLayout the layout before the change
		 */
		syncGridItems(layout, previousLayout) {
			if (!this.grid) {
				return
			}
			const previousIds = new Set(previousLayout.map(item => item.widgetId))
			const positions = Object.fromEntries(layout.map(item => [item.widgetId, item]))
			for (const node of [...this.grid.engine.nodes]) {
				if (!positions[node.id]) {
					this.grid.removeWidget(node.el, false, false)
				}
			}
			// A move from the widget menu rather than a drag. On a narrower canvas the grid shows a
			// reflow of the stored layout, which loads again when the canvas widens.
			if (this.grid.getColumn() === GRID_COLUMNS && this.grid.engine.nodes.some(node => !isAtPosition(node, positions[node.id]))) {
				this.loadLayout(layout)
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
		 * Puts the widgets GridStack knows where the layout has them, in one go so they do not push
		 * each other on the way. Emits the layout when GridStack had to move a widget to make room.
		 *
		 * @param {Array} layout the layout to show
		 */
		loadLayout(layout) {
			const nodeIds = new Set(this.grid.engine.nodes.map(node => node.id))
			const items = layout.filter(item => nodeIds.has(item.widgetId))
				.map(item => ({ id: item.widgetId, x: item.gridX, y: item.gridY, w: item.gridWidth, h: item.gridHeight }))
			this.applyingLayout = true
			try {
				this.grid.load(items, false)
			} finally {
				this.applyingLayout = false
			}
			const nodes = Object.fromEntries(this.grid.engine.nodes.map(node => [node.id, node]))
			if (layout.some(item => nodes[item.widgetId] && !isAtPosition(nodes[item.widgetId], item))) {
				this.$emit('layout-change', applyNodePositions(layout, this.grid.engine.nodes))
			}
		},
		/**
		 * Shows the number of columns that fits the canvas width.
		 *
		 * @param {number} width current width of the canvas in pixels
		 */
		applyColumns(width) {
			this.width = width
			if (!this.grid || width === 0) {
				return
			}
			// A nested canvas keeps all columns while the page arranges, and reflows with it
			const columns = this.isNested && !this.isNarrow ? GRID_COLUMNS : columnsForWidth(width)
			if (this.grid.getColumn() !== columns) {
				this.applyingLayout = true
				try {
					this.grid.column(columns, 'moveScale')
				} finally {
					this.applyingLayout = false
				}
				// GridStack restores the 12 columns from its own cache, which misses layout changes
				// made while the canvas was narrower
				if (columns === GRID_COLUMNS) {
					this.loadLayout(this.layout)
				}
			}
			this.columns = columns
		},
		/**
		 * Emits the layout after a drag or resize. A reflow to fewer columns is only how the grid is
		 * shown on this screen, never the stored layout, so it is not emitted.
		 *
		 * @param {Array} changedNodes the GridStack nodes that moved
		 */
		onGridChange(changedNodes) {
			if (this.applyingLayout || !Array.isArray(changedNodes) || changedNodes.length === 0 || this.grid.getColumn() !== GRID_COLUMNS) {
				return
			}
			this.$emit('layout-change', applyNodePositions(this.layout, changedNodes))
		},
	},
}
</script>

<style lang="scss" scoped>
.grid-canvas {
	&__hint {
		margin: 0 0 calc(2 * var(--default-grid-baseline));
		color: var(--color-text-maxcontrast);
	}

	&__stack {
		min-height: 160px;
	}
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
	outline: 2px dashed var(--color-border);
	outline-offset: -2px;
	cursor: move;
}
</style>
