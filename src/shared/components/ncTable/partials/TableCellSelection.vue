<template>
	<div>
		{{ column.getLabel(value) }}<span v-if="isDeleted()" :title="t('tables', 'This option is outdated.')">&nbsp;⚠️</span>
	</div>
</template>

<script>

import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'TableCellSelection',

	props: {
		column: {
			type: Object,
			default: () => {},
		},

		rowId: {
			type: Number,
			default: null,
		},

		value: {
			type: Number,
			default: null,
		},
	},
	methods: {
		t,
		isDeleted() {
			this.column.isDeletedLabel(this.value)
		},
	},
}
</script>

<style lang="scss" scoped>

span {
	cursor: help;
}

</style>
