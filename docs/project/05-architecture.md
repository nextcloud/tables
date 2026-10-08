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

Three columns because the fork is ahead of Tables: what nextcloud/tables has, what the fork's open pull requests add, and what this project adds.

| Entity | Table | In nextcloud/tables | On the fork | This project adds |
|---|---|---|---|---|
| Application | `tables_contexts_context` | name, icon, description, owner, sharing, nodes, pages | slug, menu items | version, `configuration` JSON (landing page, theme, runtime options), permission fields |
| Menu item | `tables_contexts_menu_item` | nothing; the start page lists node tiles | label, icon, target (view, table, url), slug, order | section, parent (one level), permission (group), count source |
| Page | `tables_views` with `type` | views are table views only; a context has one start page in `tables_contexts_page` with ordered node tiles | view `type` table or grid, grid JSON, slug, views without a table | types detail, form and settings; page `configuration` JSON; actions JSON; sidebar JSON; permission |
| Widget | inside the page's grid JSON | nothing | type, layout (position and size), content with every property including title and show title | split into `configuration` (title, show title, style, data source, visibility rules) and `content` (what the widget shows) |
| Widget type | `GridWidgetTypes` on the server | nothing | title, default show title, size, content schema | a category, a data need flag, and a configuration schema next to the content schema |
| Data | tables, columns, rows | fifteen column types, shares, views with filter and sort, uuid and technical name on views | | uuids on rows, relations with inverse, the new column types, formats, audit, locks |

Two naming notes. Tables has no configuration field on an application today: its `api/2/config` routes return the notification settings of a table or view and set app or user config keys, which is a different thing. The application and page field is therefore named `configuration` here, and the plan avoids `config` for it. For widgets the fork currently puts every property, title and show title included, in `content`; the split into `configuration` and `content` is a change to the fork's widget shape before it goes upstream, so it is listed under the pre-sprint work.

Pages of type detail and form are views without a table of their own, like grid views. Their configuration names the table they work on.

## Request paths

A page in the shell renders through three calls at most: the application (with menu and pages), the page definition, and the data of its widgets. Data calls go to the row API with filter, sort, search and pagination on the query string, validated against the columns the caller may see. Aggregates go to one aggregation endpoint per table or view. Nothing is loaded that the page does not show.

The shell is the existing standalone route `/apps/tables/app/{slug}`. The Tables UI stays the configuration surface and keeps the tab bar for switching pages while designing.

## The Tables API speaks objects

Proposal P1: the translation between Tables storage and the object shape the components expect happens on the server. A second rows API lists rows of a table or view with search, filter, sort, paging, field selection and `format=object`, returning `{results, total, page, pages}` with rows as flat objects keyed by technical name and a metadata block. A schema endpoint returns a table as JSON Schema. The frontend store that the nc-vue components call is pointed at these endpoints with URL and parameter changes only. What remains in the browser is configuration, not a shape mapper. The [component list](03-component-list.md) names the methods and their callers; the [types and formats](10-types-and-formats.md) document names the mapping rules.

Nested data is column types, proposal P2: relation for objects, array for lists of scalars, json for opaque blobs, file for Files nodes. See [questions and proposals](09-questions-and-proposals.md) for the rules that keep this honest.

## API consumers and versions

Other apps depend on the Tables API, so it is a contract, not an implementation detail. Known consumers: Nextcloud Analytics reads tables as a data source, Nextcloud Forms writes submissions into tables, automation connectors such as the n8n node call it, and Conduction's integriq calls it. Tables' own frontend uses internal routes and is not a consumer of the OCS API.

Two API versions exist today, both under `/ocs/v2.php/apps/tables`:

| Version | Routes | Covers | Rows |
|---|---|---|---|
| `api/1` | 40 | tables, views, columns, rows, shares, import | yes, `limit` and `offset` only, cell-array shape |
| `api/2` | 34 | tables, columns, contexts, favourites, config, scheme export and import, ownership transfer | no rows endpoint |

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
