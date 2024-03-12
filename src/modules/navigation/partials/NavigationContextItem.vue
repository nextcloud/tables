<template>
	<NcAppNavigationItem v-if="context" data-cy="navigationContextItem" :name="context.name"
		:class="{ active: activeContext && context.id === activeContext.id }" :force-menu="true"
		:to="'/application/' + parseInt(context.id)">
		<template #icon>
			<template v-if="context.iconName">
				{{ context.iconName }}
			</template>
			<template v-else>
				<TableIcon :size="20" />
			</template>
		</template>
		<template #actions>
			<NcActionButton v-if="canManageContext(context)" :close-after-click="true" @click="editContext">
				<template #icon>
					<PlaylistEdit :size="20" />
				</template>
				{{ t('tables', 'Edit application') }}
			</NcActionButton>
		</template>
	</NcAppNavigationItem>
</template>
<script>
import { NcAppNavigationItem, NcActionButton } from '@nextcloud/vue'
import '@nextcloud/dialogs/dist/index.css'
import { mapGetters } from 'vuex'
import TableIcon from 'vue-material-design-icons/Table.vue'
import { emit } from '@nextcloud/event-bus'
import PlaylistEdit from 'vue-material-design-icons/PlaylistEdit.vue'
import permissionsMixin from '../../../shared/components/ncTable/mixins/permissionsMixin.js'

export default {
	name: 'NavigationContextItem',

	components: {
		PlaylistEdit,
		TableIcon,
		NcAppNavigationItem,
		NcActionButton,
	},

	mixins: [permissionsMixin],

	props: {
		context: {
			type: Object,
			default: null,
		},
	},

	computed: {
		...mapGetters(['activeContext']),
	},
	methods: {
		emit,
		async editContext() {
			emit('tables:context:edit', this.context.id)
		},
	},

}
</script>
