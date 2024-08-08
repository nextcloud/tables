<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcEmptyContent :name="t('tables', 'No columns')"
		:description="t('tables', 'We need at least one column, please be so kind and create one.')">
		<template #icon>
			{{ table.emoji }}
		</template>
		<template v-if="canManageTable(table)" #action>
			<NcButton :aria-label="t('table', 'Create column')" type="primary" @click="createColumn()">
				{{ t('tables', 'Create column') }}
			</NcButton>
		</template>
	</NcEmptyContent>
</template>
<script>
import { NcEmptyContent, NcButton } from '@nextcloud/vue'
import { emit } from '@nextcloud/event-bus'
import permissionsMixin from '../../../shared/components/ncTable/mixins/permissionsMixin.js'

export default {
	name: 'EmptyTable',
	components: {
		NcEmptyContent,
		NcButton,
	},
	mixins: [permissionsMixin],
	props: {
		table: {
			type: Object,
			default: null,
		},
	},
	methods: {
		createColumn() {
			emit('tables:column:create', { isView: false, element: this.table })
		},
	},

}
</script>

<style scoped>

:deep(.empty-content__icon) {
	font-size: xxx-large;
	opacity: 1;
}

.empty-content {
	margin-top: 20vh;
}

</style>
