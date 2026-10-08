<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V.
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Types and formats

Part of the [buildiq parity project](README.md). Measured on OpenRegister at 1dc6a466 and the Tables fork on 8 October 2026.

## How each side models a value

OpenRegister follows JSON Schema. A property has a `type` from eight values and an optional `format` that narrows it. Validation runs server-side through Opis JSON Schema, with custom resolvers for the formats that are not in the standard.

Tables has a `type` from six values and a `subtype` that changes storage, editor and rendering. There is no `format` concept. Validation on write covers mandatory, text length, number range, unique text, link protocol, relation target existence and parsing. A regex per text column is stored as `textAllowedPattern` and not enforced on the server. Every column also has a free `customSettings` JSON field.

So Tables does not support `format` the way we do. It needs the concept, a place to store it, a server-side validator per format, and a mapping to JSON Schema in both directions.

## Types

| OpenRegister type | Tables today | Plan |
|---|---|---|
| string | text, subtypes line, link, long, rich | map; formats narrow it |
| number | number | map |
| integer | number with decimals 0 | map; export as integer when decimals is 0 |
| boolean | selection-check | map |
| array of scalars | selection-multi and usergroup hold lists; no general array | new type **array** with a scalar subtype, one cell per value |
| array of objects | nothing | relation, multi-valued, to a child table |
| object | nothing | relation, single-valued, to a child table |
| dictionary | nothing | **json** type, opaque |
| file | nothing | new type **file**, one cell per Files node id |
| user and group | usergroup | map |
| relation (`$ref`) | relation | map; add inverse lookup and inline extend |
| selection with options | selection | map; `enum` in JSON Schema |
| datetime, date, time | datetime, subtypes date and time | map |

Four new column types: array, json, file, and a date-time with timezone if the benchmark shows the current datetime cell loses it. Boolean and integer need no new storage, only a mapping rule.

## Formats

OpenRegister's schema editor offers these formats, and the backend registers resolvers for the ones marked with a validator.

| Format | OpenRegister validator | Tables today | Plan |
|---|---|---|---|
| text, markdown, html | none, rendering only | text long and text rich | map to subtypes |
| date-time, date, time | date-time has a resolver | datetime subtypes | map |
| duration | standard | nothing | validator, S |
| email, idn-email | standard | nothing | validator, S |
| hostname, idn-hostname, ipv4, ipv6 | standard | nothing | validator, S, low priority |
| uri, uri-reference, iri, iri-reference, url, uri-template | standard | text link checks protocol | validator, S |
| uuid | resolver | nothing | validator, S |
| regex | standard | nothing | validator, S |
| json-pointer, relative-json-pointer | standard | nothing | skip unless a widget needs it |
| color and six colour variants | resolver, hex default, rgba, oklch | nothing | validator, S; the grid widgets already validate hex |
| bsn | resolver | nothing | validator, S |
| semver | resolver | nothing | validator, S |
| cron | resolver | nothing | skip, schedules are out of scope |
| user | resolver against the user manager | usergroup type | map |
| binary, csv, pdf, ods, json | used as file content types | nothing | file type with an accepted content type list |
| pattern (regex on the property) | standard | stored, not enforced | enforce on the server, S |

Formats buildiq apps use that OpenRegister does not name as formats, and that the formats registry should carry from day one: postal code, phone, iban, kvk, rsin and percentage. They are validators of a few lines each.

## What this means for the build

1. Add `format` to a column, stored in a new column or in `customSettings`, see question Q6. Expose it in the column editor as a dropdown filtered by type.
2. Add a server-side formats registry: one class per format, a `validate(value): ?string` method, registered by type. Run it in the column type's `validateValue`, next to the existing checks. Enforce `textAllowedPattern` there as well.
3. Map both ways in the JSON Schema export and import: `type` and `subtype` and `format` to JSON Schema `type` and `format`, with `enum` for selection, `$ref` for relation, `items` for array.
4. Build the three new storage types: json first, then file, then array.

Sizes: the registry with the first twelve formats is S to M. Each storage type is M, because a column type touches about twenty-nine files from migration to OpenAPI.
