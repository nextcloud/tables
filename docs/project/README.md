<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V.
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# buildiq parity on Nextcloud Tables

A project plan for bringing the application builder that buildiq offers on OpenRegister to Nextcloud Tables, without OpenRegister. Written on 8 October 2026 for Conduction and Nextcloud GmbH.

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

The [research note](../research/functionality-research-tables-openregister.md) of 6 October is the starting point these documents answer.

## In one paragraph

Tables already holds the structural half of an application builder: tables, columns, views, applications with menus, shares, schemes, import and export, and since October a widget grid with server-side widget schemas and a standalone app shell. What is missing is query power on the API, row identity, row-level extras such as files and audit, the detail and form page types, the remaining designer editors, a manifest format, and a packager. Two developers full time, a half-time owner and a half-time tester deliver that in seven sprints, with packaging as the last milestone and the first to move behind the freeze if the data layer slips.
