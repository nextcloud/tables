<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V.
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Risks

Part of the [buildiq parity project](README.md). Each risk names the trigger that tells us it is happening and the response.

| # | Risk | Likelihood | Impact | Trigger | Response |
|---|---|---|---|---|---|
| 1 | The entity, attribute, value storage is too slow for server-side filtering, facets and aggregations on large tables | medium | high | the sprint 2 benchmark on a table of 100k rows and 30 columns misses 500 ms for a filtered page | add covering indexes first; then a per-table materialised view for aggregates; nested properties stay deferred |
| 2 | Nested object and array properties are needed earlier than planned by a real application | medium | high | an application in the acceptance set cannot be modelled flat | model as child tables with relations; the JSON cell type stays deferred |
| 3 | The frontend switch to server-side paging breaks existing Tables behaviour (client search, filters, counters) | high | medium | Playwright suite red on existing specs | keep client-side mode for tables below a threshold; server mode only for application pages at first |
| 4 | Upstream review cadence slows merges into nextcloud/tables | high | medium | a fork PR older than three weeks without review | the fork ships on 16 February regardless; upstream merges are a parallel track with smaller, feature-sliced PRs |
| 5 | Two full-time developers and a half-time owner cannot cover both the data layer and the designer by the freeze | medium | high | sprint 4 review shows milestone M2 incomplete | drop packaging (M5) behind the freeze first, then visibility rules and the settings page type |
| 6 | Row uuids and routes touch federation and user migration, which have few tests | medium | medium | psalm or the Behat suite flags the mapper changes | write Behat scenarios for uuid routes before the migration lands |
| 7 | File attachments collide with Files' access rules | medium | medium | a user sees a file through a row they could not open in Files | the attachment column stores file ids only and asks Files for access on every read; no copies |
| 8 | The generated app's dependency chain (Tables version, Nextcloud version) is unclear at release time | low | medium | the generated app fails `occ app:install` on a clean instance in sprint 7 | pin the Tables minimum version in the template and test the generated app in CI from sprint 6 |
| 9 | Scope creep from buildiq features that look small (notes, presence, saved view trees) | high | low | a PR adds an entity not in the component list | the component list is the scope; additions need a decision in the sprint review |
| 10 | Holidays in sprint 6 (21 December to 3 January) | certain | low | none needed | sprint 6 is planned at 8 working days and carries only closing work |

Two of these already have evidence. Risk 1 is the concern the research note raised, and the Tables UI today loads every row into the browser, which hides the cost. Risk 3 is why the frontend paging switch is scheduled in sprint 2, before the API gains filters, so the suite proves the rendering path first.
