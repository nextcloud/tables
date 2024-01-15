import { registerCustomPickerElement, registerWidget, NcCustomPickerRenderResult } from '@nextcloud/vue/dist/Components/NcRichText.js'
import Vue from 'vue'

import TablesSmartPicker from './views/SmartPicker.vue'

__webpack_nonce__ = btoa(OC.requestToken) // eslint-disable-line
__webpack_public_path__ = OC.linkTo('tables', 'js/') // eslint-disable-line

const renderWidget = async (el, { richObjectType, richObject, accessible, interactive }) => {
	const { default: Vue } = await import(/* webpackChunkName: "reference-table-lazy" */'vue')
	const { default: TableReferenceWidget } = interactive
		? await import(/* webpackChunkName: "reference-table-lazy" */'./views/ContentReferenceWidget.vue')
		: await import(/* webpackChunkName: "reference-table-lazy" */'./views/LinkReferenceWidget.vue')
	Vue.mixin({ methods: { t, n } })
	const Widget = Vue.extend(TableReferenceWidget)
	new Widget({
		propsData: {
			richObjectType,
			richObject,
			accessible,
		},
	}).$mount(el)
}

registerWidget('tables_link', renderWidget)

registerWidget('tables_content', renderWidget)

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
