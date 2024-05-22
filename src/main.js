/**
 * @copyright Copyright (c) 2018 John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @author John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
import { generateFilePath } from '@nextcloud/router'
import Vue from 'vue'
import App from './App.vue'
import Vuex from 'vuex'
import store from './store/store.js'
import router from './router.js'
import VuePapaParse from 'vue-papa-parse'

__webpack_nonce__ = btoa(OC.requestToken) // eslint-disable-line
// eslint-disable-next-line
__webpack_public_path__ = generateFilePath('tables', '', 'js/')

Vue.mixin({ methods: { t, n } })
Vue.use(Vuex)
Vue.use(VuePapaParse)

export default new Vue({
	el: '#content',
	router,
	store,
	render: h => h(App),
})
