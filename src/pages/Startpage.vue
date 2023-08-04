<template>
	<div>
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
import MainModals from '../modules/main/modals/Modals.vue'

export default {
	components: {
		NcEmptyContent,
		NcButton,
		IconTables,
		MainModals,
	},
	methods: {
		addTable() {
			emit('tables:table:create', {})
		},
	},
}
</script>
