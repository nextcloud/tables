<template>
	<AppNavigation>
		<AppNavigationNew v-if="!loading"
			:text="t('tables', 'New table')"
			:disabled="false"
			button-class="icon-add"
			@click="showModalAddNewTable = true" />
		<ul>
			<AppNavigationItem v-for="table in tables"
				:key="table.id"
				:title="table.title"
				:class="{active: table.id === activeTableId}">
				<template slot="actions">
					<ActionButton
						icon="icon-close">
						{{ t('tables', 'Cancel table creation') }}
					</ActionButton>
					<ActionButton
						icon="icon-delete">
						{{ t('tables', 'Delete table') }}
					</ActionButton>
				</template>
			</AppNavigationItem>
		</ul>
		<Modal
			v-if="showModalAddNewTable" />
	</AppNavigation>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import AppNavigation from '@nextcloud/vue/dist/Components/AppNavigation'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import AppNavigationNew from '@nextcloud/vue/dist/Components/AppNavigationNew'
import Modal from '@nextcloud/vue/dist/Components/Modal'

export default {
	name: 'Navigation',
	components: {
		ActionButton,
		AppNavigation,
		AppNavigationItem,
		AppNavigationNew,
		Modal,
	},
	data() {
		return {
			loading: true,
			tables: [],
			activeTableId: null,
			showModalAddNewTable: false,
		}
	},
	async mounted() {
		try {
			const response = await axios.get(generateUrl('/apps/tables/table'))
			this.tables = response.data
		} catch (e) {
			console.error(e)
			showError(t('tables', 'Could not fetch tables'))
		}
		this.loading = false
	},
}
</script>
