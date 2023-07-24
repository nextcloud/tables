import Vue from 'vue'
import Router from 'vue-router'
import { generateUrl } from '@nextcloud/router'
import DefaultMainView from './pages/DefaultMainView.vue'
import Redirect from './pages/Redirect.vue'
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
			path: '/view/:viewId',
			component: DefaultMainView,
			name: 'view',
		},
		{
			path: '/view/:viewId/row/:rowId',
			component: DefaultMainView,
			name: 'row',
		},
		// Path for old existing links to tables. Now redirected to the basetable link
		{
			path: '/table/:tableId',
			component: Redirect,
			name: 'table',
		},
	],
})
