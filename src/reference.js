import { registerCustomPickerElement, registerWidget, NcCustomPickerRenderResult } from '@nextcloud/vue/dist/Components/NcRichText.js'
import Vue from 'vue'

import TablesSmartPicker from './views/SmartPicker.vue'

__webpack_nonce__ = btoa(OC.requestToken) // eslint-disable-line
__webpack_public_path__ = OC.linkTo('tables', 'js/') // eslint-disable-line

registerWidget('tables_link', async (el, { richObjectType, richObject, accessible }) => {
	const { default: Vue } = await import(/* webpackChunkName: "reference-table-lazy" */'vue')
	const { default: TableReferenceWidget } = await import(/* webpackChunkName: "reference-table-lazy" */'./views/LinkReferenceWidget.vue')
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

registerWidget('tables_content', async (el, { richObjectType, richObject, accessible }) => {
	const { default: Vue } = await import(/* webpackChunkName: "reference-table-lazy" */'vue')
	const { default: TableReferenceWidget } = await import(/* webpackChunkName: "reference-table-lazy" */'./views/ContentReferenceWidget.vue')
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

registerWidget('tables_row', async (el, { richObjectType, richObject, accessible }) => {
	const { default: Vue } = await import(/* webpackChunkName: "reference-table-lazy" */'vue')
	const { default: TableReferenceWidget } = await import(/* webpackChunkName: "reference-table-lazy" */'./views/RowReferenceWidget.vue')
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

registerCustomPickerElement('tables-ref-tables', (el, { providerId, accessible }) => {
	const Element = Vue.extend(TablesSmartPicker)
	const vueElement = new Element({
		propsData: {
		},
	}).$mount(el)
	return new NcCustomPickerRenderResult(vueElement.$el, vueElement)
}, (el, renderResult) => {
	renderResult.object.$destroy()
})
