/*!
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: MIT
 */

import {
	configureNextcloud,
	getContainer,
	runExec,
	runOcc,
	startNextcloud,
	stopNextcloud,
	waitOnNextcloud,
} from '@nextcloud/e2e-test-server/docker'
import { readFileSync } from 'fs'
import { execSync } from 'node:child_process'

const TEXT_REPOSITORY = 'https://github.com/nextcloud/text.git'

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

async function moveAppAside(sourcePath, targetPathPrefix) {
	const container = getContainer()

	try {
		await runExec(['test', '-d', sourcePath], { container })
		const backupPath = `${targetPathPrefix}-${Date.now()}`
		await runExec(['mv', sourcePath, backupPath], {
			container,
			user: 'root',
		})
	} catch {
		// App path does not exist in this image, so there is nothing to move aside.
	}
}

async function installVendoredText(vendoredBranch) {
	const container = getContainer()

	await runOcc(['app:disable', 'text'], { container }).catch(() => {
		// Text might not be registered yet, which is fine for a fresh container.
	})

	await moveAppAside(
		'/var/www/html/apps/text',
		'/var/www/html/apps/text.server',
	)
	await moveAppAside(
		'/var/www/html/apps-extra/text',
		'/var/www/html/apps-extra/text.server',
	)

	await runExec(
		[
			'git',
			'clone',
			'--depth=1',
			`--branch=${vendoredBranch}`,
			TEXT_REPOSITORY,
			'apps/text',
		],
		{ container, verbose: true },
	)
	await runOcc(['app:enable', '--force', 'text'], {
		container,
		verbose: true,
	})
}

async function stop() {
	process.stderr.write('Stopping Nextcloud server…\n')
	await stopNextcloud()
	// eslint-disable-next-line n/no-process-exit
	process.exit(0)
}

process.on('SIGINT', stop)
process.on('SIGTERM', stop)

// Start and fully configure the Nextcloud docker container before Playwright continues.
const ip = await start()
await waitOnNextcloud(ip)

const serverBranch = process.env.SERVER_BRANCH || 'stable33'
const vendoredBranch = serverBranch === 'master' ? 'main' : serverBranch

await configureNextcloud(['tables'], vendoredBranch)
await installVendoredText(vendoredBranch)

// Idle to wait for shutdown
while (true) {
	await new Promise((resolve) => setTimeout(resolve, 5000))
}
