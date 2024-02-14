import Vue from 'vue'
import Router from 'vue-router'
import { generateUrl } from '@nextcloud/router'
import MainViewWrapper from './pages/View.vue'
import MainDashboardWrapper from './pages/Table.vue'
import Startpage from './pages/Startpage.vue'
import Context from './pages/Context.vue'

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
			path: '/context/:contextId',
			component: Context,
			name: 'context',
		},
		{
			path: '/context/:contextId/row/:rowId',
			component: Context,
			name: 'contextRow',
		},
		{
			path: '/table/:tableId',
			component: MainDashboardWrapper,
			name: 'table',
		},
		{
			path: '/table/:tableId/content',
			component: MainDashboardWrapper,
			name: 'table',
		},
		{
			path: '/table/:tableId/row/:rowId',
			component: MainDashboardWrapper,
			name: 'tableRow',
		},
		{
			path: '/view/:viewId',
			component: MainViewWrapper,
			name: 'view',
		},
		{
			path: '/view/:viewId/content',
			component: MainViewWrapper,
			name: 'view',
		},
		{
			path: '/view/:viewId/row/:rowId',
			component: MainViewWrapper,
			name: 'viewRow',
		},
	],
})