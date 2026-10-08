<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V.
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Architecture

Part of the [buildiq parity project](README.md).

## Principles

1. **Applications are Tables entities; their data are tables and rows.** An application, its menu items, its pages and their widgets are entities in Tables' own schema, `tables_contexts_*` and `tables_views`, with their own mappers, services and API routes. The data an application works on are ordinary tables, columns and rows. Neither side is stored as the other: an application is not a row in a user table, and a user's data is never hidden inside an application record. The manifest is a file format for exchange, not the storage.
2. **Tables runs on itself.** Tables depends on the Nextcloud server and on nothing else. No OpenRegister, no nextcloud-vue runtime dependency, no other app's classes, bundled or not. Anything from outside the server reaches Tables through `OCP` interfaces or through extension points Tables publishes and other apps register into. A schema is a table, a property is a column, an object is a row.
3. **Server-side definitions.** Widget types, page types, formats and validators are defined once on the server and served to the frontend, as the widget schemas already are. The form and the validation share one definition.
4. **Nothing hidden.** Every value a page shows comes from a Tables API a user could call. No private endpoints for the runtime.
5. **Upstream shape.** Code is written so nextcloud/tables can take it: Nextcloud attributes on controllers, psalm clean, generated OpenAPI, unit tests per class, Playwright for every user path.

## The application model

Three columns because the fork is ahead of Tables: what nextcloud/tables has, what the fork's open pull requests add, and what this project adds. Nothing in the last two columns is in Tables; all of it is a proposal until it is merged upstream.

| Entity | Table | In nextcloud/tables | On the fork (proposed) | This project adds (proposed) |
|---|---|---|---|---|
| Application | `tables_contexts_context` | name, icon, description, owner, sharing, nodes, pages | technical name, menu items | version, `configuration` JSON (landing page, theme, runtime options), permission fields |
| Menu item | `tables_contexts_menu_item` | nothing; the start page lists node tiles | label, icon, target (view, table, url), technical name, order | section, parent (one level), permission (group), count source |
| Page | `tables_views` with `type` | views are table views only; a context has one start page in `tables_contexts_page` with ordered node tiles | view `type` table or grid, grid JSON, views without a table | types detail, form and settings; page `configuration` JSON; actions JSON; sidebar JSON; permission |
| Widget | inside the page's grid JSON | nothing | `{id, type, configuration, content}`; configuration holds title and show title; position and size in the grid's layout list | configuration grows with style, data source and visibility rules |
| Widget type | `GridWidgetTypes` on the server | nothing | title, size, a configuration schema and a content schema, served on `api/2/views/widget-types` | a category and a data need flag |
| Data | tables, columns, rows | fifteen column types, shares, views with filter and sort, uuid and technical name on views | | uuids on rows, relations with inverse, the new column types, formats, audit, locks |

Two naming notes. Tables has no configuration field on an application today: its `api/2/config` routes return the notification settings of a table or view and set app or user config keys, which is a different thing. The application and page field is therefore named `configuration` here, and the plan avoids `config` for it. Widgets carry `configuration` and `content` as separate objects with separate schemas since fork pull request #7; grids saved before that read as the new shape until saved again.

Pages of type detail and form are views without a table of their own, like grid views. Their configuration names the table they work on.

## Examples

An application, as the proposed `api/2` would return it:

```json
{
  "id": 2,
  "technicalName": "intake",
  "name": "Intake",
  "icon": "inbox",
  "description": "Intake of requests for the service desk",
  "version": "1.2.0",
  "configuration": { "landingPage": "home", "theme": "default" },
  "menu": [
    { "id": 11, "technicalName": "home", "label": "Intake home", "icon": "home", "target": { "type": "view", "id": 6 }, "order": 0 },
    { "id": 12, "technicalName": "requests", "label": "Requests", "icon": "table", "target": { "type": "table", "id": 4 }, "order": 1, "permission": { "groups": ["servicedesk"] } },
    { "id": 13, "technicalName": "docs", "label": "Documentation", "icon": "book", "target": { "type": "url", "url": "https://docs.example.org" }, "order": 2 }
  ],
  "owner": "admin",
  "sharing": []
}
```

A page, a view of type grid, with two widgets as the fork stores them since pull request #7. Position and size live in the layout list, keyed by widget id:

```json
{
  "id": 6,
  "uuid": "7c0e…",
  "technicalName": "home",
  "title": "Intake home",
  "type": "grid",
  "tableId": null,
  "grid": {
    "widgets": [
      {
        "id": "w-header-1",
        "type": "header",
        "configuration": { "title": "Welcome", "showTitle": false },
        "content": { "title": "Welcome to intake", "subtitle": "Register a request in three steps", "textAlign": "left", "backgroundColor": "#1f6feb", "textColor": "" }
      },
      {
        "id": "w-data-2",
        "type": "data",
        "configuration": { "title": "Open requests", "showTitle": true },
        "content": { "target": { "type": "view", "id": 9 } }
      }
    ],
    "layout": [
      { "id": 1, "widgetId": "w-header-1", "gridX": 0, "gridY": 0, "gridWidth": 12, "gridHeight": 2 },
      { "id": 2, "widgetId": "w-data-2", "gridX": 0, "gridY": 2, "gridWidth": 12, "gridHeight": 6 }
    ]
  }
}
```

