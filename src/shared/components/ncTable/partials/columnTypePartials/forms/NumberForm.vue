<template>
	<div>
		<div class="row space-T">
			<div class="col-2 space-R" style="display: block">
				<div class="fix-col-4 title">
					{{ t('tables', 'Default value') }}
				</div>
				<div class="fix-col-4">
					<input v-model="mutableColumn.numberDefault" type="number">
				</div>
			</div>
			<div class="col-2 space-R" style="display: block">
				<!-- decimals -->
				<div class="fix-col-4 title">
					{{ t('tables', 'Decimals') }}
				</div>
				<div class="fix-col-4">
					<input v-model="mutableColumn.numberDecimals" type="number">
				</div>
			</div>
		</div>

		<div class="row space-T">
			<div class="col-2 space-R" style="display: block">
				<!-- min -->
				<div class="fix-col-4 title">
					{{ t('tables', 'Minimum') }}
				</div>
				<div class="fix-col-4">
					<input v-model="mutableColumn.numberMin" type="number">
				</div>
			</div>
			<div class="col-2 space-R" style="display: block">
				<!-- max -->
				<div class="fix-col-4 title">
					{{ t('tables', 'Maximum') }}
				</div>
				<div class="fix-col-4">
					<input v-model="mutableColumn.numberMax" type="number">
				</div>
			</div>
		</div>

		<div class="row space-T">
			<div class="col-2 space-R" style="display: block">
				<!-- prefix -->
				<div class="fix-col-4 title">
					{{ t('tables', 'Prefix') }}
				</div>
				<div class="fix-col-4 space-B">
					<input v-model="mutableColumn.numberPrefix">
				</div>
			</div>
			<div class="col-2 space-R" style="display: block">
				<!-- suffix -->
				<div class="fix-col-4 title" style="display: flex;">
					{{ t('tables', 'Suffix') }}
				</div>
				<div class="fix-col-4">
					<input v-model="mutableColumn.numberSuffix">
				</div>
			</div>
		</div>
	</div>
</template>

<script>

import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'NumberForm',
	props: {
		column: {
			type: Object,
			default: null,
		},
	},
	data() {
		return {
			mutableColumn: this.column,
		}
	},
	watch: {
		column() {
			this.mutableColumn = this.column
		},
	},
	methods: {
		t,
	},
}
</script>
