<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V.
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Feature comparison: buildiq against the Tables fork

Part of the [buildiq parity project](README.md). Measured on buildiq 0.7.15 with nextcloud-vue 2.57.1 and on the Tables fork at `feat/application-shell`.

buildiq has three layers: an application runtime that renders a whole app from one JSON manifest, a designer that edits that manifest and the data model in place, and packaging that turns an application into a real Nextcloud app. Flows, automation, the AI companion and connectors are out of scope for this project and are left out below.

Status words: **has** means Tables does it today, **partial** means a smaller version exists, **lacks** means nothing exists, **built** means the fork added it on the open pull requests; it is not in Tables and counts as proposed until merged upstream.

## 1 The manifest

buildiq describes an application in a manifest validated against a JSON Schema (`app-manifest-v2.schema.json`). Tables describes an application as rows: a context, its menu items, its pages and its views.

| Manifest key | What it does in buildiq | Tables fork | Status |
|---|---|---|---|
| `version` | content version, cache busting, migrations | none on applications | lacks |
| `menu` | left navigation: sections, nesting, icons, permission, counts, dynamic sources | menu items table: label, icon, target (view, table, link), slug, order; one level | built, partial |
| `pages` | typed pages with route, title, permission, widgets, actions, sidebar | views of type table or grid, linked through menu items | built, partial |
| `dependencies` | hard and soft app dependencies with a guard screen | none | lacks |
| `setup` | first-run wizard with config fields, choices and server actions | none | lacks |
| `walkthrough` | versioned guided tours with recorded targets | none | lacks |
| `support`, `personalisation`, `nav` | support dialog, per-user defaults, navigation chrome | none | lacks |
| `runtime.user` | server-injected permissions and owner flag, drives `visibleIf` | context ownership and share bitmask only | partial |
| `adminSettings`, settings pages | settings forms backed by a generic settings endpoint | Tables has its own settings; applications have none | lacks |
| `schedules`, `observability`, `store`, `deepLinks`, `mcp` | scheduled actions, health and metrics, store plane, search deep links, tool hints | none | out of scope for 16 February |
| `pageTemplates`, `pageInstances`, `sets` | declare an index and detail page once and stamp them per entity | none | lacks |

Proposal this project takes: Tables keeps rows as the source of truth and gains an export of an application as a manifest, plus an import of a manifest into rows. That keeps every piece editable in the Tables UI and gives buildiq compatible apps a file format. See [architecture](05-architecture.md).

## 2 Page types

| buildiq page type | Renders | Tables fork | Status | Needs from the data layer |
|---|---|---|---|---|
| `index` | object list as table, cards, list, map, board or timeline; search, facets, quick filters, saved views, mass actions, quick edit, create and edit dialogs | a table or view in a menu item: Tables' own data table with inline edit, client-side search, filter and sort, saved views as Tables views | partial | server-side list with filter, sort, search, pagination and facets; aggregations for counts |
| `detail` | one object with a widget grid and a sidebar (files, notes, tags, audit, access, relations, locks, presence) | row edit dialog; activity in the sidebar | lacks as a page | row by uuid, relations both ways, files on rows, audit per row, locks |
| `dashboard` | GridStack widget grid, per-user layout, page filters | grid view with header, text and data widgets, server-side widget schemas | built, partial | aggregations for the stat and chart widgets |
| `form` | standalone or public form, multi-step, per-field validation, `visibleWhen` | row create dialog generated from columns | partial | validation with formats; public form submit |
| `settings` | settings form in sections or tabs saved to an endpoint | none for applications | lacks | an application settings store |
| `logs` | read-only paginated table | a read-only view | partial | pagination on the API |
| `map` | Leaflet map with layers and markers from objects | none | lacks | a geo column type or a lat and lon pair |
| `wiki` | markdown article with a tree sidebar | rich text column | partial | a tree relation |
| `files` | Files folder listing | none | lacks | Files integration only |
| `chat`, `reports`, `roadmap`, `search`, `store` | Talk iframe, report cards, GitHub roadmap, cross-schema search, app store | none | deferred: not needed for parity of a built application |
| `custom` | any registered component | none | lacks until packaged apps exist |

