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
composer run openapi         # Regenerate openapi.json and TS types from PHP annotations
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

`openapi.json` (auto-generated) is the source of truth for the REST API contract. The TypeScript types in `src/types/` are derived from it — always regenerate both together with `composer run openapi` when changing API shapes. Never edit `openapi.json` or `src/types/openapi/openapi.ts` by hand; they are generated artifacts.

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
- After writing or modifying any PHP code, run the following checks before considering the task complete:
  1. `composer run cs:fix` — auto-correct coding-standard violations, then verify with `composer cs:check` that no issues remain.
  2. `composer run psalm` — static analysis; fix every reported type error or logical issue (no suppressions).
  3. `composer run lint` — PHP syntax check across all source files.
- After writing or modifying any frontend code (Vue, JS, TS, CSS/SCSS), run `npm run dev` to verify the frontend compiles without errors before considering the task complete.

### Clean code

Apply standard clean-code practices to every file you touch:

- **Single responsibility** — each class and method does one thing. Split large methods if they handle multiple concerns.
- **Meaningful names** — variables, parameters, and methods must describe their purpose. Avoid abbreviations and generic names like `$data`, `$arr`, or `$tmp`.
- **No dead code** — do not leave commented-out code, unused variables, or unreachable branches in the codebase.
- **Early returns** — prefer guard clauses over deeply nested `if/else` trees.
- **Boolean casts** — use explicit `(bool)` only when a value truly represents a boolean; do not silently coerce unrelated types.
- **Avoid double negatives** — name booleans positively (`isEnabled`, `hasShares`) rather than negatively (`isNotDisabled`).

### Psalm annotations

Never add `@psalm-suppress` annotations to work around a type error. A suppression is a red flag that signals the code or its type annotation is wrong. Fix the root cause instead:

- If the return type annotation does not match what the method actually returns, fix the annotation or the implementation.
- Use explicit, closed Psalm array shapes — `array{columnId: int, order: int}` — never leave a trailing `...` in a shape literal.
- Do not use `@psalm-suppress MismatchingDocblockReturnType` (or any other suppression) just because a Psalm rule is inconvenient to satisfy.

## Architecture Patterns

### Icons (Vue frontend)

Always use the **outline variant** of a `vue-material-design-icons` icon. Import from e.g. `ArchiveArrowDownOutline.vue`, never the filled variant (`ArchiveArrowDown.vue`). This keeps the icon style consistent across the app.

### Boolean getters on Nextcloud DB entities

Do not implement an explicit `isXxx(): bool` method on a class that extends `Entity` (or `EntitySuper`). The base class handles `isXxx` calls via `__call` magic for any `protected bool $xxx` property. Instead, declare the method in the class-level `@method` docblock so that static analysis and IDE completion still work:

```php
 * @method isArchived(): bool
```

### Database queries inside loops

Never build a `IQueryBuilder` query inside a loop. Construct the query once before the loop using `$qb->createParameter('name')` as a placeholder for the value that changes per iteration. Inside the loop call `$qb->setParameter('name', $value, IQueryBuilder::PARAM_*)` to bind the new value. This avoids re-parsing and re-compiling the query on every iteration.

```php
$qb = $this->db->getQueryBuilder();
$qb->select('*')->from($this->table)
    ->where($qb->expr()->in('node_id', $qb->createParameter('chunk')));

foreach (array_chunk($ids, 997) as $chunk) {
    $qb->setParameter('chunk', $chunk, IQueryBuilder::PARAM_INT_ARRAY);
    // ...
}
```

### Unit tests for services with injected dependencies

When a service constructor gains a new dependency, add a corresponding `$this->createMock(NewDependency::class)` in every `setUp()` method that instantiates that service, and pass the mock as the matching constructor argument. Failing to do so causes `ArgumentCountError` at test runtime.

### New REST endpoints

