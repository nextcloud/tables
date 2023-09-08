<template>
	<div style="width: 100%">
		<!-- default -->
		<div class="row">
			<div class="fix-col-4">
				{{ t('tables', 'Default') }}
			</div>
			<div class="fix-col-4 space-B">
				<input v-model="mutableColumn.textDefault">
			</div>
		</div>

		<!-- allowed pattern -->
		<!--
		<div class="row">
			<div class="fix-col-1">
				{{ t('tables', 'Allowed pattern (regex)') }}
			</div>
			<div class="fix-col-3 margin-bottom">
				<input v-model="mutableColumn.textAllowedPattern">
			</div>
		</div>
-->

		<!-- max text length -->
		<div class="row">
			<div class="fix-col-4">
				{{ t('tables', 'Maximum text length') }}
			</div>
			<div class="fix-col-4">
				<input v-model="getMaxLength"
					type="number"
					step="1"
					min="0">
			</div>
		</div>
	</div>
</template>

<script>

import { translate as t } from '@nextcloud/l10n'

export default {

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

	computed: {
		getMaxLength() {
			return this.mutableColumn.textMaxLength > 0 ? this.mutableColumn.textMaxLength : null
		},
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
