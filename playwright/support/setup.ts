/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import {
	configureNextcloud,
	getContainer,
	runExec,
	runOcc,
} from '@nextcloud/e2e-test-server'
import { test as setup } from '@playwright/test'

const TEXT_REPOSITORY = 'https://github.com/nextcloud/text.git'
type NextcloudContainer = ReturnType<typeof getContainer>

async function moveAppAside(
	container: NextcloudContainer,
	sourcePath: string,
	targetPathPrefix: string,
) {
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

async function installVendoredText(container: NextcloudContainer, vendoredBranch: string) {
	await runOcc(['app:disable', 'text'], { container }).catch(
		() => {
			// Text might not be registered yet, which is fine for a fresh container.
		},
	)

	await moveAppAside(
		container,
		'/var/www/html/apps/text',
		'/var/www/html/apps/text.server',
	)
	await moveAppAside(
		container,
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

/**
 * We use this to ensure Nextcloud is configured correctly before running our tests
 *
 * This can not be done in the webserver startup process,
 * as that only checks for the URL to be accessible which happens already before everything is configured.
 */
setup('Configure Nextcloud', async () => {
	// Default local runs to stable33; CI can still override with SERVER_BRANCH.
	const serverBranch = process.env.SERVER_BRANCH || 'stable33'
	const vendoredBranch = serverBranch === 'master' ? 'main' : serverBranch
	const container = getContainer()

	await configureNextcloud(['tables'], vendoredBranch, container)
	await installVendoredText(container, vendoredBranch)
})
