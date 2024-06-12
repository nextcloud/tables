export default {
	methods: {
		uriToSvg(dataUri) {
			const pattern = /data:image\/svg\+xml;base64,/
			const strippedUri = dataUri.replace(pattern, '')

			return atob(strippedUri)
		},
		async getContextIcon(iconName) {
			const { default: icon } = await import(
				/* webpackChunkName: 'material-icons' */
				/* webpackMode: 'lazy-once' */
				`MaterialIcons/${iconName}.svg`
			)

			return this.uriToSvg(icon).replaceAll(/#fff/g, 'currentColor')
		},
	},
}
