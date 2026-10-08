<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V.
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Questions and proposals

Part of the [buildiq parity project](README.md). Open questions wait for an answer from Conduction and Nextcloud GmbH together. Nothing below is decided yet. Proposals record what Conduction puts forward and why. A proposal becomes a proposal when Conduction and Nextcloud GmbH agree in a sprint review or the kick-off; the agreed ones get a date here. Add to both lists as the work raises them.

## Open questions

### Q1. May an application assume Tables is installed?

Everything in this plan treats Tables as the runtime: pages, menus and widgets call the Tables API for their data. A generated application therefore declares Tables as a dependency and refuses to enable without it. Is that acceptable for Nextcloud GmbH, or must a generated app degrade when Tables is absent?

Why it matters: if the answer is no, every widget needs a "Tables missing" state and the packager needs a fallback data store, which is a different project. The plan assumes yes.

Who answers: Nextcloud GmbH. Needed before: sprint 5, when packaging is specified.

### Q4. Does a relation delete cascade?

A nested object becomes a row in a child table and a relation cell. When the parent goes, does the child go? Per relation setting, or one rule for all?

Who answers: Ruben. Needed before: sprint 3.

### Q2. Which direction do integrations run?

Tables reaches teams by calling Circles classes directly behind an enabled check. Nextcloud's architecture prefers the other direction: the hosting app publishes extension points and the integrating app registers into them, the way Flow works. Does Tables publish extension points for column types, row tabs and widgets, so Deck, Talk, Collectives, Forms, Polls, Maps, Photos and the rest can register themselves, and does Tables itself stop calling other apps' classes?

Why it matters: it decides the shape of every integration in this plan and what Tables may contain. See the [meeting agenda](11-meeting-agenda.md).

Who answers: Nextcloud GmbH with Conduction, in the kick-off meeting. Needed before: sprint 2.

### Q7. Does Nextcloud have a user panel for Tables, and may we run review sessions?

We are not aware of a standing user research panel at Nextcloud; the community forum and GitHub discussions are open channels, not a panel. Does Nextcloud GmbH have customer contacts who build on Tables and would join an online review session once a month from December? And is it acceptable that Conduction hosts those sessions and invites its own customers alongside?

Why it matters: the sessions start in the week of 7 December and need invitations in November.

Who answers: Nextcloud GmbH. Needed before: 15 November 2026.

## Proposals

### P1. The Tables API speaks objects, the frontend speaks Tables

The translation from cell arrays to objects and from columns to JSON Schema happens on the server, in a second rows API and a schema endpoint, not in a browser adapter and not in each component. The frontend store is pointed at Tables with URL and parameter changes only.

Why: fifty files send `_search`, and the shape gap is bigger than the parameter gap. A server-side object format serves every client, is a feature Tables wants anyway, and moves work to the lane where the engine already lives.

### P2. Nested attributes are column types, not a document

An object attribute is a relation cell to a row in another table. An array is one cell per value in the typed table, the usergroup pattern. A json blob is a new opaque cell type. A file is a new cell type that stores a Files node id.

Why: the EAV layout gives typed filters, sorts and aggregations cheaper than a JSON document does. The nested property gap moves from one XL item to three M items.

### P3. A json column is never queried

The json cell type is for display data, imports and configuration. It does not appear in filter, sort, facet, aggregate or search. An optional JSON Schema fragment validates it on write.

Why: without this rule the json type becomes the escape hatch that makes every later query impossible.

### P4. Files owns file storage

A file cell holds a Files node id and nothing else. Access, versions, previews, sharing and trash are Files' job. Tables asks Files whether the user may read the node.

Why: OpenRegister's own file tree and access code is where the file problems came from.

### P5. Types and API extensions are one cluster in sprint 1

Ruben. The new column types and the API extensions are built together in the first two weeks with both developers on them, because they share the mapper, the import and export code and the OpenAPI document. The keep widgets move to sprint 2.

Why: one pass through the twenty-nine files a column type touches, instead of four passes.

### P7. User reviews from December

Ruben. From December every sprint review is followed by an online user review with organisations that depend on Tables for their applications, Schleswig-Holstein among the first. Four sessions before the release, see the [project plan](06-project-plan.md).

Why: these organisations carry the risk of a change in Tables; they should see it before it ships, not after.

### P8. Everything goes upstream

Ruben. There is no fork-only feature. Every endpoint, type and designer surface is built to land in nextcloud/tables, with Tables conventions, regenerated OpenAPI and upstream review. The fork is a staging area, not a product.

### P9. Search is improved, not accepted

Ruben. `LIKE` over text cells is the first version so the endpoint ships in sprint 1; a real search follows in the plan rather than waiting on a benchmark verdict.

### P10. Delete behaviour is property configuration

Ruben. What happens to a relation target or a file when a row goes is a setting on the column, like mandatory or default, not a global rule. The column editor offers unlink, trash or delete where the type allows it.

### P11. Calendar items and contacts are column types

Ruben. Both are added through the server's `OCP\\Calendar` and `OCP\\Contacts` interfaces, which ship with every server. The cell stores a uid; the owning app edits the item.

### P12. Everything is backwards compatible

Ruben. A table, view, column, share or API call that works today works unchanged after this project. New fields are nullable and default to today's behaviour. The v1 and v2 APIs keep their shapes; new capabilities arrive as new parameters and new endpoints.

### P13. A frontend store with a cache

Components call the Tables API directly, which puts caching on the backend alone. Before that becomes a performance problem, the Tables frontend gets one store: a per-source cache of collections and items, request coalescing so two widgets asking for the same page share one call, invalidation on row create, update and delete, and an optional subscription to row events later. The nc-vue object store already has this shape and is the model. On the roadmap in sprint 2, next to server-side paging.

### P14. Context becomes application in code and API

Ruben. The user interface already says application; the code, the database and the API say context. The 2.0 release renames code and API to application, adds `api/2/applications` routes and keeps `api/2/contexts` as aliases for one release.

### P15. One identifier: the technical name

Ruben. Columns and views already have `technicalName`; applications and menu items got it on fork pull request #7, which also dropped the fork's `slug`. Tables get the same field with the same pattern. Row input by technical name is an existing pattern, `dataByAlias`, and the object format on `api/2` builds on it.

### P16. One API: the frontend consumes `api/2`

Ruben. Tables has three APIs: `api/1` and `api/2` over OCS and 48 internal routes that only the frontend uses and that no OpenAPI document describes. Every new capability lands on `api/2` only, the frontend store (P13) is pointed at `api/2`, and the internal routes retire in 2.0 once nothing calls them. The OpenAPI document then describes the whole surface, with a named schema for every response, and the JSON Schema endpoint per table describes the data. `api/1` stays frozen for its outside consumers.

Why: an API the primary consumer does not use is not tested by use, and a frontend on undocumented routes cannot be replaced by another client.

### P6. Parity scope and data layer

Ruben. Parity is the application runtime, the designer and packaging. Flows, automation and the AI companion are out. The data layer is Tables rows. See [goals and scope](01-goals-and-scope.md).
