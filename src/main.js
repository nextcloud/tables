/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createPinia } from 'pinia'
import { createApp } from 'vue'
import App from './App.vue'
import router from './router.js'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'

const pinia = createPinia()

const app = createApp(App)
app.config.globalProperties.t = t
app.config.globalProperties.n = n
app.use(pinia)
app.use(router)
app.mount('#content')

export default app
