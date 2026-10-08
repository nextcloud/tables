<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V.
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# The rows API, today and proposed

Part of the [buildiq parity project](README.md). Shapes are taken from the controllers and `ResponseDefinitions.php` on the fork; every Tables response sits inside the OCS envelope `{"ocs": {"meta": {...}, "data": ...}}`, which is left out below.

## Who depends on the API today

The v1 API has been public and documented since Tables 0.6 and has consumers outside Tables:

- Nextcloud Analytics reads tables as a data source.
- Nextcloud Forms links a form to a table and writes submissions as rows.
- The n8n community node for Nextcloud Tables and comparable automation connectors.
- Conduction's integriq calls it from its own code.
- Tables' own frontend uses the internal routes, not the OCS API.

Proposal P12 follows from that: v1 and the existing v2 routes keep their shapes. New capability arrives as new parameters with defaults and as new endpoints, never as a changed response.

## Today: the row index

`GET /ocs/v2.php/apps/tables/api/1/tables/{tableId}/rows?limit=&offset=`, also `/api/1/views/{viewId}/rows`. There is no rows endpoint under `api/2`. Parameters: `limit` and `offset` only. Response: a bare list, no total, no paging metadata.

```json
[
  {
    "id": 17,
    "tableId": 4,
    "createdBy": "admin",
    "createdAt": "2026-10-08 14:02:11",
    "lastEditBy": "admin",
    "lastEditAt": "2026-10-08 14:02:11",
    "data": [
      { "columnId": 5, "value": "Jansen" },
      { "columnId": 6, "value": "jansen@example.org" },
      { "columnId": 7, "value": "@selection-id-2" }
    ],
    "dataByAlias": {
      "name": { "columnId": 5, "value": "Jansen" },
      "email": { "columnId": 6, "value": "jansen@example.org" },
      "status": { "columnId": 7, "value": "@selection-id-2" }
    }
  }
]
```

`dataByAlias` is only filled for columns that have a technical name. Selection values are the magic string `@selection-id-{id}`. Relation values are target row ids. Usergroup values are `{id, type, displayName}` objects.

## Today: a single row

`GET /ocs/v2.php/apps/tables/api/1/rows/{rowId}`. Same shape as one element above. Create is `POST /api/1/tables/{tableId}/rows` with `{"data": {"5": "Jansen", "6": "jansen@example.org"}}` keyed by column id; update is `PUT /api/1/rows/{rowId}` with the same body; delete is `DELETE /api/1/rows/{rowId}`.

## Proposed: the object format on `api/2`

New endpoints, new parameters, same OCS envelope. Routes by id and, once tables carry slugs, by slug under the application:

```
GET    /api/2/tables/{tableId}/rows
GET    /api/2/views/{viewId}/rows
GET    /api/2/apps/{applicationSlug}/{tableSlug}
GET    /api/2/rows/{uuid}
POST   /api/2/tables/{tableId}/rows
PUT    /api/2/rows/{uuid}
DELETE /api/2/rows/{uuid}
GET    /api/2/tables/{tableId}/schema
GET    /api/2/tables/{tableId}/aggregate
```

Index parameters:

| Parameter | Example | Meaning |
|---|---|---|
| `search` | `search=jansen` | text search over text and selection cells |
| `filter[column][operator]` | `filter[status][eq]=open&filter[amount][gte]=100` | the twelve operators of the view filter, by technical name or column id |
| `sort` | `sort=-createdAt,name` | minus for descending, several allowed |
| `page`, `limit` | `page=2&limit=25` | default limit 25, maximum 500 |
| `fields` | `fields=name,email` | return only these |
| `extend` | `extend=customer` | resolve relation cells inline |
| `format` | `format=object` | default `object` on `api/2`; `cells` returns today's shape |

Index response:

```json
{
  "results": [
    {
      "id": "3f1c9f2e-6a2b-4b7e-9d1a-0c8e5a7d2b11",
      "name": "Jansen",
      "email": "jansen@example.org",
      "status": "open",
      "customer": { "id": "9b2e…", "name": "Gemeente Zuiddrecht" },
      "@self": {
        "rowId": 17,
        "tableId": 4,
        "createdBy": "admin",
        "createdAt": "2026-10-08T14:02:11+02:00",
        "updatedBy": "admin",
        "updatedAt": "2026-10-08T14:02:11+02:00"
      }
    }
  ],
  "total": 1203,
  "page": 2,
  "pages": 49,
  "limit": 25
}
```

Properties are keyed by technical name; a column without one gets a slug of its title and the schema endpoint says which. Selection values are the option label, with the option id available through the schema's `enum`. Dates are ISO 8601 with offset. Relation cells are ids unless extended. The metadata block carries the integer row id so v1 and v2 can be joined.

Single row: `GET /api/2/rows/{uuid}` returns one object of the same shape. Create and update accept the same object shape keyed by technical name and return the stored object, so a client never needs a second read.

Errors keep Nextcloud's OCS status codes; the body carries `{"message": "..."}` plus, for validation, `{"errors": {"email": "is not a valid email address"}}` per property, which the form components render inline.

## OpenAPI

Tables generates `openapi.json` (OpenAPI 3.0.3, currently 32 OCS paths) from the controller annotations with `composer run openapi`, and the TypeScript types from it. Every new route above regenerates into it. There is no served documentation page; Nextcloud has none for any app. A Redoc or Swagger UI page under the Tables settings is a small addition and is on the kick-off agenda as a question, not in the plan.

Alignment with OpenAPI conventions on the new routes: plural nouns, filter and sort as query parameters, a paging envelope with `total`, consistent error bodies, uuids as identifiers, and every response shape declared as a named type in `ResponseDefinitions.php`. The v1 routes are not changed to match; they are documented as they are.
