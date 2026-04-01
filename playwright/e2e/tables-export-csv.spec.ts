/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test, expect } from '../support/fixtures'
import * as fs from 'fs'
import { clickOnTableThreeDotMenu, getTutorialTableName, loadTable } from '../support/commands'

test.describe('Import csv', () => {

	test('Export csv', async ({ userPage: { page } }) => {
		await page.goto('/index.php/apps/tables')
		await loadTable(page, 'Welcome to Nextcloud Tables!')

		const tutorialName = await getTutorialTableName(page)
		const fileNamePattern = new RegExp(`^\\d{2}-\\d{2}-\\d{2}_\\d{2}-\\d{2}_${tutorialName.replace(/[.*+?^${}()|[\\]\\\\]/g, '\\\\$&')}\\.csv$`)

		const [download] = await Promise.all([
			page.waitForEvent('download'),
			clickOnTableThreeDotMenu(page, 'Export as CSV'),
		])

		expect(download.suggestedFilename()).toMatch(fileNamePattern)

		const path = await download.path()
		const content = fs.readFileSync(path, 'utf8')

		expect(content).toContain('What,How to do,Ease of use,Done')
		expect(content).toContain('Open the tables app,Reachable via the Tables icon in the apps list.,5,true')
	})
})
