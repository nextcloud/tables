/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { registerCustomPickerElement, registerWidget, NcCustomPickerRenderResult } from '@nextcloud/vue/functions/registerReference'
import './styles/smart-picker.scss'

registerWidget('tables_link', async (el, { richObjectType, richObject, accessible }) => {
	const { createApp } = await import('vue')
	const { default: TableReferenceWidget } = await import('./views/LinkReferenceWidget.vue')
	const app = createApp(TableReferenceWidget, {
		richObjectType,
		richObject,
		accessible,
	})
	app.mixin({ methods: { t, n } })
	app.mount(el)
})

registerWidget('tables_content', async (el, { richObjectType, richObject, accessible, interactive = true }) => {
	const { createApp } = await import('vue')
	const { default: TableReferenceWidget } = interactive
		? await import('./views/ContentReferenceWidget.vue')
		: await import('./views/LinkReferenceWidget.vue')

	const { createPinia } = await import('pinia')
	const pinia = createPinia()

	const app = createApp(TableReferenceWidget, {
		richObjectType,
		richObject,
		accessible,
	})
	app.use(pinia)
	app.mixin({ methods: { t, n } })
	app.mount(el)
}, () => {}, { hasInteractiveView: true })

registerCustomPickerElement('tables-ref-tables', async (el, { providerId, accessible }) => {
	const { createApp } = await import('vue')
	const { default: TablesSmartPicker } = await import('./views/SmartPicker.vue')

	const { createPinia } = await import('pinia')
	const pinia = createPinia()

	const app = createApp(TablesSmartPicker)
	app.use(pinia)
	const vueElement = app.mount(el)
	return new NcCustomPickerRenderResult(vueElement.$el, app)
}, (el, renderResult) => {
	renderResult.object.unmount()
})
