<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="chart-config-bar">
		<div class="chart-config-item">
			<label>{{ t('tables', 'Chart type') }}</label>
			<NcSelect v-model="localChartType"
				:options="chartTypeOptions"
				:clearable="false"
				label="label"
				track-by="id"
				@input="emitConfig" />
		</div>
		<div class="chart-config-item">
			<label>{{ t('tables', 'X-Axis') }}</label>
			<NcSelect v-model="localXColumn"
				:options="columnOptions"
				:clearable="false"
				label="title"
				track-by="id"
				@input="emitConfig" />
		</div>
		<div class="chart-config-item">
			<label>{{ t('tables', 'Y-Axis') }}</label>
			<NcSelect v-model="localYColumn"
				:options="numericColumnOptions"
				:clearable="true"
				:placeholder="t('tables', 'Count (rows)')"
				label="title"
				track-by="id"
				@input="emitConfig" />
		</div>
	</div>
</template>

<script>
import { NcSelect } from '@nextcloud/vue'

export default {
	name: 'ChartConfigBar',
	components: { NcSelect },
	props: {
		columns: {
			type: Array,
			required: true,
		},
		config: {
			type: Object,
			default: () => ({ chartType: 'bar', xColumnId: null, yColumnId: null }),
		},
	},
	data() {
		return {
			chartTypeOptions: [
				{ id: 'bar', label: t('tables', 'Bar') },
				{ id: 'line', label: t('tables', 'Line') },
				{ id: 'pie', label: t('tables', 'Pie / Donut') },
			],
			localChartType: null,
			localXColumn: null,
			localYColumn: null,
		}
	},
	computed: {
		columnOptions() {
			return this.columns.filter(c => c.id >= 0)
		},
		numericColumnOptions() {
			return this.columns.filter(c => c.id >= 0 && (c.type === 'number' || c.type === 'number-stars' || c.type === 'number-progress'))
		},
	},
	watch: {
		config: {
			immediate: true,
			handler(val) {
				this.localChartType = this.chartTypeOptions.find(o => o.id === val?.chartType) || this.chartTypeOptions[0]
				this.localXColumn = this.columns.find(c => c.id === val?.xColumnId) || this.columnOptions[0] || null
				this.localYColumn = this.columns.find(c => c.id === val?.yColumnId) || null
			},
		},
		columns: {
			immediate: true,
			handler() {
				if (!this.localXColumn && this.columnOptions.length > 0) {
					this.localXColumn = this.columnOptions[0]
					this.emitConfig()
				}
			},
		},
	},
	methods: {
		emitConfig() {
			this.$emit('update:config', {
				chartType: this.localChartType?.id || 'bar',
				xColumnId: this.localXColumn?.id || null,
				yColumnId: this.localYColumn?.id || null,
			})
		},
	},
}
</script>

<style lang="scss" scoped>
.chart-config-bar {
	display: flex;
	gap: 16px;
	align-items: flex-end;
	padding: 12px 0;
	flex-wrap: wrap;
}

.chart-config-item {
	display: flex;
	flex-direction: column;
	gap: 4px;
	min-width: 160px;

	label {
		font-size: 12px;
		font-weight: 600;
		color: var(--color-text-maxcontrast);
		text-transform: uppercase;
		letter-spacing: 0.5px;
	}
}
</style>
