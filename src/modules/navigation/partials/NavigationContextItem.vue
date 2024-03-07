<template>
	<NcAppNavigationItem v-if="context"
		data-cy="navigationContextItem"
		:name="context.name"
		:class="{active: activeContext && context.id === activeContext.id}"
		:force-menu="true"
		:to="'/context/' + parseInt(context.id)">
		<template #icon>
			<template v-if="context.iconName">
				{{ context.iconName }}
			</template>
			<template v-else>
				<Table :size="20" />
			</template>
		</template>
		<template #actions>
			<!-- TODO check if current user has right permissions before showing options -->
			<NcActionButton
				:close-after-click="true"
				@click="editContext">
				<template #icon>
					<PlaylistEdit :size="20" />
				</template>
				{{ t('tables', 'Edit context') }}
			</NcActionButton>
		</template>
	</NcAppNavigationItem>
</template>
<script>
import { NcAppNavigationItem, NcActionButton } from '@nextcloud/vue'
import '@nextcloud/dialogs/dist/index.css'
import { mapGetters } from 'vuex'
import Table from 'vue-material-design-icons/Table.vue'
import { emit } from '@nextcloud/event-bus'
import PlaylistEdit from 'vue-material-design-icons/PlaylistEdit.vue'

export default {
	name: 'NavigationContextItem',

	components: {
		PlaylistEdit,
		// eslint-disable-next-line vue/no-reserved-component-names
		Table,
		NcAppNavigationItem,
		NcActionButton,
	},

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
			emit('tables:context:edit', this.context)
		},
	},

}
</script>
