<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V.
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Testing and acceptance

Part of the [buildiq parity project](README.md). Thijn owns this page; developers add a line per feature before the sprint review.

## What every change carries

- Unit tests for every new or changed PHP class, run file by file in a container with a Nextcloud install. The full suite deletes every Tables row of the database it runs against, so it runs only on throwaway instances.
- Playwright for every user path: one spec per feature, run against a clean instance, seeding its own data.
- psalm with no errors, code style clean, the generated OpenAPI document up to date, eslint and stylelint clean.
- A screenshot pass at desktop and phone width for every new surface, reviewed by a person, before the pull request.
- A live walk of the demo path on a clean instance by the developer, and by Thijn before the sprint review.

## Acceptance paths

Each definition-of-done item in [goals and scope](01-goals-and-scope.md) maps to one Playwright spec and one manual script. The spec is the gate, the script catches what a spec cannot see.

| # | Path | Spec | Manual script |
|---|---|---|---|
| 1 | Create an application with a slug, open it at its address, see its menu as the navigation, first page opens | `grid-view.spec.ts` (exists) | open in a browser on phone width, check the navigation collapses and reopens |
| 2 | Add pages of every type, design a dashboard with every widget type, configure each widget | `application-pages.spec.ts` | one dashboard per widget group, screenshot each |
| 3 | Build a data model with relations both ways, a file attachment, uuids, a validated format; export as JSON Schema; import it on a second instance | `data-model.spec.ts`, `json-schema.spec.ts` | round trip between two instances |
| 4 | A list page filters, sorts, searches and pages on the server; a dashboard shows counts and sums | `row-query.spec.ts`, `aggregations.spec.ts` | the 100k row benchmark table |
| 5 | A detail page shows files, audit trail, relations and lock state; two users see the lock | `detail-page.spec.ts` | two browsers |
| 6 | A menu entry, page and widget gated by group disappear for a user outside the group | `visibility.spec.ts` | three accounts |
| 7 | Export an application, generate the app, install it on a clean instance, open it | `packaging.spec.ts` on the generated ZIP | `occ app:install` on a fresh container |

## Benchmarks

Measured on one instance with PostgreSQL 16 and the row query API, repeated in sprint 2, 4 and 7:

| Case | Target |
|---|---|
| Filtered, sorted page of 50 rows from a table of 100k rows and 30 columns | under 500 ms |
| Count under the same filter | under 300 ms |
| Grouped count on one selection column over 100k rows | under 1 s |
| Opening an application shell with 10 menu items | under 1 s to first page |

Numbers above target are a risk 1 trigger, see [risks](07-risks.md).

## Personas

Thijn runs the application paths with the three personas that fit application building: the functional administrator who builds, the colleague who uses, and the screen reader user. Findings go into the sprint as bugs, not into the backlog.

## Release checklist for 16 February

1. Every acceptance spec green on the release candidate.
2. The benchmark table within target.
3. The generated app installs on a clean Nextcloud with the released Tables.
4. Upgrade test from the previous Tables release with existing tables, views and applications.
5. Screenshots refreshed in the documentation.
6. Changelog written, pull requests referenced, upstream pull requests opened for the slices Nextcloud GmbH agreed to take.
