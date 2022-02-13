import Vue from 'vue'
import Router from 'vue-router'
import { generateUrl } from '@nextcloud/router'

Vue.use(Router)

export default new Router({
	base: generateUrl('/apps/tables/'),
	linkActiveClass: 'active',
	routes: [
		{
			path: '/table/:tableId',
			name: 'table',
		},
	],
})
