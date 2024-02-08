module.exports = {
	root: true,
	extends: [
		'@nextcloud',
		'@nextcloud/eslint-config/typescript',
	],
	rules: {
		'jsdoc/require-jsdoc': 'off',
		'jsdoc/tag-lines': 'off',
		'vue/first-attribute-linebreak': 'off',
		'vue/max-attributes-per-line': 'off'
	}
}
