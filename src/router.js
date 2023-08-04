import Vue from 'vue'
import Router from 'vue-router'
import { generateUrl } from '@nextcloud/router'
import MainViewWrapper from './pages/View.vue'
import MainDashboardWrapper from './pages/Table.vue'
import Startpage from './pages/Startpage.vue'

Vue.use(Router)

export default new Router({
	base: generateUrl('/apps/tables/'),
	linkActiveClass: 'active',
	routes: [
		{
			path: '/',
			component: Startpage,
		},
		{
			path: '/table/:tableId',
			component: MainDashboardWrapper,
			name: 'table',
		},
		{
			path: '/view/:viewId',
			component: MainViewWrapper,
			name: 'view',
		},
		{
			path: '/view/:viewId/row/:rowId',
			component: MainViewWrapper,
			name: 'row',
		},
	],
})
