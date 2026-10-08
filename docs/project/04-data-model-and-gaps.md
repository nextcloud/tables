<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V.
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Data model mapping and gaps

Part of the [buildiq parity project](README.md). Measured on the fork at branch `feat/application-shell` on 8 October 2026.

buildiq stores its data in OpenRegister: registers hold schemas, schemas are JSON Schema documents, objects are JSON documents validated against a schema. After the move, Tables is the data layer. This page maps every OpenRegister concept buildiq uses onto Tables, names the Tables piece, and ranks each gap by effort.

## How Tables stores data today

Tables uses an entity, attribute, value model. A table is a row in `tables_tables`. A column is a row in `tables_columns`. A data row is a sleeve in `tables_row_sleeves` with only ids and timestamps. Every cell value lives in one table per storage type: `tables_row_cells_text`, `_number`, `_selection`, `_datetime`, `_usergroup` and `_relation`. `Row2Mapper` assembles a row from the sleeve and the cell tables.

Fifteen column types exist, grouped by storage type:

| storage | types |
|---|---|
| text | line, long, rich, link |
| number | plain, stars, progress |
| selection | single, multi, check |
| datetime | date and time, date, time |
| usergroup | users, groups, teams; single or multiple |
| relation | one row of one target table or view, shown by one label column |

Tables, columns, views and selection options carry a uuid. Rows do not. Columns and views carry a technical name, so a row can be read as `dataByAlias` keyed by that name.

Validation on write covers mandatory, text length, number range, unique text, link protocol and provider, relation target existence, and parsing of selection and date values. A regex pattern per text column is stored but not enforced.

## Concept by concept

| OpenRegister concept buildiq uses | Tables today | Verdict |
|---|---|---|
| Register as a container of schemas | Application (context) groups tables and views, carries permissions per node, exports and imports as one scheme, and since this fork has a slug, a menu and grid pages | Has, under another name |
| Schema as JSON Schema with typed properties | Table with typed columns; the scheme format is Tables' own | Partial, needs a JSON Schema mapping |
| Object and array properties | No nested values; only selection-multi and usergroup-multi hold lists | Lacks; decision D2 makes them column types, see [types and formats](10-types-and-formats.md) |
| `$ref` relations between schemas | Relation column: one target, one row, one label; no multi, no inverse, no cascade | Partial |
| File properties on objects | Text-link column with the Files provider and a preview; a link only, no storage, upload or access control | Partial |
| Uuid-based object access | Rows have integer ids only; no route takes a uuid | Lacks for rows |
| Object list with filter, sort, search, pagination | The mapper supports filter groups, thirteen operators and multi-sort, but only through saved views; the API exposes limit and offset; search runs in the browser | Partial |
| Facets | None | Lacks |
| Aggregations for charts and stats | Row counts only; the Analytics app can read tables | Lacks |
| Audit trail per object | Activity stream with change sets on row updates; deletes logged as critical actions; an unused `tables_log` table | Partial |
| Object locking | None | Lacks |
| Versions of an object | None; updates overwrite cells | Lacks |
| Soft delete | Archive flag on tables only | Lacks |
| Access control per schema | Shares on tables, views and applications with a permission bitmask; readonly and mandatory per column per view | Has |
| Access control per object | A filtered view shared to someone, typically on a usergroup column with `@me` | Partial |
| Import of JSON Schema | None | Lacks |
| Import of OpenAPI definitions | None | Lacks |
| Validation on write with formats | Mandatory, lengths, ranges, unique, link, relation; no format registry | Partial |

## Gaps ranked by effort

Effort is a relative size for one developer, including tests: S under a week, M one to two weeks, L two to four weeks, XL more than a month or a design decision first.

### S

1. **Regex pattern enforcement.** The pattern is stored and passed through already. One check in the text line business class enforces it. The field is overloaded for link providers on link columns, so the two uses need separating first.
2. **Applications as registers.** Contexts already group, permission and export. Naming plus an application uuid for scheme portability.
3. **Advisory row locking.** Two columns on the row sleeve and one check in update and delete.

### M

4. **Row uuids and uuid routes.** Migration with backfill on the sleeves, a lookup in the row mapper, OCS routes by uuid, and the user migration and federation code that copies rows.
5. **Filter, sort, search and pagination on the API.** The mapper already takes filter and sort arrays. A new OCS `GET` rows endpoint validates them against the columns the caller may see and returns a page with a total.
6. **Server-side full text search.** A `contains` across the text cell table is cheap. Ranking is a later step.
7. **JSON Schema export.** Column types map to JSON Schema types and formats, selection options to `enum`, mandatory to `required`, relations to `$ref` or uuid strings.
8. **JSON Schema import, flat.** The reverse mapping through the existing scheme import. Nested objects and arrays are rejected with a clear message until item 19 lands.
9. **Formats and validators.** A format id per column and a registry of validators on server and client, with the same list on both sides.
10. **Soft delete for rows.** A `deleted_at` on the sleeve, exclusion in every row query, count and relation lookup, plus restore and purge.
11. **Audit trail per row.** Persist the existing row events and change sets into `tables_log`, keyed by row, with a read endpoint and a sidebar tab.
12. **Aggregations.** Count, sum, average, minimum, maximum and group by one column on one cell table joined to the filtered sleeves. Grouping by several columns is harder on this storage.

### L

13. **Facets.** Distinct counts per column under the current filter. Multi-value columns need their own handling, and the cost on this storage must be measured.
14. **Multi-valued, typed relations.** A multi mode for the relation column, lookups by target uuid, inverse lookups, cascade rules, filters and editors.
15. **File attachments.** A column type that stores file ids, a resource provider so Files decides access, an upload editor, and cleanup when a row goes.
16. **OpenAPI import.** `$ref` resolution, `allOf` and `oneOf`, and several component schemas becoming several tables with relation columns inside one application.
17. **Versions.** History per cell type or a snapshot per update, with restore and a diff view.
18. **Access control per row.** An ACL per row injected into row selection, counts, relations, search and the federation proxy.

### XL

19. **Nested object and array properties.** These do not fit scalar cells. Either a JSON cell type, which loses typed filtering and sorting unless JSON path queries are added for all three databases, or child tables with automatic relations. Both touch the mapper, the API, import and export, the editors and analytics. This needs a decision before any code.

## One cross-cutting item

The Tables frontend loads every row of a table or view and filters, sorts and searches in the browser. Server-side query work (items 5, 6, 12 and 13) only pays off once the row views page, filter and search on the server. That switch is a frontend task of size M to L and belongs in the plan before the query items.

## What buildiq does not need from OpenRegister

Out of scope by decision: flows and automation, the AI companion, and everything only they consume (flow runs, actions, chat threads). The parity target is the application runtime, the designer and the packaging.
