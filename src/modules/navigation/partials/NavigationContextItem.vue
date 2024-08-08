<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcAppNavigationItem v-if="context" data-cy="navigationContextItem" :name="context.name"
		:class="{ active: activeContext && context.id === activeContext.id }" :force-menu="true"
		:to="'/application/' + parseInt(context.id)">
		<template #icon>
			<template v-if="context.iconName">
				<NcIconSvgWrapper :svg="icon" />
			</template>
			<template v-else>
				<TableIcon :size="20" />
			</template>
		</template>
		<template #actions>
			<NcActionButton v-if="ownsContext(context)" :close-after-click="true" data-cy="navigationContextEditBtn" @click="editContext">
				<template #icon>
					<PlaylistEdit :size="20" />
				</template>
				{{ t('tables', 'Edit application') }}
			</NcActionButton>
			<NcActionButton v-if="ownsContext(context)" :close-after-click="true" @click="transferContext">
				<template #icon>
					<FileSwap :size="20" />
				</template>
				{{ t('tables', 'Transfer application') }}
			</NcActionButton>
			<NcActionButton v-if="ownsContext(context)" :close-after-click="true" data-cy="navigationContextDeleteBtn" @click="deleteContext">
				<template #icon>
					<Delete :size="20" />
				</template>
				{{ t('tables', 'Delete application') }}
			</NcActionButton>
		</template>
	</NcAppNavigationItem>
</template>
<script>
import { NcAppNavigationItem, NcActionButton, NcIconSvgWrapper } from '@nextcloud/vue'
import '@nextcloud/dialogs/dist/index.css'
import { mapGetters } from 'vuex'
import TableIcon from 'vue-material-design-icons/Table.vue'
import { emit } from '@nextcloud/event-bus'
import PlaylistEdit from 'vue-material-design-icons/PlaylistEdit.vue'
import FileSwap from 'vue-material-design-icons/FileSwap.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import permissionsMixin from '../../../shared/components/ncTable/mixins/permissionsMixin.js'
import svgHelper from '../../../shared/components/ncIconPicker/mixins/svgHelper.js'

export default {
	name: 'NavigationContextItem',

	components: {
		PlaylistEdit,
		FileSwap,
		TableIcon,
		Delete,
		NcIconSvgWrapper,
		NcAppNavigationItem,
		NcActionButton,
	},

	mixins: [permissionsMixin, svgHelper],

	props: {
		context: {
			type: Object,
			default: null,
		},
	},

	data() {
		return {
			icon: null,
		}
	},
	computed: {
		...mapGetters(['activeContext']),
	},

	watch: {
		'context.iconName': {
			async handler() {
				this.icon = await this.getContextIcon(this.context.iconName)
			},
			immediate: true,
		},
	},

	methods: {
		emit,
		async editContext() {
			emit('tables:context:edit', this.context.id)
		},
		async transferContext() {
			emit('tables:context:transfer', this.context)
		},
		deleteContext() {
			emit('tables:context:delete', this.context)
		},
	},

}
</script>
