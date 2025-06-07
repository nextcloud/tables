/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { registerCustomPickerElement, registerWidget, NcCustomPickerRenderResult } from '@nextcloud/vue/dist/Functions/registerReference.js'

registerWidget('tables_link', async (el, { richObjectType, richObject, accessible }) => {
	const { default: Vue } = await import('vue')
	const { default: TableReferenceWidget } = await import('./views/LinkReferenceWidget.vue')
	Vue.mixin({ methods: { t, n } })
	const Widget = Vue.extend(TableReferenceWidget)
	new Widget({
		propsData: {
			richObjectType,
			richObject,
			accessible,
		},
	}).$mount(el)
})

registerWidget('tables_content', async (el, { richObjectType, richObject, accessible, interactive = true }) => {
	const { default: Vue } = await import('vue')
	const { default: TableReferenceWidget } = interactive
		? await import(/* webpackChunkName: "reference-table-lazy" */'./views/ContentReferenceWidget.vue')
		: await import(/* webpackChunkName: "reference-table-lazy" */'./views/LinkReferenceWidget.vue')
	
	// Import Pinia and stores
	const { createPinia, setActivePinia } = await import('pinia')
	const { useTablesStore } = await import(/* webpackChunkName: 'store' */ './store/store.js')
	const { useDataStore } = await import(/* webpackChunkName: 'store' */ './store/data.js')

	// Create and set up Pinia instance
	const pinia = createPinia()
	setActivePinia(pinia)

	Vue.mixin({ methods: { t, n } })
	Vue.use(pinia)
	
	const Widget = Vue.extend(TableReferenceWidget)
	new Widget({
		propsData: {
			richObjectType,
			richObject,
			accessible,
		},
		pinia,
	}).$mount(el)
}, () => {}, { hasInteractiveView: true })

registerCustomPickerElement('tables-ref-tables', async (el, { providerId, accessible }) => {
	const { default: Vue } = await import('vue')
	const { default: TablesSmartPicker } = await import('./views/SmartPicker.vue')
	
	// Import Pinia
	const { createPinia } = await import('pinia')
	const pinia = createPinia()
	
	Vue.use(pinia)
	
	const Element = Vue.extend(TablesSmartPicker)
	const vueElement = new Element({
		propsData: {
		},
	}).$mount(el)
	return new NcCustomPickerRenderResult(vueElement.$el, vueElement)
}, (el, renderResult) => {
	renderResult.object.$destroy()
})
