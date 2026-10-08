<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V.
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Project plan

Part of the [buildiq parity project](README.md).

## Dates

| Date | Event |
|---|---|
| before sprint 1 | the fork chain merged and opened as one pull request on nextcloud/tables; everything below builds on it |
| 12 October 2026 | sprint 1 starts |
| 15 January 2027 | code freeze: every feature that ships is merged on the fork and green |
| 18 January to 12 February 2027 | stabilisation: bugs, upgrade tests, documentation, upstream slices |
| 16 February 2027 | release |

Seven two-week sprints run before the freeze, 70 working days in all. Sprint 6 has eight working days because of the holidays. Every sprint ends with a review together with Nextcloud GmbH.

## Team

| Person | Role | Capacity |
|---|---|---|
| Robert | backend: data layer, APIs, packaging | full time |
| Remko | frontend: adapter, pages, widgets, designer | full time |
| Ruben | product owner, design, specifications, code | half time |
| Thijn | testing: acceptance specs, benchmarks, personas | half time |

Rule of thumb for the sizes below: one developer does about two medium items, or one large item with its tests, in one sprint.

## Milestones

| Milestone | Sprint | Outcome | Deferrable past the freeze |
|---|---|---|---|
| M1 data foundation | 1 to 2 | cell indexes, row uuids, rows v2 listing with object format, column `format` and the formats registry, json, file and array column types, JSON Schema export and import, aggregation endpoint, benchmark baseline | no |
| M2 pages and widgets | 3 to 4 | detail, form and settings page types, aggregation API and widgets, presentational widget set, relations with inverse, file attachments, row audit, advisory locks | parts: audit, locks |
| M3 designer | 5 | visibility rules with server-side gating, actions and sidebar editors, index page configuration, menu sections and nesting, application settings and preferences, templates | parts: actions editor, templates |
| M4 manifest exchange | 6 | export and import of an application as a manifest with its schemes; a buildiq manifest imports | no |
| M5 packaging | 7 | generate an installable Nextcloud app from an application; release candidate | yes, as a whole |

## Sprint plan

### Before sprint 1

| Owner | Item | Size |
|---|---|---|
| Ruben | merge fork pull requests #2, #3 and #4 into `feat/grid-page`, rename the migration to the current target version and date, open the upstream pull request on nextcloud/tables with the AI disclosure, in Ruben's own words | S |
| Remko | done on fork pull request #7: widget `configuration` and `content`, `technicalName` instead of `slug`, OCS routes for widget types and standalone views, the scheme import value object; left: review and merge #7, then address upstream review during sprint 1 as it comes | S |

### Sprint 1, 12 to 25 October, the data layer cluster

Proposal P5: Robert and Remko work the types and the API extensions together, because they share the mapper, import and export, the column editor and the OpenAPI document. The keep widgets move to sprint 2.

| Owner | Item | Size |
|---|---|---|
| Robert | indexes on the cell tables, `(column_id, row_id)` and `(column_id, value)`; row uuids with backfill and routes by uuid; `technicalName` on tables and applications and the default CRUD routes `/api/2/apps/{application}/{table}` (P15) | M |
| Robert | rows v2 listing for tables and views: search, filter with the twelve operators, sort, page and limit with total, fields, `format=object`; object format accepted on create and update | L |
| Robert | `format` on columns, the formats registry with the first twelve validators, pattern enforcement; JSON Schema export and flat import | M |
| Remko | the json and file column types end to end: migration, mapper, cell editor, renderer, filter UI, import and export, OpenAPI | L |
| Remko | the store pointed at the Tables rows v2 and schema endpoints: URL and parameter changes, the data widget on it | S |
| Ruben | specification of the JSON Schema mapping, the formats list and the request syntax of rows v2; the answer to Q2 and Q6 | S |
| Thijn | acceptance specs for paths 1 and 4 drafted; the 100k row benchmark table built; benchmark round 0 on the new endpoint | M |

### Sprint 2, 26 October to 8 November

| Owner | Item | Size |
|---|---|---|
| Robert | aggregation endpoint: value, grouped, timeseries; distinct values of a column; `extend` for relation cells | M |
| Robert | `api/2` routes for what only internal routes offer today: views by table, shares, import preview, search, navigation (P16) | M |
| Robert | the tag column type; search improved past `LIKE`: a per-table search index over text cells, proposal P9 | L |
| Remko | copy of the keep widgets: label, image, link, links, quicklinks, tile, divider, video, menu, container, banner, with their forms as server schemas | M |
| Remko | the Tables frontend store (P13) on `api/2` only (P16): per-source cache, request coalescing, invalidation on row changes; server-side paging, filtering and search in the data table behind a threshold | L |
| Remko | aggregation widgets: stat, delta, gauge, stats block, chart, stacked bar, workspace filter | M |
| Ruben | design of the detail page and the form page (layout, sidebar tabs, actions) | S |
| Thijn | benchmark round 1 and the review of M1 | S |

### Sprint 3, 9 to 22 November

| Owner | Item | Size |
|---|---|---|
| Robert | relation extended: filter, sort, inverse lookups, inline resolve, import and export remapping | L |
| Robert | the array column type; delete behaviour as property configuration on relation and file columns, proposal P10 | M |
| Remko | detail page type: header, widget grid with data and metadata widgets, sidebar with files, relations and activity tabs | L |
| Remko | form page type: standalone form from a table's columns, multi-step, per-field validation, public submit | M |
| Ruben | manifest field mapping specification (buildiq v2 to Tables rows and back) | S |
| Thijn | acceptance specs for paths 2, 3 and 5 | M |

### Sprint 4, 23 November to 6 December

