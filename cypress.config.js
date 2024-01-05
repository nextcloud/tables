const { defineConfig } = require('cypress')
const { configureNextcloud, startNextcloud, stopNextcloud, waitOnNextcloud } = require('@nextcloud/cypress/docker')

module.exports = defineConfig({
	projectId: 'ixbf9n',
	e2e: {
		baseUrl: 'http://nextcloud.local/index.php/',
		setupNodeEvents(on, config) {
			if (process.env.CYPRESS_baseUrl) {
				return config
			}
			// Remove container after run
			on('after:run', () => {
				if (!process.env.CI) {
					stopNextcloud()
				}
			})

			// starting Nextcloud testing container with specified server branch
			return startNextcloud(process.env.BRANCH)
				.then((ip) => {
					// Setting container's IP as base Url
					config.baseUrl = `http://${ip}/index.php`
					return ip
				})
				.then(waitOnNextcloud)
				// configure Nextcloud, also enable the app
				.then(() => configureNextcloud(['tables']))
				.then(() => {
					return config
				})
		},
	  pageLoadTimeout: 120000,
	},
})
