<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V.
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# buildiq parity on Nextcloud Tables

We want to build applications in Nextcloud Tables the way buildiq builds them on OpenRegister today, without OpenRegister. This is the plan. It is written for Conduction and Nextcloud GmbH together.

Ship date 16 February 2027, code freeze 15 January 2027.

| Document | What it answers |
|---|---|
| [Goals and scope](01-goals-and-scope.md) | what parity means, what is in and out, who works on it, when it is done |
| [Feature comparison](02-feature-comparison.md) | every buildiq capability against what the Tables fork has today |
| [Component list](03-component-list.md) | the 229 library components involved, their verdict, and the data adapter contract |
| [Data model and gaps](04-data-model-and-gaps.md) | how OpenRegister concepts map onto Tables and the 19 gaps ranked by effort |
| [Architecture](05-architecture.md) | the principles and the shape of the result |
| [Project plan](06-project-plan.md) | milestones, seven sprints, owners, dependencies, deferral order |
| [Risks](07-risks.md) | ten risks with triggers and responses |
| [Testing and acceptance](08-testing-and-acceptance.md) | what every change carries, the acceptance paths, the benchmarks, the release checklist |
| [Questions and proposals](09-questions-and-proposals.md) | what still needs an answer and from whom, and what Conduction proposes and why |
| [Types and formats](10-types-and-formats.md) | OpenRegister property types and formats against Tables column types, the missing types, the format proposal |
| [Rows API](12-rows-api.md) | who depends on the API today, the current index and single row responses, the proposed object format |
| [API index](13-api-index.md) | every route split into internal, `api/1` and `api/2`, and the decision to build on `api/2` only |
| [Component destinations](14-component-destinations.md) | every component with its destination, nc-vue or Tables, the week it moves, and whether it has a screenshot |
| [Kick-off agenda](11-meeting-agenda.md) | the first meeting with Nextcloud GmbH: integration direction, rows API and slugs, types, dependencies, user reviews |

Our colleague's [research note](../research/functionality-research-tables-openregister.md) asked the questions. These documents answer them.

## In one paragraph

Tables already has the bones of an application builder. Tables, columns, views, applications with menus, shares, schemes, import and export all exist. On the fork we added the rest of the foundation: a widget grid on views, widget schemas on the server, menu items on applications and a standalone app shell. None of that is in Nextcloud Tables yet. We send it upstream as one pull request before sprint 1, and everything below builds on it. Two developers, a half-time owner and a half-time tester then deliver the rest in seven sprints. Packaging comes last. If the data layer slips, packaging is the first thing to move behind the freeze.

## What is still missing

- Query power on the rows API: search, filter, sort, paging with a total, field selection, an object-shaped response.
- Row identity: a uuid per row and routes by uuid.
- Column formats: a `format` field, a server-side validator registry, pattern enforcement.
- Six column types: json, file, array, tag, calendar item and contact. Relation extended with filter, sort, inverse lookup and inline resolve.
- Technical names on tables and applications and a default CRUD API per application and table.
- A frontend store with a cache.
- The rename of context to application in code and API.
- One API: the frontend on `api/2`, the 48 internal routes retired.
- Search beyond `LIKE`.
- JSON Schema export and import of a table and of an application.
- Aggregations: value, grouped and timeseries.
- Row extras: audit trail, advisory locks, facets.
- Page types: detail, form, settings, logs as a read-only index.
- The remaining widgets: data lists, aggregations, detail widgets, related objects.
- Designer editors: visibility rules, actions, sidebar, index configuration, menu sections, templates.
- Application settings and preferences, group permissions on menu items, pages and widgets.
- A manifest format with export and import, including a buildiq v2 import.
- A packager that generates an installable Nextcloud app from an application.

## Where to start

Read [goals and scope](01-goals-and-scope.md) first, then the [project plan](06-project-plan.md). Before the kick-off, read the [agenda](11-meeting-agenda.md) and the [questions and proposals](09-questions-and-proposals.md) it draws on. Everything else is reference for the people building it.
