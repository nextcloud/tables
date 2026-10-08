<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V.
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# The rows API, today and proposed

Part of the [buildiq parity project](README.md). The shapes come from the controllers and `ResponseDefinitions.php` on the fork. Every Tables response sits inside the OCS envelope `{"ocs": {"meta": {...}, "data": ...}}`, which we leave out below.

## Who depends on the API today

The v1 API has been public and documented since Tables 0.6. It has consumers outside Tables:

- Nextcloud Analytics reads tables as a data source.
- Nextcloud Forms links a form to a table and writes submissions as rows.
- The n8n community node for Nextcloud Tables and comparable automation connectors.
- Conduction's integriq calls it from its own code.
- Tables' own frontend uses the internal routes, not the OCS API.

The fork already added two `api/2` routes in this spirit on pull request #7: `GET /api/2/views/widget-types`, and `POST /api/2/views` for a view without a table. Both are in the OpenAPI document.

Proposal P12 follows from that. The v1 routes and the existing v2 routes keep their shapes. New capability arrives as new parameters with defaults and as new endpoints, never as a changed response.

## Today: the row index

The index is `GET /ocs/v2.php/apps/tables/api/1/tables/{tableId}/rows?limit=&offset=`, and the same under `/api/1/views/{viewId}/rows`. There is no rows endpoint under `api/2`. The only parameters are `limit` and `offset`. The response is a bare list, with no total and no paging metadata.

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

`dataByAlias` is only filled for columns that have a technical name. That alias is the pattern this plan builds on. The object format on `api/2` is `dataByAlias` flattened, and spreading `technicalName` to tables (P15) is the debt between today's API and a default CRUD route per application and table. Selection values are the magic string `@selection-id-{id}`. Relation values are target row ids. Usergroup values are `{id, type, displayName}` objects.

## Today: a single row

A single row is `GET /ocs/v2.php/apps/tables/api/1/rows/{rowId}`, in the same shape as one element above. Create is `POST /api/1/tables/{tableId}/rows` with `{"data": {"5": "Jansen", "6": "jansen@example.org"}}`, keyed by column id. Update is `PUT /api/1/rows/{rowId}` with the same body. Delete is `DELETE /api/1/rows/{rowId}`.

## Proposed: non-breaking additions to `api/2`

Everything below is an addition. No existing route changes its parameters or its response. The components need two resources. The **table endpoint** lists the rows of one table or view as a collection. The **item endpoint** returns one row by uuid. Both sit in the OCS envelope. Routes exist by id and, once tables carry a technical name, by technical name under the application:

```
GET    /api/2/tables/{tableId}/rows
GET    /api/2/views/{viewId}/rows
GET    /api/2/apps/{applicationTechnicalName}/{tableTechnicalName}
GET    /api/2/rows/{uuid}
POST   /api/2/tables/{tableId}/rows
PUT    /api/2/rows/{uuid}
DELETE /api/2/rows/{uuid}
GET    /api/2/tables/{tableId}/schema
GET    /api/2/tables/{tableId}/aggregate
GET    /api/2/rows/{uuid}/relations?direction=used
GET    /api/2/rows/{uuid}/audit
GET    /api/2/columns/{id}/distinct
```

Collection and item, side by side:

| | Table endpoint | Item endpoint |
|---|---|---|
| read | `GET /api/2/tables/{id}/rows` | `GET /api/2/rows/{uuid}` |
| by technical name | `GET /api/2/apps/{application}/{table}` | `GET /api/2/apps/{application}/{table}/{uuid}` |
| create | `POST /api/2/tables/{id}/rows` | |
| update | | `PUT /api/2/rows/{uuid}`, `PATCH` for partial |
| delete | `DELETE /api/2/tables/{id}/rows?ids=` for bulk | `DELETE /api/2/rows/{uuid}` |
| response | `{results, total, page, pages, limit}` | one object |

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

Properties are keyed by technical name. A column without one gets a name derived from its title on first use, stored on the column, and the schema endpoint says which. Selection values are the option label, with the option id available through the schema's `enum`. Dates are ISO 8601 with offset. Relation cells are ids unless extended. The metadata block carries the integer row id, so v1 and v2 can be joined.

A single row, `GET /api/2/rows/{uuid}`, returns one object of the same shape. Create and update accept that same object keyed by technical name and return the stored object, so a client never needs a second read.

Errors keep Nextcloud's OCS status codes. The body carries `{"message": "..."}` and, for validation, `{"errors": {"email": "is not a valid email address"}}` per property, which the form components render inline.

## Proposed: applications as manifests on `api/2`

`api/2` already exports and imports a context scheme, through `GET /contexts/{id}/scheme/export`, `POST /contexts/{id}/scheme/import` and `POST /contexts/{id}/scheme/preview-changes`. The fork's version of that scheme already carries the technical name, the menu items and the grid views with their widgets. The manifest is that scheme grown to the whole application, not a second format:

| Scheme today | Manifest adds |
|---|---|
| name, description, icon, technical name | version, author, licence, required Tables version |
| nodes: the tables and views the application uses | pages with their type, grid and widgets; menu sections and nesting |
| tables with columns and their settings | column `format`, relations by target technical name, the new types |
| menu items, grid views | visibility rules, actions, sidebar configuration, settings, walkthrough and setup text |
| | optional data: rows per table, for templates and demo content |

Routes, all additions:

```
GET    /api/2/contexts/{id}/manifest                 the application as a manifest
POST   /api/2/contexts/manifest                      create an application from a manifest
POST   /api/2/contexts/{id}/manifest                 update an application from a manifest
POST   /api/2/contexts/{id}/manifest/preview-changes what an import would change
GET    /api/2/contexts/{id}/manifest/export?data=1   manifest plus rows as a ZIP
POST   /api/2/contexts/manifest/import               the ZIP back in
```

The existing scheme routes stay and keep returning today's shape. We reuse `preview-changes` as the dry run the designer shows before an import. A buildiq v2 manifest imports through the same `POST` with a `source=buildiq` parameter that switches on the field mapping. What has no home in Tables shows up in the preview and is skipped. The packager in sprint 7 reads the manifest from the first route and writes it into the generated app. On install, the generated app's repair step posts it back through the import route. So import and export, templates, the round trip between two instances and packaging all run through one format and six routes.

## OpenAPI

Tables generates `openapi.json`, OpenAPI 3.0.3 with 55 paths today, from the controller annotations with `composer run openapi`, and the TypeScript types from it. Every new route above regenerates into it. There is no served documentation page, and Nextcloud has none for any app. A Redoc or Swagger UI page under the Tables settings is a small addition. It is a question on the kick-off agenda, not an item in the plan.

On the new routes we follow OpenAPI conventions: plural nouns, filter and sort as query parameters, a paging envelope with `total`, consistent error bodies, uuids as identifiers, and every response shape declared as a named type in `ResponseDefinitions.php`. The v1 routes stay as they are and are documented as they are. When you add a route, start from the parameter table above and the collection and item table, and regenerate the document before you push.