What this project adds to the page is a `configuration` object of its own (data source, actions, sidebar) and, to each widget's configuration, style and visibility rules.

## Naming debt to clear in 2.0

Two names are inconsistent today, and the upstream pull request is the moment to fix them, proposals P14 and P15.

**Context versus application.** The code, the database tables and the eleven `contexts` routes say context. The user interface says application in 39 strings and context in 1. A 2.0 release renames the code and the API to application: `tables_contexts_*` tables and classes become `tables_applications_*`, `api/2/applications` routes are added, and the `api/2/contexts` routes stay for one release as aliases so Analytics, Forms and connectors keep working.

**Technical name versus slug.** Upstream columns and views already carry a `technicalName`, validated as `^[a-z][a-z0-9_]*$`, and the row API already returns `dataByAlias` keyed by it. The fork had added a separate `slug` on views and applications; pull request #7 removed it and gave applications and menu items `technicalName` with the same pattern. What remains: the same field on tables, so the default CRUD route per application and table exists. Spreading an identifier that already exists to one more entity is debt removal, not a feature.

## Request paths

A page in the shell renders through three calls at most: the application (with menu and pages), the page definition, and the data of its widgets. Data calls go to the row API with filter, sort, search and pagination on the query string, validated against the columns the caller may see. Aggregates go to one aggregation endpoint per table or view. Nothing is loaded that the page does not show.

The shell is the fork's standalone route `/apps/tables/app/{technicalName}`. The Tables UI stays the configuration surface and keeps the tab bar for switching pages while designing.

## The Tables API speaks objects

Proposal P1: the translation between Tables storage and the object shape the components expect happens on the server. A second rows API lists rows of a table or view with search, filter, sort, paging, field selection and `format=object`, returning `{results, total, page, pages}` with rows as flat objects keyed by technical name and a metadata block. A schema endpoint returns a table as JSON Schema. The frontend store that the nc-vue components call is pointed at these endpoints with URL and parameter changes only. What remains in the browser is configuration, not a shape mapper. The [component list](03-component-list.md) names the methods and their callers; the [types and formats](10-types-and-formats.md) document names the mapping rules.

Nested data is column types, proposal P2: relation for objects, array for lists of scalars, json for opaque blobs, file for Files nodes. See [questions and proposals](09-questions-and-proposals.md) for the rules that keep this honest.

## API consumers and versions

Other apps depend on the Tables API, so it is a contract, not an implementation detail. Known consumers: Nextcloud Analytics reads tables as a data source, Nextcloud Forms writes submissions into tables, automation connectors such as the n8n node call it, and Conduction's integriq calls it. Tables' own frontend uses internal routes and is not a consumer of the OCS API.

Three APIs exist today, which is the problem proposal P16 addresses:

| API | Routes | Covers | In OpenAPI | Rows |
|---|---|---|---|---|
| `api/1`, OCS | 40 | tables, views, columns, rows, shares, import | yes | yes, `limit` and `offset` only, cell-array shape |
| `api/2`, OCS | 36 | tables, columns, contexts, favourites, config, scheme export and import, ownership transfer, widget types, standalone views | yes | no rows endpoint |
| internal, `/apps/tables/...` | 48 | views, rows, shares, tables, import, search, navigation, the app shell | no | yes, for the frontend only |

The internal routes are the ones Tables' own frontend calls for views, rows and shares, while it already calls `api/2` for applications and tables. So the primary consumer of Tables uses an API that has no OpenAPI description and no declared schemas, and every capability exists twice or three times. Proposal P16: the frontend moves to `api/2` and the internal routes retire in 2.0.

The rule this project follows, proposal P12: neither version changes shape. Rows arrive on `api/2` as new endpoints with the object format, next to the existing `api/2` tables and contexts routes. `api/1` rows stay for Analytics, Forms and the connectors. A client that wants the old cell shape on `api/2` asks for `format=cells`. The [rows API](12-rows-api.md) document holds both shapes.

## Manifest exchange

The manifest is the existing `api/2` context scheme grown to the whole application: identity and version, the tables and views with their columns, pages with their widgets, menu, settings, visibility rules and optional row data. Export, import, preview and a ZIP form with data are additions to `api/2`; the current scheme routes keep their shape. Templates, the round trip between instances, the buildiq v2 import and the packager all use the same six routes. The [rows API](12-rows-api.md) lists them and the [data model](04-data-model-and-gaps.md) names what buildiq fields have no home.

## Packaging

The packager generates a Nextcloud app from a template: `appinfo/info.xml` with a dependency on Tables, a repair step that imports the application manifest and schemes through the Tables API, a frontend that mounts the Tables shell for one application, and the CI configuration from the app template. The output is a deterministic ZIP. GitHub push is deferred; the ZIP is the deliverable.

## Permissions

Three layers, all existing Tables mechanisms:

1. **Who may open the application**: context shares (user, group, team), as today.
2. **Who sees which menu entry, page and widget**: a permission field holding a group id, evaluated on the server when the application is served, as buildiq does with `runtime.user.permissions`.
3. **Who may read and write which data**: table and view shares with the permission bitmask, and views as the row-level filter. Access control per row is deferred.

## What stays in nc-vue

Components that are tier none in the reliance score and purely presentational can be shared later; this project copies what it needs into Tables and keeps the adapter shape so the copies can be replaced by the library versions when Tables adopts it. No `@conduction/nextcloud-vue` dependency is added before 16 February.
