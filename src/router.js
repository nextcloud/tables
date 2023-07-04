import Vue from 'vue'
import Router from 'vue-router'
import { generateUrl } from '@nextcloud/router'
import DefaultViewMainView from './pages/DefaultViewMainView.vue'
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
			component: DefaultViewMainView,
			name: 'view',
		},
	],
})