## 3 Widgets

buildiq registers about 41 widget types. The fork has 3. The table groups them by what they need.

| Group | buildiq widgets | Fork | Status |
|---|---|---|---|
| Presentational | label, text, image, link, links, quicklinks, tile, header, divider, video, menu, container, banner | header, text | partial, straightforward |
| Data list | object-table, card-grid, nav-card-grid | data (a table or view) | partial |
| Aggregation | stat, delta, gauge, chart, stats-block, workspace-filter | none | lacks, needs aggregations on the API |
| Detail only | data, metadata, related, audit-trail, stages, countdown, presence, object-geo, tabs | none | lacks, needs the detail page |
| Nextcloud integrations | files, calendar, people, nc-widget | none | lacks, no OpenRegister needed |
| Map | map, map-viewer | none | lacks, needs geo data |
| Forms | form-renderer, interaction-form | none | lacks |
| Out of scope | flow-runs, tasks, spend-analytics, kb-search, integration | none | out of scope |

Widget configuration already follows the buildiq shape on the fork: one `content` object per widget, validated against a server-side schema that also drives the form. New widget types are a schema and a renderer each.

## 4 Menu, actions, sidebar, settings, onboarding and permissions

| Concern | buildiq | Tables fork | Status |
|---|---|---|---|
| Menu | sections, two levels, counts, permission, `visibleIf`, dynamic sources, pinned and open state | one level, label, icon, target, slug, order; inline editor | built, partial |
| Published app entry | top-bar entry with icon for a published application | top-bar entry through the existing navigation display mode, slug address | built |
| Page and row actions | declarative actions: object-op, open-form, open-page, navigate, export, api-call, refresh, toggle; bulk actions | Tables' fixed row actions and export | partial |
| Sidebar | index sidebar (search, columns, facets), detail sidebar tabs | Tables sidebar: sharing, activity, integration | partial |
| Settings | settings pages, admin settings dialog, generic settings endpoint | none for applications | lacks |
| Setup wizard | first-run wizard from the manifest | none | lacks |
| Walkthrough | tours with a click-to-record designer | none | lacks |
| Visibility gating | menu, page and widget visibility by group, server-side filtering | ownership and shares | partial |
| App roles | owners, editors, viewers per application | owner plus share permissions per node | partial |
| Data access control | per schema, per operation, groups or owner, enforced by OpenRegister | shares on tables, views and applications with a permission bitmask; column readonly per view | has, different shape |

## 5 Designer

| Surface | buildiq | Tables fork | Status |
|---|---|---|---|
| Edit mode on a page | edit button, add widget, configure and remove widgets, save a delta | edit page, add widget, configure and remove, save the grid | built |
| Pages editor | add, remove, reorder, type, parent, route | application page: pages as cards, add page | built, partial |
| Menu editor | tree with icons and nesting | inline editor with drag order, one level | built, partial |
| Widget forms | one form per widget type from the registry | one generic form from the server schema | built |
| Visibility rules | `visibleWhen` rule editor | none | lacks |
| Actions editor | header and row actions | none | lacks |
| Sidebar editor | index panel and detail tabs | none | lacks |
| Index page config | five tabs: views, columns, forms, mass actions, advanced | Tables view settings: columns, filter, sort | partial |
| Data model editor | registers and schemas with properties, validation, relations, lifecycle, access | Tables table and column editors, scheme import and export | has, different shape |
| Three-pane page designer | page list, per-type editor, validator, live preview, undo, block library | none | lacks, partly replaced by in-place editing |
| App creation wizard | application, versions, register and schemas from presets | create application dialog; templates for tables | partial |
| Versioning and promotion | draft, published, archived versions with diff and rollback | none | lacks |
| Layered customisation of installed apps | base, admin delta, user delta | none | out of scope for 16 February |
| Templates and shop | application templates, GitHub shop | table templates (six, hardcoded) | partial |
| Icon upload | light and dark SVG per application | material icon name | partial |

