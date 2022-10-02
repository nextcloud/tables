<template>
	<div>
		<div class="row first-row">
			<h1>
				{{ activeTable.title }}&nbsp;
				<NcActions>
					<NcActionButton v-if="canManageActiveTable" :close-after-click="true" @click="showCreateColumn = true">
						<template #icon>
							<TableColumnPlusAfter :size="20" decorative title="" />
						</template>
						{{ t('tables', 'Add a new column') }}
					</NcActionButton>
					<NcActionButton v-if="canManageActiveTable" :close-after-click="true" @click="showEditColumns = true">
						<template #icon>
							<ViewColumnOutline :size="20" decorative title="" />
						</template>
						{{ t('tables', 'Edit columns') }}
					</NcActionButton>
					<NcActionButton v-if="!activeTable.isShared"
						:close-after-click="true"
						icon="icon-share"
						@click="$store.commit('setShowSidebar', true); $store.commit('setSidebarActiveTab', 'share')">
						{{ t('tables', 'Sharing options') }}
					</NcActionButton>
				</NcActions>
			</h1>
		</div>
		<div v-if="!loading" class="row space-LR space-T">
			<p v-if="!columns || columns.length === 0">
				{{ t('tables', 'There are no columns yet, click on the three-dot menu next to the table title ahead and create some.') }}
			</p>
			<CreateColumn :show-modal="showCreateColumn"
				@close="showCreateColumn = false; $emit('reload')" />
			<EditColumns :show-modal="showEditColumns" @close="showEditColumns = false; $emit('reload')" />
		</div>
	</div>
</template>

<script>
import { mapState, mapGetters } from 'vuex'
import { NcActions, NcActionButton } from '@nextcloud/vue'
import TableColumnPlusAfter from 'vue-material-design-icons/TableColumnPlusAfter.vue'
import ViewColumnOutline from 'vue-material-design-icons/ViewColumnOutline.vue'
import CreateColumn from '../../modals/CreateColumn.vue'
import EditColumns from '../../modals/EditColumns.vue'
import tablePermissions from '../../mixins/tablePermissions.js'

export default {
	name: 'TableDescription',
	components: {
		NcActions,
		NcActionButton,
		TableColumnPlusAfter,
		ViewColumnOutline,
		CreateColumn,
		EditColumns,
	},
	mixins: [tablePermissions],
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
			this.showCreateColumn = true
		}
	},
}
</script>
