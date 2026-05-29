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
import { createServer } from 'node:http'

const TEXT_REPOSITORY = 'https://github.com/nextcloud/text.git'
const READY_HOST = '127.0.0.1'
const READY_PORT = Number.parseInt(process.env.PLAYWRIGHT_READY_PORT ?? '18089', 10)
let readyServer = null

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
	await closeReadyServer()
	await stopNextcloud()
	// eslint-disable-next-line n/no-process-exit
	process.exit(0)
}

async function startReadyServer() {
	await new Promise((resolve, reject) => {
		readyServer = createServer((request, response) => {
			if (request.url === '/ready') {
				response.writeHead(200, { 'content-type': 'text/plain' })
				response.end('ready')
				return
			}

			response.writeHead(404, { 'content-type': 'text/plain' })
			response.end('not found')
		})
		readyServer.once('error', reject)
		readyServer.listen(READY_PORT, READY_HOST, () => {
			readyServer.off('error', reject)
			resolve()
		})
	})
}

async function closeReadyServer() {
	if (!readyServer) {
		return
	}

	await new Promise((resolve) => {
		readyServer.close(() => resolve())
	})
	readyServer = null
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
await startReadyServer()
process.stdout.write('Tables Playwright environment is ready\n')

// Idle to wait for shutdown
await new Promise(() => {})
