import Vue from 'vue'
import Router from 'vue-router'
import { generateUrl } from '@nextcloud/router'
import TableDefaultView from './pages/TableDefaultView'
import TablesOverviewView from './pages/TablesOverviewView'

Vue.use(Router)

export default new Router({
	base: generateUrl('/apps/tables/'),
	linkActiveClass: 'active',
	routes: [
		{
			path: '/',
			component: TablesOverviewView,
		},
		{
			path: '/table/:tableId',
			component: TableDefaultView,
			name: 'table',
		},
	],
})
