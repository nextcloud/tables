<template>
	<div style="width: 100%">
		<!-- default -->
		<div class="row space-T">
			<div class="fix-col-4">
				{{ t('tables', 'Default') }}
			</div>
			<div class="fix-col-2">
				<input v-model="mutableColumn.numberDefault"
					type="number"
					min="0"
					max="100">
			</div>
		</div>
	</div>
</template>

<script>

import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'NumberProgressForm',
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
