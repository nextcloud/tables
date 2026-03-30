/*!
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: MIT
 */

import {
	startNextcloud,
	stopNextcloud,
} from '@nextcloud/e2e-test-server/docker'
import { readFileSync } from 'fs'
import { execSync } from 'node:child_process'

async function start() {
	let branch = process.env.SERVER_BRANCH || 'stable33'

	if (!branch) {
		const appinfo = readFileSync('appinfo/info.xml').toString()
		const maxVersion = appinfo.match(
			/<nextcloud min-version="\d+" max-version="(\d\d+)" \/>/,
		)?.[1]

		branch = 'stable33'
		if (maxVersion) {
			const refs = execSync('git ls-remote --refs').toString('utf-8')
			branch = refs.includes(`refs/heads/stable${maxVersion}`)
				? `stable${maxVersion}`
				: branch
		}
	}

	return await startNextcloud(branch, true, {
		exposePort: 8089,
		forceRecreate: true,
	})
}

async function stop() {
	process.stderr.write('Stopping Nextcloud server…\n')
	await stopNextcloud()
	// eslint-disable-next-line n/no-process-exit
	process.exit(0)
}

// Start the Nextcloud docker container
await start()

// Listen for process to exit (tests done) and shut down the docker container
process.on('SIGINT', stop)
process.on('SIGTERM', stop)

// Idle to wait for shutdown
while (true) {
	await new Promise((resolve) => setTimeout(resolve, 5000))
}
