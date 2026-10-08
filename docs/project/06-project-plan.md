<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V.
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Project plan

Part of the [buildiq parity project](README.md).

## Dates

| Date | Event |
|---|---|
| 8 October 2026 | plan written; research and the October fork work (grid views, applications, menus, widget schemas, app shell) done |
| 12 October 2026 | sprint 1 starts |
| 15 January 2027 | code freeze: every feature that ships is merged on the fork and green |
| 18 January to 12 February 2027 | stabilisation: bugs, upgrade tests, documentation, upstream slices |
| 16 February 2027 | release |

Seven two-week sprints before the freeze, 70 working days in total. Sprint 6 has eight working days because of the holidays. A sprint review with Nextcloud GmbH closes every sprint.

## Team

| Person | Role | Capacity |
|---|---|---|
| Robert | backend: data layer, APIs, packaging | full time |
| Remko | frontend: adapter, pages, widgets, designer | full time |
| Ruben | product owner, design, specifications, code | half time |
| Thijn | testing: acceptance specs, benchmarks, personas | half time |

Rule of thumb for the sizing below: one developer, one sprint, roughly two medium items or one large item plus its tests.

## Milestones

| Milestone | Sprint | Outcome | Deferrable past the freeze |
|---|---|---|---|
| M1 data foundation | 1 to 2 | cell indexes, row uuids, rows v2 listing with object format, column `format` and the formats registry, json, file and array column types, JSON Schema export and import, aggregation endpoint, benchmark baseline | no |
| M2 pages and widgets | 3 to 4 | detail, form and settings page types, aggregation API and widgets, presentational widget set, relations with inverse, file attachments, row audit, advisory locks | parts: audit, locks |
| M3 designer | 5 | visibility rules with server-side gating, actions and sidebar editors, index page configuration, menu sections and nesting, application settings and preferences, templates | parts: actions editor, templates |
| M4 manifest exchange | 6 | export and import of an application as a manifest with its schemes; a buildiq manifest imports | no |
| M5 packaging | 7 | generate an installable Nextcloud app from an application; release candidate | yes, as a whole |

## Sprint plan

### Sprint 1, 12 to 25 October, the data layer cluster

Decision D5: both developers work the types and the API extensions together, because they share the mapper, import and export, the column editor and the OpenAPI document. The keep widgets move to sprint 2.

| Owner | Item | Size |
|---|---|---|
| Robert | indexes on the cell tables, `(column_id, row_id)` and `(column_id, value)`; row uuids with backfill and routes by uuid | M |
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
| Robert | the array column type; search decision Q3 settled by the benchmark | M |
| Remko | copy of the keep widgets: label, image, link, links, quicklinks, tile, divider, video, menu, container, banner, with their forms as server schemas | M |
| Remko | server-side paging, filtering and search in the Tables data table behind a threshold; application pages use server mode | L |
| Remko | aggregation widgets: stat, delta, gauge, stats block, chart, stacked bar, workspace filter | M |
| Ruben | design of the detail page and the form page (layout, sidebar tabs, actions) | S |
| Thijn | benchmark round 1 and the review of M1 | S |

### Sprint 3, 9 to 22 November

| Owner | Item | Size |
|---|---|---|
| Robert | multi-valued relations with inverse lookups and lookups by target uuid; relation endpoint in both directions | L |
| Robert | relation delete rule (Q4) and file cell delete rule (Q5) implemented; inverse relation endpoint | M |
| Remko | detail page type: header, widget grid with data and metadata widgets, sidebar with files, relations and activity tabs | L |
| Remko | form page type: standalone form from a table's columns, multi-step, per-field validation, public submit | M |
| Ruben | manifest field mapping specification (buildiq v2 to Tables rows and back) | S |
| Thijn | acceptance specs for paths 2, 3 and 5 | M |

### Sprint 4, 23 November to 6 December

| Owner | Item | Size |
|---|---|---|
| Robert | row audit trail stored from the existing row events, with a read endpoint; advisory locks | M |
| Robert | application settings and preferences endpoints (per application, per user); group permission fields on menu items, pages and widgets with server-side filtering when the application is served | M |
| Remko | data list widgets: object table, card grid, map; related widget and related collections on the relation endpoint | L |
| Remko | audit and lock state on the detail page; settings page type | M |
| Ruben | review of M2 with Nextcloud GmbH; decision on what is deferred | S |
| Thijn | benchmark round 2; persona round on the application paths | M |

### Sprint 5, 7 to 20 December

| Owner | Item | Size |
|---|---|---|
| Robert | manifest export: identity, menu, pages, widgets, settings, visibility, schemes by uuid; manifest import creating or updating rows | L |
| Remko | designer: visibility rules editor, actions editor, sidebar editor, index page configuration dialog, menu sections and one level of nesting | L |
| Remko | application templates: save an application as a template, create from template | M |
| Ruben | packaging specification: template, repair step, dependency on Tables, CI configuration | S |
| Thijn | acceptance spec for path 6; regression run of the whole Playwright suite | M |

### Sprint 6, 21 December to 3 January, eight working days

| Owner | Item | Size |
|---|---|---|
| Robert | manifest import of a buildiq v2 manifest with the supported concepts; round trip tests | M |
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

Bug fixing from the acceptance runs, the upgrade test from the previous Tables release, documentation with refreshed screenshots, the changelog, and upstream pull requests sliced per feature for nextcloud/tables. No new features.

## Dependencies between items

- Rows v2 (sprint 1) before every adapt component, server-side paging (sprint 2) and the index page work.
- Row uuids (sprint 1) before relations by uuid, manifest export and packaging.
- The aggregation endpoint (sprint 2) before the aggregation widgets (sprint 2) and the dashboard parity.
- The file type (sprint 1) and relations (sprint 3) before the related widget and the detail sidebar (sprint 4).
- Settings and permission fields (sprint 4) before the designer editors (sprint 5).
- Manifest export (sprint 5) before packaging (sprint 7).

## Deferral order

If sprint 4 ends with M2 incomplete, items leave the freeze in this order: packaging as a whole, application templates, the actions editor, locks, audit, the settings page type. The data foundation and the manifest exchange stay, because the release is not useful without them.

## How work flows

One change, one pull request, one feature, on the fork with base `feat/application-shell` until that chain merges, then base `main`. Every pull request carries unit tests, a Playwright spec where a user path changes, the generated OpenAPI document, and a reviewed screenshot. Nextcloud GmbH gets the sprint review and chooses which slices go upstream and in which order.