Every new OCS endpoint must carry:
- `#[NoAdminRequired]`
- `#[RequirePermission(...)]` on **every** method that accesses a resource by ID — not just mutation endpoints. Without it, access is only implicitly enforced by the mapper's SQL filter, which is correct but non-obvious and inconsistent. Use `PERMISSION_READ` for read-only or soft-state operations (e.g. archive/unarchive); use `PERMISSION_MANAGE` for mutations.
- `#[UserRateLimit(limit: 20, period: 60)]` for mutation endpoints (see `ImportController` for the pattern)

When adding or auditing a `#[RequirePermission]` attribute, also verify the method body and docblock are consistent:
- a `PermissionError` catch block returning `$this->handlePermissionError($e)`
- `Http::STATUS_FORBIDDEN` in the `@return` docblock union type
- a `403: No permissions` OpenAPI annotation line

Every controller method must return `->jsonSerialize()` directly. Do not add a separate GET round-trip after a create/update/delete — the response body is the authoritative post-mutation state.

After any of the following changes, run `composer run openapi` to regenerate `openapi.json` and the TypeScript types in `src/types/openapi/openapi.ts`. CI fails if either file is stale.

Triggers that require regeneration:
- Adding, removing, or renaming a controller route
- Changing a controller method signature (parameters, return type, or PHPDoc `@param`/`@return` annotations)
- Changing a response shape (adding/removing fields in `ResponseDefinitions.php` or a `jsonSerialize()` method)
- Adding or removing an HTTP status code from a controller method's return type annotation
- Changing a Psalm array-shape type that appears in a public API response

### Selection option values

Selection column values are encoded as magic strings: `@selection-id-{id}` where `{id}` is the selection option's DB primary key. This format is used by the filter component, the sort evaluator, and any condition-based feature (e.g. conditional formatting). Always use this format — never store or compare bare option labels.

On import/export, selection option IDs change. `ColumnService::importColumn()` must return a `selectionOptionIdMap` alongside the new column ID so callers can remap stored option references. Unmapped IDs should be flagged as `broken: true` rather than silently dropped.

### Soft-invalidation ("broken" flag)

When a stored rule, filter, or condition references a column or option that no longer exists (due to deletion, type change, or import remapping), mark it `broken: true` instead of deleting it. Surface the broken state in the UI. Provide an auto-clear path that removes the flag when the rule is next saved in a valid state.

### XSS / CSS injection

- Never use `v-html`, `innerHTML`, `eval`, or `new Function` with user-supplied values.
- Apply dynamic styles via Vue's `:style` binding only.
- Validate any user-supplied CSS color values on the backend with `/^#[0-9a-fA-F]{3,6}$/` before storing. Reject other formats.

### Input validation and value objects

**Validate structured array input at the controller boundary, before the service layer.**

When a controller parameter accepts a structured array (e.g. `$columnSettings`, `$sort`), parse and validate it into a typed value object — such as `ColumnSettings::createFromInputArray()` or `SortRuleSet::createFromInputArray()` — immediately in the controller, before calling any service method. If the input is invalid, return `Http::STATUS_BAD_REQUEST` with a descriptive message. Never pass a raw unvalidated array into a service method.

```php
// Good — validate at controller boundary, pass value objects downstream
try {
    $columnSettingsObj = $columnSettings !== null
        ? ColumnSettings::createFromInputArray($columnSettings)
        : null;
    $sortObj = $sort !== null ? SortRuleSet::createFromInputArray($sort) : null;
} catch (\InvalidArgumentException $e) {
    return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
}
return new DataResponse($this->service->update(..., $columnSettingsObj, $sortObj)->jsonSerialize());
```

**Service methods must declare typed value-object parameters, not raw arrays.**

`TableService::update()` and equivalent methods must accept `?ColumnSettings` and `?SortRuleSet`, not `?array`. This makes the contract explicit and prevents the service from receiving unvalidated data.

**Value objects must throw, not silently coerce.**

`fromArray()` / `__construct()` methods on value objects must throw `\InvalidArgumentException` when required fields are missing or have an incompatible type. Do not add silent casts like `(int)$data['columnId']` that accept garbage input without error — that hides bugs and lets invalid data propagate to the database. The correct pattern is to call `static::assertRequiredFields($data)` (which throws) before casting.
