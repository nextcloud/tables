<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V.
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Architecture

Part of the [buildiq parity project](README.md).

## Principles

1. **Rows are the source of truth.** An application, its menu, its pages and its widgets are rows in Tables, editable in the Tables UI. The manifest is a file format for exchange, not the storage.
2. **Tables is the data layer.** A schema is a table, a property is a column, an object is a row. No OpenRegister at runtime.
3. **Server-side definitions.** Widget types, page types and validators are defined once on the server and served to the frontend, as the widget schemas already are. The form and the validation share one definition.
4. **Nothing hidden.** Every value a page shows comes from a Tables API a user could call. No private endpoints for the runtime.
5. **Upstream shape.** Code is written so nextcloud/tables can take it: Nextcloud attributes on controllers, psalm clean, generated OpenAPI, unit tests per class, Playwright for every user path.

## The application model

| Entity | Table | What it holds today | What it gains |
|---|---|---|---|
| Application | `tables_contexts_context` | name, icon, description, owner, slug | version, settings JSON, runtime options (landing page, theme) |
| Menu item | `tables_contexts_menu_item` | label, icon, target (view, table, url), slug, order | section, parent (one level of nesting), permission (group), count source |
| Page | `tables_views` with `type` | table and grid views, grid JSON, slug | types detail, form and settings; page config JSON; actions JSON; sidebar JSON; permission |
| Widget | inside the page's grid JSON | id, type, title, show title, content, position | visibility rules |
| Widget type | `GridWidgetTypes` on the server | title, size, content schema | a category and a data need flag, so the add dialog can group and hide what the page cannot feed |
| Data | tables, columns, rows | fifteen column types, shares, views | uuids on rows, relations with inverse, files, audit, locks, formats |

Pages of type detail and form are views without a table of their own, like grid views. Their config names the table they work on.

## Request paths

A page in the shell renders through three calls at most: the application (with menu and pages), the page definition, and the data of its widgets. Data calls go to the row API with filter, sort, search and pagination on the query string, validated against the columns the caller may see. Aggregates go to one aggregation endpoint per table or view. Nothing is loaded that the page does not show.

The shell is the existing standalone route `/apps/tables/app/{slug}`. The Tables UI stays the configuration surface and keeps the tab bar for switching pages while designing.

## The Tables API speaks objects

Decision D1: the translation between Tables storage and the object shape the components expect happens on the server. A second rows API lists rows of a table or view with search, filter, sort, paging, field selection and `format=object`, returning `{results, total, page, pages}` with rows as flat objects keyed by technical name and a metadata block. A schema endpoint returns a table as JSON Schema. The frontend store that the nc-vue components call is pointed at these endpoints with URL and parameter changes only. What remains in the browser is configuration, not a shape mapper. The [component list](03-component-list.md) names the methods and their callers; the [types and formats](10-types-and-formats.md) document names the mapping rules.

Nested data is column types, decision D2: relation for objects, array for lists of scalars, json for opaque blobs, file for Files nodes. See [questions and decisions](09-questions-and-decisions.md) for the rules that keep this honest.

## Manifest exchange

Export produces one JSON document per application: identity, menu, pages with their config and widgets, and the schemes of the tables it uses, with every target referenced by uuid. Import creates or updates the rows. The document follows buildiq's manifest v2 where the concepts match (menu, pages, widgets, settings, visibility) and adds a `tables` section for the data model. A buildiq manifest with only those concepts imports into Tables; the reverse export opens in buildiq's validator. Exact field mapping is a design task in sprint 3.

## Packaging

The packager generates a Nextcloud app from a template: `appinfo/info.xml` with a dependency on Tables, a repair step that imports the application manifest and schemes through the Tables API, a frontend that mounts the Tables shell for one application, and the CI configuration from the app template. The output is a deterministic ZIP. GitHub push is deferred; the ZIP is the deliverable.

## Permissions

Three layers, all existing Tables mechanisms:

1. **Who may open the application**: context shares (user, group, team), as today.
2. **Who sees which menu entry, page and widget**: a permission field holding a group id, evaluated on the server when the application is served, as buildiq does with `runtime.user.permissions`.
3. **Who may read and write which data**: table and view shares with the permission bitmask, and views as the row-level filter. Access control per row is deferred.

## What stays in nc-vue

Components that are tier none in the reliance score and purely presentational can be shared later; this project copies what it needs into Tables and keeps the adapter shape so the copies can be replaced by the library versions when Tables adopts it. No `@conduction/nextcloud-vue` dependency is added before 16 February.
