<template>
	<div>
		<div class="row first-row">
			<h1>
				{{ activeTable.title }}&nbsp;
				<Actions>
					<ActionButton :close-after-click="true" @click="showCreateColumn = true">
						<template #icon>
							<TableColumnPlusAfter :size="20" decorative title="" />
						</template>
						{{ t('tables', 'Add a new column') }}
					</ActionButton>
					<ActionButton :close-after-click="true" @click="showEditColumns = true">
						<template #icon>
							<ViewColumnOutline :size="20" decorative title="" />
						</template>
						{{ t('tables', 'Edit columns') }}
					</ActionButton>
					<ActionButton v-if="!activeTable.isShared"
						:close-after-click="true"
						icon="icon-share"
						@click="$store.commit('setShowSidebar', true); $store.commit('setSidebarActiveTab', 'share')">
						{{ t('tables', 'Sharing options') }}
					</ActionButton>
				</Actions>
			</h1>
		</div>
		<div v-if="!loading" class="row space-LR space-T">
			<p v-if="!columns || columns.length === 0">
				{{ t('tables', 'There are no columns yet, click on the three-dot menu next to the table title ahead and create some.') }}
			</p>
			<CreateColumn
				:show-modal="showCreateColumn"
				@close="showCreateColumn = false; $emit('reload')" />
			<EditColumns :show-modal="showEditColumns" @close="showEditColumns = false; $emit('reload')" />
		</div>
	</div>
</template>

<script>
import { mapState, mapGetters } from 'vuex'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import TableColumnPlusAfter from 'vue-material-design-icons/TableColumnPlusAfter'
import ViewColumnOutline from 'vue-material-design-icons/ViewColumnOutline'
import CreateColumn from '../../modals/CreateColumn'
import EditColumns from '../../modals/EditColumns'

export default {
	name: 'TableDescription',
	components: {
		Actions,
		ActionButton,
		TableColumnPlusAfter,
		ViewColumnOutline,
		CreateColumn,
		EditColumns,
	},
	props: {
		columns: {
			type: Array,
			default: null,
		},
		loading: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			showCreateColumn: false,
			showEditColumns: false,
		}
	},
	computed: {
		...mapState([]),
		...mapGetters(['activeTable']),
	},
	mounted() {
		if (this.columns && this.columns.length === 0) {
			// this.showCreateColumn = true
		}
	},
}
</script>
