<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Nextcloud App »Tables«

[![REUSE status](https://api.reuse.software/badge/github.com/nextcloud/tables)](https://api.reuse.software/info/github.com/nextcloud/tables)

**Manage data the way you need it.**

With this app you are able to create your own tables with individual columns. You can start with a template or from scratch and add your wanted columns.

## Documentation

### General information
https://github.com/nextcloud/tables/wiki

### Administration
https://github.com/nextcloud/tables/wiki/Administration

### API
https://github.com/nextcloud/tables/wiki/API

### Developer information
https://github.com/nextcloud/tables/wiki/Developing

## Installation/Update
The app can be installed through the [app store](https://apps.nextcloud.com/apps/tables) within Nextcloud. You can also download the latest release from the [release page](https://github.com/nextcloud-releases/tables/releases).

### Install from source code
*To build you will need to have [Node.js](https://nodejs.org/en/) and [Composer](https://getcomposer.org/) installed.*

- Clone repository into the app-directory: `cd /path/to/apps && git clone https://github.com/nextcloud/tables && cd tables`
- Install PHP dependencies: `composer install --no-dev`
- Install JS dependencies: `npm ci`
- Build JavaScript for the frontend
	- Development build `npm run dev` or
	- Watch for changes `npm run watch` or
	- Production build `npm run build`
