<template>
	<NcAppNavigationItem v-if="context"
		data-cy="navigationContextItem"
		:name="context.name"
		:class="{active: activeContext && context.id === activeContext.id}"
		:force-menu="true"
		:to="'/context/' + parseInt(context.id)">
		<template #icon>
			<template v-if="context.emoji">
				{{ context.emoji }}
			</template>
			<template v-else>
				<Table :size="20" />
			</template>
		</template>
		<template #actions>
			<NcActionButton
				:close-after-click="true"
				@click="editContext">
				<template #icon>
					<PlaylistEdit :size="20" />
				</template>
				{{ t('tables', 'Edit context') }}
			</NcActionButton>
			<NcActionButton
				icon="icon-delete"
				:close-after-click="true"
				@click="emit('tables:context:delete', context)">
				{{ t('tables', 'Delete context') }}
			</NcActionButton>
		</template>
	</NcAppNavigationItem>
</template>
<script>
import { NcAppNavigationItem, NcActionButton, NcCounterBubble, NcAvatar } from '@nextcloud/vue'
import '@nextcloud/dialogs/dist/index.css'
import { getCurrentUser } from '@nextcloud/auth'
import { mapGetters } from 'vuex'
import { showError } from '@nextcloud/dialogs'
import Table from 'vue-material-design-icons/Table.vue'
import permissionsMixin from '../../../shared/components/ncTable/mixins/permissionsMixin.js'
import Connection from 'vue-material-design-icons/Connection.vue'
import PlaylistPlay from 'vue-material-design-icons/PlaylistPlay.vue'
import { emit } from '@nextcloud/event-bus'
import PlaylistEdit from 'vue-material-design-icons/PlaylistEdit.vue'
import Import from 'vue-material-design-icons/Import.vue'

export default {
	name: 'NavigationContextItem',

	components: {
		PlaylistEdit,
		// eslint-disable-next-line vue/no-reserved-component-names
		Table,
		NcAppNavigationItem,
		NcCounterBubble,
		NcActionButton,
		Connection,
		NcAvatar,
		PlaylistPlay,
		Import,
	},

	filters: {
		truncate(string, num) {
			if (string.length >= num) {
				return string.substring(0, num) + '...'
			} else {
				return string
			}
		},
	},

	mixins: [permissionsMixin],

	props: {
		context: {
			type: Object,
			default: null,
		},
	},

	data() {
		return {
			showDeletionConfirmation: false,
		}
	},

	computed: {
		...mapGetters(['activeContext']),
		getTranslatedDescription() {
			return t('tables', 'Do you really want to delete the context "{context}"?', { context: this.context.name })
		},
		userId() {
			return getCurrentUser().uid
		},
	},
	methods: {
		emit,
		async editContext() {
			emit('tables:context:edit', { context: this.context })
		},
	},

}
</script>
