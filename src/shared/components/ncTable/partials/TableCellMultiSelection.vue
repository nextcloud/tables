<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		<ul>
			<li v-for="v in getObjects()" :key="v.id">
				{{ v.label }}<span v-if="v.deleted" :title="t('tables', 'This option is outdated.')">&nbsp;⚠️</span>
			</li>
		</ul>
	</div>
</template>

<script>

import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'TableCellMultiSelection',

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
			type: Array,
			default: null,
		},
	},
	methods: {
		t,
		getObjects() {
			return this.column.getObjects(this.value)
		},
	},

}
</script>
<style lang="scss" scoped>

ul {
	list-style-type: disc;
	padding-left: calc(var(--default-grid-baseline) * 3);
}

</style>