| Owner | Item | Size |
|---|---|---|
| Robert | row audit trail stored from the existing row events, with a read endpoint; advisory locks; calendar item and contact column types through `OCP\\Calendar` and `OCP\\Contacts` | L |
| Robert | application settings and preferences endpoints (per application, per user); group permission fields on menu items, pages and widgets with server-side filtering when the application is served | M |
| Remko | data list widgets: object table, card grid, map; related widget and related collections on the relation endpoint | L |
| Remko | audit and lock state on the detail page; settings page type | M |
| Ruben | review of M2 with Nextcloud GmbH; proposal on what is deferred | S |
| Thijn | benchmark round 2; persona round on the application paths | M |

### Sprint 5, 7 to 20 December

| Owner | Item | Size |
|---|---|---|
| Robert | manifest routes on `api/2`, grown from the context scheme export and import: identity and version, pages with widgets, menu, settings, visibility, columns with format and relations by technical name; preview-changes as dry run; optional rows as data | L |
| Remko | designer: visibility rules editor, actions editor, sidebar editor, index page configuration dialog, menu sections and one level of nesting | L |
| Remko | application templates: save an application as a template, create from template | M |
| Ruben | packaging specification: template, repair step, dependency on Tables, CI configuration | S |
| Thijn | acceptance spec for path 6; regression run of the whole Playwright suite | M |

### Sprint 6, 21 December to 3 January, eight working days

| Owner | Item | Size |
|---|---|---|
| Robert | manifest ZIP export and import with data; import of a buildiq v2 manifest through `source=buildiq`; round trip tests between two instances | M |
| Remko | closing work on M2 and M3 items; screenshot pass of every surface at desktop and phone width | M |
| Thijn | round trip test between two instances | S |

### Sprint 7, 4 to 15 January

| Owner | Item | Size |
|---|---|---|
| Robert | packaging: generate the app ZIP from the template with manifest and schemes, repair step importing through the Tables API, dependency on Tables declared; install test in CI | L |
| Remko | frontend of the generated app: mount the Tables shell for one application; upgrade path for existing applications | M |
| Ruben | freeze review: every item merged and green or moved behind the freeze | S |
| Thijn | acceptance spec for path 7; benchmark round 3; release checklist started | M |

### After the freeze, 18 January to 12 February

We fix bugs from the acceptance runs, run the upgrade test from the previous Tables release, refresh the documentation and its screenshots, write the changelog, and slice the upstream pull requests per feature for nextcloud/tables. No new features.

## User reviews

From December every sprint review is followed by a user review. That is an online session of about an hour, where we show the working software to people who build on Tables today and listen to what they say. Proposal P7.

| Date | Session | Shows |
|---|---|---|
| week of 7 December 2026 | user review 1 | detail and form pages, data widgets, the designer so far |
| week of 4 January 2027 | user review 2 | visibility rules, actions, sidebar, templates, manifest export and import |
| week of 25 January 2027 | user review 3 | the release candidate, a packaged application, the upgrade path |
| week of 8 February 2027 | user review 4 | fixes from review 3, go or no go for 16 February |

How a session runs. Ruben demonstrates one acceptance path from [testing and acceptance](08-testing-and-acceptance.md) live. Two or three participants then try the same path on a shared test instance while the others watch. Open discussion closes it. Thijn records findings as issues, in the participant's words and with the screen they were on. We triage within a week, and every participant hears what happened to their finding.

Who is invited. Organisations that depend on Tables for their own applications, Schleswig-Holstein first because of the weight of its Tables use. The Conduction municipalities that will run the first packaged apps. Anyone Nextcloud GmbH names. Six to ten participants per session. Question Q7 asks Nextcloud GmbH whether a user panel exists we can draw from. Until that answer, we build our own list.

## The 2.0 rename and the internal routes

With P16 the internal routes go in the same release as the rename, once the frontend store calls `api/2` for everything. Sprint 4 removes what nothing calls any more. From then on the OpenAPI document describes the whole app.

Proposal P14, context to application, is its own item because it touches every layer. Robert takes it in sprint 4, after the data layer has settled and before the manifest and packaging work write the entity name into files. It covers a migration that renames the tables, renamed classes and services, new `api/2/applications` routes with `api/2/contexts` kept as aliases, a regenerated OpenAPI document, and the frontend store and strings aligned. Size L. If sprint 4 is full it moves to the stabilisation weeks, never past the release.

## Dependencies between items

- Rows v2 (sprint 1) before every adapt component, server-side paging (sprint 2) and the index page work.
- Row uuids (sprint 1) before relations by uuid, manifest export and packaging.
- The aggregation endpoint (sprint 2) before the aggregation widgets (sprint 2) and the dashboard parity.
- The file type (sprint 1) and relations (sprint 3) before the related widget and the detail sidebar (sprint 4).
- Settings and permission fields (sprint 4) before the designer editors (sprint 5).
- Manifest export (sprint 5) before packaging (sprint 7).

## Deferral order

If sprint 4 ends with M2 incomplete, items leave the freeze in this order: packaging as a whole, application templates, the actions editor, locks, audit, the settings page type. The data foundation and the manifest exchange stay. Without them the release is not useful.

## How work flows

One change, one pull request, one feature. The base is `feat/application-shell` until that chain merges, then `main`. Every pull request carries unit tests, a Playwright spec where a user path changes, the generated OpenAPI document and a reviewed screenshot. Nextcloud GmbH gets the sprint review and chooses which slices go upstream, and in which order.

If you own an item above, open its pull request against the base named here and write what you verified in the body. If an item is going to miss its sprint, say so at the review, not after it.