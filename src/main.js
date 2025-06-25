/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createPinia, PiniaVuePlugin } from 'pinia'
import Vue from 'vue'
import App from './App.vue'
import router from './router.js'
import VuePapaParse from 'vue-papa-parse'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'

Vue.mixin({ methods: { t, n } })
Vue.use(PiniaVuePlugin)
const pinia = createPinia()
Vue.use(VuePapaParse)

export default new Vue({
	el: '#content',
	router,
	pinia,
	render: h => h(App),
})
