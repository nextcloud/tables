<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V.
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Goals and scope

Part of the [buildiq parity project](README.md).

## Goal

Build applications in Nextcloud Tables the way buildiq builds them today, without OpenRegister. An application is a menu of pages over tables. Its owner designs it in place and opens it at its own address. When wanted, it becomes a Nextcloud app of its own.

We ship on 16 February 2027 and freeze the code on 15 January 2027. Work that is not ready by the freeze moves to a later release. Nothing ships half done.

## Why

Tables is the data app Nextcloud users already have. buildiq proved the application model: a manifest, typed data, schema-driven lists and forms, a widget grid, editing in place. Moving that model onto Tables gives it a home with a maintainer base. It removes the OpenRegister installation step for the common case. And it gives Tables the pieces it is missing for building applications. The [research note](../research/functionality-research-tables-openregister.md) lists the discussion points we answer here.

## In scope

Three buildiq layers:

1. **Application runtime.** Applications with a menu, pages of type table, grid, detail, form and settings, widgets with server-validated configuration, actions, visibility rules, a standalone shell at its own address and a top-bar entry.
2. **Designer.** In-place editing of pages, menu, widgets, actions, sidebar and visibility; the data model edited with Tables' own table and column editors; application templates.
3. **Packaging.** Export of an application as a manifest and schemes, and generation of an installable Nextcloud app that depends on Tables.

Plus the data layer work those three need. Tables is the data layer: a schema is a table, an object is a row. The [data model mapping](04-data-model-and-gaps.md) lists what Tables gains.

## Out of scope

Out, and staying out:

- Flows, automation, rule sets, proposal tables, agents, the AI companion and connectors. They stay in OpenRegister and buildiq.
- OpenRegister features buildiq does not use, and OpenRegister features only the excluded layers use (flow runs, tasks, chat threads).
- Layered customisation of already installed fleet apps (admin and user deltas).
- The store plane, scheduled actions, observability metrics, MCP hints.
- Registering an application as a Nextcloud app with its own id without packaging it. Packaging covers that need.

## Deferred past 15 January

Larger and more complex features move behind the freeze rather than ship unfinished. These are the candidates, in the order we would pick them up afterwards:

1. Nested object and array properties in a table.
2. Versions of rows with restore and diff.
3. Access control per row.
4. Facets.
5. OpenAPI import.
6. Lifecycle state machines.
7. The three-pane page designer with live preview.
8. GitHub push from the packager.

## Who

| Role | Person | Share |
|---|---|---|
| Backend | Robert | full time |
| Frontend | Remko | full time |
| Product owner, design, management, code | Ruben | half time |
| Testing | Thijn | half time |

Nextcloud GmbH reviews the pull requests on the fork that are meant for upstream. They decide what goes into nextcloud/tables.

## Definition of done for the project

On 16 February 2027 a Tables user can:

1. create an application, give it a technical name, and open it at `/apps/tables/app/{technicalName}` with its menu as the navigation;

Each item has an acceptance path in [testing and acceptance](08-testing-and-acceptance.md). If you build one of these items, that path is your definition of done.