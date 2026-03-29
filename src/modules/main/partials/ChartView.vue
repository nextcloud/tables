<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="chart-view">
		<ChartConfigBar :columns="columns" :config="chartConfig" @update:config="onConfigChange" />
		<div v-if="!hasValidConfig" class="chart-empty">
			<p>{{ t('tables', 'Select columns to visualize your data.') }}</p>
		</div>
		<div v-else class="chart-container">
			<component :is="chartComponent" :data="chartData" :options="chartOptions" />
		</div>
	</div>
</template>

<script>
import { Bar, Line, Pie } from 'vue-chartjs'
import {
	Chart as ChartJS,
	Title,
	Tooltip,
	Legend,
	BarElement,
	LineElement,
	PointElement,
	ArcElement,
	CategoryScale,
	LinearScale,
} from 'chart.js'
import ChartConfigBar from './ChartConfigBar.vue'

ChartJS.register(Title, Tooltip, Legend, BarElement, LineElement, PointElement, ArcElement, CategoryScale, LinearScale)

const CHART_COLORS = [
	'#0082c9', '#33b5e5', '#e9322d', '#eca700', '#46ba61',
	'#969696', '#6c5ce7', '#fd79a8', '#00b894', '#fdcb6e',
	'#636e72', '#d63031', '#74b9ff', '#a29bfe', '#55efc4',
]

export default {
	name: 'ChartView',
	components: { Bar, Line, Pie, ChartConfigBar },
	props: {
		rows: { type: Array, default: () => [] },
		columns: { type: Array, default: () => [] },
		element: { type: Object, default: null },
		isView: { type: Boolean, default: false },
	},
	data() {
		return {
			chartConfig: this.loadConfig(),
		}
	},
	computed: {
		hasValidConfig() {
			return this.chartConfig.xColumnId !== null
		},
		xColumn() {
			return this.columns.find(c => c.id === this.chartConfig.xColumnId)
		},
		yColumn() {
			return this.columns.find(c => c.id === this.chartConfig.yColumnId)
		},
		chartComponent() {
			switch (this.chartConfig.chartType) {
			case 'line': return 'Line'
			case 'pie': return 'Pie'
			default: return 'Bar'
			}
		},
		aggregatedData() {
			if (!this.xColumn) return { labels: [], values: [] }

			const groups = {}
			for (const row of this.rows) {
				const xCell = row.data?.find(d => d.columnId === this.chartConfig.xColumnId)
				const label = this.formatCellValue(xCell, this.xColumn) || t('tables', '(empty)')

				if (!groups[label]) groups[label] = { sum: 0, count: 0 }
				groups[label].count++

				if (this.yColumn) {
					const yCell = row.data?.find(d => d.columnId === this.chartConfig.yColumnId)
					const val = parseFloat(yCell?.value) || 0
					groups[label].sum += val
				}
			}

			const labels = Object.keys(groups)
			const values = labels.map(l => this.yColumn ? groups[l].sum : groups[l].count)
			return { labels, values }
		},
		chartData() {
			const { labels, values } = this.aggregatedData
			const isPie = this.chartConfig.chartType === 'pie'

			return {
				labels,
				datasets: [{
					label: this.yColumn?.title || t('tables', 'Count'),
					data: values,
					backgroundColor: isPie
						? labels.map((_, i) => CHART_COLORS[i % CHART_COLORS.length])
						: CHART_COLORS[0],
					borderColor: isPie
						? labels.map((_, i) => CHART_COLORS[i % CHART_COLORS.length])
						: CHART_COLORS[0],
					borderWidth: isPie ? 2 : 1,
				}],
			}
		},
		chartOptions() {
			const isPie = this.chartConfig.chartType === 'pie'
			return {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: { display: isPie, position: 'right' },
					title: { display: false },
				},
				...(isPie ? {} : {
					scales: {
						y: { beginAtZero: true },
					},
				}),
			}
		},
	},
	methods: {
		onConfigChange(config) {
			this.chartConfig = config
			this.saveConfig(config)
		},
		formatCellValue(cell, column) {
			if (!cell || cell.value === null || cell.value === undefined) return ''
			if (column.type === 'selection') {
				const options = column.selectionOptions || []
				const opt = options.find(o => String(o.id) === String(cell.value))
				return opt?.label || String(cell.value)
			}
			return String(cell.value)
		},
		loadConfig() {
			const key = `tables-chart-config-${this.isView ? 'view' : 'table'}-${this.element?.id}`
			try {
				const stored = localStorage.getItem(key)
				if (stored) return JSON.parse(stored)
			} catch (e) { /* ignore */ }
			return { chartType: 'bar', xColumnId: null, yColumnId: null }
		},
		saveConfig(config) {
			const key = `tables-chart-config-${this.isView ? 'view' : 'table'}-${this.element?.id}`
			try {
				localStorage.setItem(key, JSON.stringify(config))
			} catch (e) { /* ignore */ }
		},
	},
}
</script>

<style lang="scss" scoped>
.chart-view {
	padding: 0 8px;
}

.chart-container {
	position: relative;
	height: 400px;
	max-width: 900px;
}

.chart-empty {
	display: flex;
	align-items: center;
	justify-content: center;
	height: 300px;
	color: var(--color-text-maxcontrast);
}
</style>
