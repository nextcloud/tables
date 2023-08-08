<template>
	<div>
		<TableSmartPicker />
		<NcEmptyContent :title="t('tables', 'Tables')"
			:description="t('tables', 'Manage data the way you need it.')">
			<template #icon>
				<IconTables />
			</template>
			<template #action>
				<NcButton :aria-label="t('tables', 'Create new table')" type="primary" @click="addTable">
					{{ t('tables', 'Create new table') }}
				</NcButton>
			</template>
		</NcEmptyContent>
		<MainModals />
	</div>
</template>

<script>
import { NcEmptyContent, NcButton } from '@nextcloud/vue'
import IconTables from '../shared/assets/icons/IconTables.vue'
import { emit } from '@nextcloud/event-bus'
import MainModals from '../modules/modals/Modals.vue'
import TableSmartPicker from '../views/TableSmartPicker.vue'

export default {
	components: {
		NcEmptyContent,
		NcButton,
		IconTables,
		MainModals,
		TableSmartPicker,
	},
	methods: {
		addTable() {
			emit('tables:table:create', {})
		},
	},
}
</script>
