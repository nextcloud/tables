const { defineConfig } = require('cypress')

module.exports = defineConfig({
	projectId: 'ixbf9n',
	e2e: {
		baseUrl: 'http://nextcloud.local/index.php/',
		setupNodeEvents(on, config) {
			// implement node event listeners here
		},
	  pageLoadTimeout: 120000,
	},
})
