<template>
	<NcAppNavigationItem v-if="context" data-cy="navigationContextItem" :name="context.name"
		:class="{ active: activeContext && context.id === activeContext.id }" :force-menu="true"
		:to="'/application/' + parseInt(context.id)">
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
			<NcActionButton :close-after-click="true" @click="editContext">
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
import { mapGetters, mapState } from 'vuex'
import Table from 'vue-material-design-icons/Table.vue'
import { emit } from '@nextcloud/event-bus'
import PlaylistEdit from 'vue-material-design-icons/PlaylistEdit.vue'
import { NODE_TYPE_TABLE, NODE_TYPE_VIEW } from '../../../shared/constants.js'

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
		...mapState(['tables', 'views']),
	},
	methods: {
		emit,
		async editContext() {
			const resources = []
			if (this.context) {
				// Format resources for selection dropdown
				const nodes = Object.values(this.context.nodes)
				for (const node of nodes) {
					if (parseInt(node.node_type) === NODE_TYPE_TABLE || parseInt(node.node_type) === NODE_TYPE_VIEW) {
						const element = parseInt(node.node_type) === NODE_TYPE_TABLE ? this.tables.find(t => t.id === node.id) : this.views.find(v => v.id === node.id)
						if (element) {
							const elementKey = parseInt(node.node_type) === NODE_TYPE_TABLE ? 'table-' : 'view-'
							const resource = {
								title: element.title,
								emoji: element.emoji,
								key: `${elementKey}` + element.id,
								nodeType: parseInt(node.node_type) === NODE_TYPE_TABLE ? NODE_TYPE_TABLE : NODE_TYPE_VIEW,
								id: (element.id).toString(),
							}
							resources.push(resource)
						}

					}
				}
			}
			emit('tables:context:edit', { context: this.context, resources })
		},
	},

}
</script>
