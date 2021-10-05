<template>
	<div>
		<h2>
			{{ activeTable.title }}&nbsp;
			<Actions>
				<ActionButton @click="showCreateColumn = true">
					<template #icon>
						<TableColumnPlusAfter :size="20" decorative title="" />
					</template>
					{{ t('tables', 'Add a new column') }}
				</ActionButton>
				<ActionButton>
					<template #icon>
						<ViewColumnOutline :size="20" decorative title="" />
					</template>
					{{ t('tables', 'Edit columns') }}
				</ActionButton>
			</Actions>
		</h2>
		<CreateColumn :show-modal="showCreateColumn" @close="showCreateColumn = false" />
	</div>
</template>

<script>
import { mapState, mapGetters } from 'vuex'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import TableColumnPlusAfter from 'vue-material-design-icons/TableColumnPlusAfter'
import ViewColumnOutline from 'vue-material-design-icons/ViewColumnOutline'
import CreateColumn from '../../modals/CreateColumn'

export default {
	name: 'TableDescription',
	components: {
		Actions,
		ActionButton,
		TableColumnPlusAfter,
		ViewColumnOutline,
		CreateColumn,
	},
	data() {
		return {
			showCreateColumn: false,
		}
	},
	computed: {
		...mapState([]),
		...mapGetters(['activeTable']),
	},
}
</script>
