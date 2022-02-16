<template>
	<AppNavigation>
		<template #list>
			<CreateTable />
			<div v-if="tablesLoading" class="icon-loading" />
			<ul v-if="!tablesLoading">
				<NavigationTableItem
					v-for="table in tables"
					:key="table.id"
					:table="table" />
			</ul>
		</template>
		<template #footer>
			<AppNavigationSettings :title="t('tables', 'Donation')">
				{{ t('tables', 'This is a private project. Donations are welcome to give something back to me and my family.') }}<br>
				<a href="https://www.paypal.com/donate/?hosted_button_id=3NBB57F2WUFTN" target="_blank">» PayPal</a><br>
				<a href="https://buymeacoff.ee/iPbXoknVC" target="_blank">» Buy me a coffee</a><br>
			</AppNavigationSettings>
		</template>
	</AppNavigation>
</template>

<script>
import AppNavigation from '@nextcloud/vue/dist/Components/AppNavigation'
import CreateTable from './modals/CreateTable'
import NavigationTableItem from './NavigationTableItem'
import { mapState, mapGetters } from 'vuex'
import AppNavigationSettings from '@nextcloud/vue/dist/Components/AppNavigationSettings'

export default {
	name: 'Navigation',
	components: {
		NavigationTableItem,
		AppNavigation,
		CreateTable,
		AppNavigationSettings,
	},
	data() {
		return {
			loading: true,
			showModalAddNewTable: false,
		}
	},
	computed: {
		...mapState(['tables', 'tablesLoading']),
		...mapGetters(['activeTable']),
	},
	methods: {
	},
}
</script>
