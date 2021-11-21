import Vue from 'vue'
import Router from 'vue-router'
import { generateUrl } from '@nextcloud/router'
import App from './App'

Vue.use(Router)

export default new Router({
	base: generateUrl('/apps/tables/'),
	linkActiveClass: 'active',
	routes: [
		{
			path: '/table/:tableId',
			components: App,
			props: {
				default: (route) => {
					return {
						tableId: route.params.tableId,
					}
				},
			},
		},
	],
})