## 6 Packaging

buildiq has three delivery modes. Virtual: the manifest is served and rendered under the buildiq app. Hybrid: an installed app plus deltas. Export: an async job builds a Nextcloud app from a template with the manifest, the register definition, seed data and a portable repository layout, optionally pushed to GitHub.

| Capability | buildiq | Tables fork | Status |
|---|---|---|---|
| Serve an application at its own address with its own navigation entry | `/apps/buildiq/builder/{slug}` | `/apps/tables/app/{slug}` | built |
| Export the application definition | manifest plus register JSON plus seed data | context scheme with menu items and grid views; table schemes with data | partial |
| Generate an installable app | ZIP from a template, deterministic, with CI config | none | lacks |
| Push to GitHub | through the OpenRegister credential broker | none | lacks, needs a credential source without OpenRegister |
| Generated app runtime | needs OpenRegister installed: it imports its register on repair and calls the OpenRegister API from the browser | target: the generated app needs Tables, imports its scheme on repair and calls the Tables API | to design |

Two facts about buildiq's export matter for the plan. The template it builds from still pins Vue 2 and an old library version while buildiq writes manifests for the current library, so exported apps need repair work regardless of this project. And the exported `info.xml` declares no OpenRegister dependency although the app cannot run without it. The Tables generator should declare its dependency on Tables explicitly.

## 7 What the runtime takes from OpenRegister

The buildiq inventory lists every OpenRegister endpoint the three layers call. Grouped by what Tables must provide:

| Need | OpenRegister provides | Tables equivalent | Gap item |
|---|---|---|---|
| Schema definitions as JSON Schema | `GET /api/schemas/{id}` | table scheme, Tables' own format | JSON Schema export and import |
| Object list with filter, sort, search, pagination, facets | `GET /api/objects/{r}/{s}` with `_page`, `_limit`, `_search`, `_order`, `_facets` | row list with limit and offset only | API filter, sort, search, pagination; facets |
| Object CRUD by uuid | objects endpoints | rows by integer id | row uuids and routes |
| Relations and their inverse | `uses`, `used`, `$ref` resolution | single relation column, no inverse | multi-valued typed relations |
| Files on objects | `/files`, multipart, publish | link column with Files preview | file attachments |
| Notes | `/notes` | none | not needed for parity of the runtime core; comments later |
| Audit trail and versions | `/audit-trails`, revert | activity stream | audit trail per row; versions later |
| Locks | `/lock`, `/unlock` | none | advisory locking |
| Lifecycle transitions | `available-actions`, `transition` | none | out of scope for 16 February |
| Aggregations | value, grouped, timeseries | row counts | aggregations |
| Import and export of data | register import, object export | CSV, XLSX and ODS import, CSV export | has |
| Saved views | `/api/views` | Tables views | has |
| Settings and preferences | AppHost `/api/settings`, `/api/preferences/{key}` | none for applications | application settings and preferences |
| Registers and schema authoring | registers and schemas endpoints | tables, columns, applications | has, different shape |
| Credential broker | `/api/credentials` | none | packaging only; GitHub token in app config |

The full list with the exact endpoints and the consuming components is in the [component list](03-component-list.md) and the [data model mapping](04-data-model-and-gaps.md).

## 8 Reading the comparison

Three conclusions shape the plan.

Tables already holds the structural half: tables, columns, views, applications, shares, schemes, import and export, and since October the menu, the grid, the widget schemas and the app shell. What it lacks is query power on the API (filter, sort, search, pagination, facets, aggregations), row identity (uuids), row-level extras (files, audit, locks, inverse relations) and the manifest-level concerns (settings, setup, walkthrough, visibility rules, versions).

The designer gap is smaller than it looks. buildiq's in-place editing (ADR-041) is the part users touch; the three-pane designer is a developer tool. The fork already edits pages, menu and widgets in place. The missing editors are rules, actions, sidebar and index page config.

Packaging is the one layer with no equivalent at all, and it depends on everything else being stable. It is the last milestone before the freeze and the first candidate to move behind 15 January if the data layer slips.
