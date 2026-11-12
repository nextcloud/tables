<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Tables documentation

Manage structured data in Nextcloud. Create tables, choose column types, filter them with views, share them, and group tables and views into applications.

## Using Tables

- [Getting started](getting-started.md)
- [Columns](columns.md)
- [Rows](rows.md)
- [Views](views.md)
- [Sharing](sharing.md)
- [Applications](applications.md)
- [Import and export](import-export.md)
- [Activity and integrations](activity.md)

## Administration

- [Administration](administration.md) covers the database requirement, scale limits, and `occ` commands.
- [permissions-matrix.xlsx](permissions-matrix.xlsx) is the internal permissions sheet for table, column, row, and share actions.

## API and development

The OpenAPI description is [`openapi.json`](../openapi.json) in the repository root. You can browse it with the [OCS API Viewer](https://apps.nextcloud.com/apps/ocs_api_viewer).

Developer setup, column internals, and filter payloads are on the [Developing wiki page](https://github.com/nextcloud/tables/wiki/Developing).
