export default {
	methods: {
		async getContextIcon(iconName) {
			const { default: icon } = await import(
				`./../../../../../img/material/${iconName}.svg?raw`
			)

			return icon.replaceAll(/#fff/g, 'currentColor')
		},
	},
}
