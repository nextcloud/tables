<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# AGENTS.md

This file provides guidance to all AI agents (Claude, Codex, Gemini, etc.) working with code in this repository.

## What This App Is

**Nextcloud Tables** is a Nextcloud app (PHP backend + Vue.js frontend) that lets users create and manage custom data tables with typed columns, views, sharing, and import/export. It ships a full OCS REST API and integrates with the Nextcloud event, activity, search, and reference systems.

## Development Setup

```bash
composer install    # PHP dependencies
npm install         # JS dependencies
npm run watch       # Frontend dev build with watch
```

## Common Commands

### Building
```bash
npm run build           # Production JS build
make build              # Full production build (JS + PHP assembly)
```

### Linting
```bash
make lint               # All linting (PHP, JS, CSS, XML)
make lint-fix           # Auto-fix all
composer cs:check       # PHP coding standards check
composer cs:fix         # PHP coding standards fix
composer psalm          # PHP static analysis (Psalm)
npm run lint            # ESLint (JS/Vue/TS)
npm run lint:fix        # ESLint auto-fix
npm run stylelint       # CSS/SCSS linting
npm run stylelint:fix   # CSS/SCSS auto-fix
```

### Testing
```bash
make test               # All tests (unit + Behat + Cypress)
composer test           # PHP unit tests
composer test:unit:local  # PHP unit tests without Nextcloud bootstrap
npm run tests:component # Cypress component tests
npm run test:e2e        # Playwright E2E tests
npm run test:e2e:ui     # Playwright with interactive UI
```

To run a single PHP test file:
```bash
vendor/bin/phpunit --bootstrap tests/unit/bootstrap.php tests/unit/path/to/TestFile.php
```

### Code Generation
```bash
composer openapi        # Regenerate openapi.json and TS types from PHP annotations
npm run typescript:generate  # Regenerate TS types from openapi.json only
```

## Architecture

### Backend (PHP — `lib/`)

The app follows the standard Nextcloud layered pattern:

- **`lib/Controller/`** — OCS REST API controllers. `Api1Controller.php` is the monolithic v1 handler; newer controllers (e.g. `ApiTablesController`, `ContextController`) are split by resource type. Public (unauthenticated share-token) variants exist alongside standard controllers.
- **`lib/Service/`** — All business logic lives here. Each resource has its own service (TableService, RowService, ColumnService, ViewService, ShareService, ContextService, ImportService, etc.). `PermissionsService` is the central access-control authority.
- **`lib/Db/`** — Doctrine DBAL mappers and entity classes. Row data is stored in typed cell tables (`tables_row_cells_text`, `_number`, `_datetime`, etc.) rather than a single JSON column. `Row2Mapper` handles the join/assembly logic.
- **`lib/Migration/`** — Versioned schema migrations (e.g. `Version000900Date20250710000000.php`).
- **`lib/Middleware/`** — `PermissionMiddleware` and `ShareControlMiddleware` enforce access control before controllers run.
- **`lib/Event/` + `lib/Listener/`** — Domain events (TableDeletedEvent, RowDeletedEvent, etc.) dispatched through Nextcloud's event system; listeners handle audit logging, user-deletion cleanup, and analytics integration.
- **`lib/Model/`** — Value objects and DTOs (FilterGroup, SortRuleSet, ViewUpdateInput, RowDataInput, TableScheme, ColumnSettings, Permissions).

Routes are defined in `appinfo/routes.php` (~100 routes covering the full REST API plus Nextcloud UI hooks).

### Frontend (Vue.js — `src/`)

Single-page app built with **Vue 2.7**, **Vue Router 3**, and **Pinia** (state management). Compiled with **Vite**.

- **`src/store/store.js`** — Primary Pinia store (tables, columns, views, shares, contexts).
- **`src/store/data.js`** — Row/cell data state.
- **`src/modules/`** — UI regions: `navigation/` (left sidebar with contexts/tables), `main/` (table grid view), `sidebar/` (right panel), `modals/` (all dialogs).
- **`src/types/`** — TypeScript interfaces auto-generated from `openapi.json`; do not edit manually.
- **`src/shared/`** — Reusable components and utilities shared across modules.

### Database

Supports PostgreSQL, MySQL, and SQLite. The unusual design detail is that row cell values are stored in **per-type tables** (`tables_row_cells_text`, `tables_row_cells_number`, `tables_row_cells_datetime`, `tables_row_cells_selection`, `tables_row_cells_usergroup`) rather than in one polymorphic column, which affects any queries or migrations touching row data.

### API

`openapi.json` (auto-generated) is the source of truth for the REST API contract. The TypeScript types in `src/types/` are derived from it — always regenerate both together with `composer openapi` when changing API shapes.

## Commits

- All commits must be signed off (`git commit -s`) per the Developer Certificate of Origin (DCO). All PRs target `master`. Backports use `/backport to stable-X.Y` in a PR comment.

- Commit messages must follow the [Conventional Commits v1.0.0 specification](https://www.conventionalcommits.org/en/v1.0.0/#specification) — e.g. `feat(chat): add voice message playback`, `fix(call): handle MCU disconnect gracefully`.

- Every commit made with AI assistance must include an `AI-assistant` trailer identifying the coding agent, its version, and the model(s) used:

  ```
  AI-assistant: Claude Code 2.1.80 (Claude Sonnet 4.6)
  AI-assistant: Copilot 1.0.6 (Claude Sonnet 4.6)
  ```

  General pattern: `AI-assistant: <coding-agent> <agent-version> (<model-name> <model-version>)`

  If multiple models are used for different roles, extend the trailer with named roles:

  ```
  AI-assistant: OpenCode v1.0.203 (plan: Claude Opus 4.5, edit: Claude Sonnet 4.5)
  ```

  Pattern with roles: `AI-assistant: <coding-agent> <agent-version> (<role>: <model-name> <model-version>, <role>: <model-name> <model-version>)`

## Pull Requests

- Include a short summary of what changed. *Example:* `fix: prevent crash on empty todo title`.
- **Pull Request**: When the agent creates a PR, it should include a description summarizing the changes and why they were made. If a GitHub issue exists, reference it (e.g., “Closes #123”).

## Code Style

- Do not use decorative section-divider comments of any kind (e.g. `// ── Title ───`, `// ------`, `// ======`).
- Every new file must end with exactly one empty trailing line (no more, no less).
