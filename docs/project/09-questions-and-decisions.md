<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V.
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Questions and decisions

Part of the [buildiq parity project](README.md). Open questions wait for an answer from Conduction and Nextcloud GmbH together. Decisions record what was chosen, when, and why, so nobody reopens them by accident. Add to both lists as the work raises them.

## Open questions

### Q1. May an application assume Tables is installed?

Everything in this plan treats Tables as the runtime: pages, menus and widgets call the Tables API for their data. A generated application therefore declares Tables as a dependency and refuses to enable without it. Is that acceptable for Nextcloud GmbH, or must a generated app degrade when Tables is absent?

Why it matters: if the answer is no, every widget needs a "Tables missing" state and the packager needs a fallback data store, which is a different project. The plan assumes yes.

Who answers: Nextcloud GmbH. Needed before: sprint 5, when packaging is specified.

### Q2. Does the object-shaped rows API go to upstream Tables or stay in the fork?

The listing endpoint with search, filter, sort, paging and `format=object` is useful to Tables on its own. If it goes upstream, the request syntax must follow Tables conventions and the OpenAPI document must be regenerated. If it stays in the fork, we are free but carry it.

Who answers: Nextcloud GmbH. Needed before: sprint 1 ends, because the parameter names are set then.

### Q3. Which search is good enough?

`LIKE` over text cells, or an index. The benchmark on 100k rows decides. If `LIKE` is too slow, the options are a per-table search index in Tables or Nextcloud's unified search provider extended with filters.

Who answers: Robert, with the benchmark. Needed before: sprint 2.

### Q4. Does a relation delete cascade?

A nested object becomes a row in a child table and a relation cell. When the parent goes, does the child go? Per relation setting, or one rule for all?

Who answers: Ruben. Needed before: sprint 3.

### Q5. Row delete and file cells

A file cell points at a Files node. When the row goes: unlink only, move the file to trash, or delete? A per-column setting is proposed.

Who answers: Ruben. Needed before: sprint 3.

### Q6. How is `format` stored on a column?

Either a new `format` column on `tables_columns`, or a key in the existing `customSettings` JSON. A dedicated column is queryable and explicit. `customSettings` needs no migration.

Who answers: Robert. Needed before: sprint 1.

## Decisions

### D1. The Tables API speaks objects, the frontend speaks Tables

8 October 2026. The translation from cell arrays to objects and from columns to JSON Schema happens on the server, in a second rows API and a schema endpoint, not in a browser adapter and not in each component. The frontend store is pointed at Tables with URL and parameter changes only.

Why: fifty files send `_search`, and the shape gap is bigger than the parameter gap. A server-side object format serves every client, is a feature Tables wants anyway, and moves work to the lane where the engine already lives.

### D2. Nested attributes are column types, not a document

8 October 2026. An object attribute is a relation cell to a row in another table. An array is one cell per value in the typed table, the usergroup pattern. A json blob is a new opaque cell type. A file is a new cell type that stores a Files node id.

Why: the EAV layout gives typed filters, sorts and aggregations cheaper than a JSON document does. The nested property gap moves from one XL item to three M items.

### D3. A json column is never queried

8 October 2026. The json cell type is for display data, imports and configuration. It does not appear in filter, sort, facet, aggregate or search. An optional JSON Schema fragment validates it on write.

Why: without this rule the json type becomes the escape hatch that makes every later query impossible.

### D4. Files owns file storage

8 October 2026. A file cell holds a Files node id and nothing else. Access, versions, previews, sharing and trash are Files' job. Tables asks Files whether the user may read the node.

Why: OpenRegister's own file tree and access code is where the file problems came from.

### D5. Types and API extensions are one cluster in sprint 1

8 October 2026, Ruben. The new column types and the API extensions are built together in the first two weeks with both developers on them, because they share the mapper, the import and export code and the OpenAPI document. The keep widgets move to sprint 2.

Why: one pass through the twenty-nine files a column type touches, instead of four passes.

### D6. Parity scope and data layer

8 October 2026, Ruben. Parity is the application runtime, the designer and packaging. Flows, automation and the AI companion are out. The data layer is Tables rows. See [goals and scope](01-goals-and-scope.md).
