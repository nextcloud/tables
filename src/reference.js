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
}, () => {}, { hasInteractiveView: true })

registerCustomPickerElement('tables-ref-tables', async (el, { providerId, accessible }) => {
	const { default: Vue } = await import('vue')
	const { default: TablesSmartPicker } = await import('./views/SmartPicker.vue')
	const Element = Vue.extend(TablesSmartPicker)
	const vueElement = new Element({
		propsData: {
		},
	}).$mount(el)
	return new NcCustomPickerRenderResult(vueElement.$el, vueElement)
}, (el, renderResult) => {
	renderResult.object.$destroy()
})
