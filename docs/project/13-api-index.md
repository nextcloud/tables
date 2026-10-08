<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V.
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# API index

Part of the [buildiq parity project](README.md). This is every route of the Tables fork at branch `refactor/upstream-shape`, read from `appinfo/routes.php`. Routes are grouped by capability and split into the three APIs that exist today. The last column is the design decision this project seeks: where the capability lives on `api/2` once proposal P16 is accepted.

The counts are 48 internal, 40 on `api/1` and 36 on `api/2`, with the CORS preflight route left out. Only `api/1` and `api/2` appear in `openapi.json`.

How to read the table. A capability with only internal routes is one the frontend uses and no other client can. One with `api/1` and internal routes exists twice. One with all three exists three times.

| Capability | Internal | `api/1` | `api/2` | Target on `api/2` |
|---|---|---|---|---|
| tables | `GET /table/templates`<br>`GET /table`<br>`GET /table/{id}`<br>`POST /table`<br>`PUT /table/{id}`<br>`DELETE /table/{id}` | `GET /api/1/tables`<br>`POST /api/1/tables`<br>`PUT /api/1/tables/{tableId}`<br>`GET /api/1/tables/{tableId}`<br>`DELETE /api/1/tables/{tableId}`<br>`GET /api/1/tables/{tableId}/relations` | `GET /api/2/tables`<br>`GET /api/2/tables/{id}`<br>`POST /api/2/tables`<br>`PUT /api/2/tables/{id}`<br>`DELETE /api/2/tables/{id}` | exists; `GET /api/2/tables/{id}/views` and templates to add |
| views | `GET /view/table/{tableId}`<br>`GET /view`<br>`GET /view/{id}`<br>`POST /view`<br>`POST /view/standalone`<br>`PUT /view/{id}`<br>`DELETE /view/{id}`<br>`DELETE /view/{viewId}/row/{id}`<br>`GET /view/{viewId}/row/{id}/present` | `GET /api/1/tables/{tableId}/views`<br>`POST /api/1/tables/{tableId}/views`<br>`GET /api/1/views/{viewId}`<br>`PUT /api/1/views/{viewId}`<br>`DELETE /api/1/views/{viewId}`<br>`GET /api/1/views/{viewId}/relations` | `GET /api/2/views/widget-types`<br>`POST /api/2/views` | `/api/2/views` collection, item, by table; standalone create exists |
| columns | none | `GET /api/1/tables/{tableId}/columns`<br>`GET /api/1/views/{viewId}/columns`<br>`POST /api/1/columns`<br>`POST /api/1/tables/{tableId}/columns`<br>`PUT /api/1/columns/{columnId}`<br>`GET /api/1/columns/{columnId}`<br>`DELETE /api/1/columns/{columnId}` | `GET /api/2/columns/{nodeType}/{nodeId}`<br>`GET /api/2/columns/{id}`<br>`POST /api/2/columns/number`<br>`POST /api/2/columns/text`<br>`POST /api/2/columns/selection`<br>`POST /api/2/columns/datetime`<br>`POST /api/2/columns/usergroup` | exists on `api/2`; delete and update per type to check |
| rows | `GET /row/table/{tableId}`<br>`GET /row/{id}`<br>`GET /row/view/{viewId}`<br>`PUT /row/{id}/column/{columnId}`<br>`PUT /row/{id}`<br>`DELETE /row/{id}` | `GET /api/1/tables/{tableId}/rows/simple`<br>`GET /api/1/tables/{tableId}/rows`<br>`GET /api/1/views/{viewId}/rows`<br>`POST /api/1/views/{viewId}/rows`<br>`POST /api/1/tables/{tableId}/rows`<br>`GET /api/1/rows/{rowId}`<br>`DELETE /api/1/views/{viewId}/rows/{rowId}`<br>`PUT /api/1/rows/{rowId}`<br>`DELETE /api/1/rows/{rowId}` | none | the new rows API, see [rows API](12-rows-api.md) |
| shares | `GET /share/table/{tableId}`<br>`GET /share/view/{viewId}`<br>`GET /share/policy`<br>`GET /share/{id}`<br>`POST /share`<br>`PUT /share/{id}/permission`<br>`PUT /share/{id}/permissions`<br>`PUT /share/{id}/display-mode`<br>`DELETE /share/{id}` | `GET /api/1/shares/{shareId}`<br>`GET /api/1/views/{viewId}/shares`<br>`GET /api/1/tables/{tableId}/shares`<br>`POST /api/1/shares`<br>`DELETE /api/1/shares/{shareId}`<br>`PUT /api/1/shares/{shareId}`<br>`PUT /api/1/shares/{shareId}/display-mode`<br>`POST /api/1/tables/{tableId}/shares` | none | `/api/2/shares` with node type and id |
| applications | none | none | `GET /api/2/contexts`<br>`GET /api/2/contexts/{contextId}`<br>`POST /api/2/contexts`<br>`PUT /api/2/contexts/{contextId}`<br>`DELETE /api/2/contexts/{contextId}`<br>`PUT /api/2/contexts/{contextId}/pages/{pageId}` | exists on `api/2`; renamed to `applications` in 2.0 (P14) |
| scheme exchange | none | `GET /api/1/tables/{tableId}/scheme` | `GET /api/2/tables/scheme/{id}`<br>`POST /api/2/tables/scheme`<br>`POST /api/2/tables/{id}/scheme/preview-changes`<br>`POST /api/2/tables/{id}/scheme/import`<br>`GET /api/2/contexts/{contextId}/scheme/export`<br>`POST /api/2/contexts/{contextId}/scheme/preview-changes`<br>`POST /api/2/contexts/{contextId}/scheme/import` | exists; grows into the manifest routes |
| ownership | none | none | `PUT /api/2/tables/{id}/transfer`<br>`PUT /api/2/contexts/{contextId}/transfer` | exists on `api/2` |
| import | `POST /import-preview/table/{tableId}`<br>`POST /v2/import/table/{tableId}`<br>`POST /v2/import/view/{viewId}`<br>`POST /import-preview/view/{viewId}`<br>`POST /importupload-preview/table/{tableId}`<br>`POST /importupload-preview/view/{viewId}`<br>`POST /v2/importupload/table/{tableId}`<br>`POST /v2/importupload/view/{viewId}`<br>`POST /importupload/table/{tableId}`<br>`POST /importupload/view/{viewId}`<br>`POST /import/table/{tableId}`<br>`POST /import/view/{viewId}` | `POST /api/1/import/table/{tableId}`<br>`POST /api/1/import/views/{viewId}` | none | `/api/2/import/{nodeType}/{id}` with preview and upload |
| favourites | none | none | `POST /api/2/favorites/{nodeType}/{nodeId}`<br>`DELETE /api/2/favorites/{nodeType}/{nodeId}` | exists on `api/2` |
| config | none | none | `GET /api/2/config/table/{id}`<br>`GET /api/2/config/view/{id}`<br>`POST /api/2/config/{key}` | exists on `api/2` |
| search | `GET /search/all` | none | none | `/api/2/search` |
| navigation | `GET /navigation` | none | none | folded into `GET /api/2/init` or `/api/2/navigation` |
| public share | `POST /s/{token}/authenticate` | none | none | `/api/2/public/{token}/...`, exists in part |
| app shell | `GET /`<br>`GET /app/{contextId}` | none | none | page routes, not an API; stay |
| grid widgets | `GET /grid/widget-types` | none | none | exists on `api/2/views/widget-types`; internal twin retires |
| init | none | none | `GET /api/2/init` | exists on `api/2` |

## The decision this asks for

We ask the kick-off to make proposal P16 a design decision: **`api/2` is the only API Tables adds to from now on, and the only one its frontend calls.** In practice:

1. No new internal route. A capability the frontend needs is an `api/2` route with Nextcloud attributes, a rate limit where it mutates, a named response type and an OpenAPI entry.
2. The frontend store (P13) calls `api/2` for everything. Sprint 2 adds the `api/2` routes that only internal ones offer today: views by table, shares, import with preview and upload, search, navigation.
3. `api/1` is frozen: no new routes, no shape changes, kept for Analytics, Forms and the connectors.
4. The internal routes are removed in 2.0, together with the context to application rename, once nothing calls them. Page routes (`/`, `/app/{technicalName}`, `/view/{id}`, the public share page) stay, because they serve HTML, not data.
5. `openapi.json` then describes the whole data surface, and `GET /api/2/tables/{id}/schema` describes the data itself in JSON Schema.

Why decide it now: every sprint that adds a capability on two APIs doubles the work and the drift. The widget types and the standalone view already exist twice on the fork, one week in. Until the decision is taken, add nothing to the internal routes; put it on `api/2` and update this index.