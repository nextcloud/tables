const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')

webpackConfig.entry = {
	main: path.join(__dirname, 'src', 'main.js'),
	files: path.join(__dirname, 'src', 'file-actions.js'),
	reference: path.join(__dirname, 'src', 'reference.js'),
}

webpackConfig.module = {
	rules: [
		...webpackConfig.module.rules,
		{
			resourceQuery: /raw/,
			type: 'asset/source',
		}
	]
}

module.exports = webpackConfig
