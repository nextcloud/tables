<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V.
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Types and formats

Part of the [buildiq parity project](README.md). Measured on OpenRegister at 1dc6a466 and the Tables fork at `feat/application-shell`.

## Types Tables is missing

- **json**: an opaque dictionary or blob, validated against an optional schema fragment, never queried.
- **file**: a Files node id per cell, multi-valued for attachments; Files owns access, versions and sharing.
- **array**: a list of scalars with a subtype, one cell per value.
- **tag**: a Nextcloud system tag per cell, multi-valued.
- **format** on text columns: not a type but the concept Tables lacks, with a server-side validator registry.

Relation exists and is extended. User, group and team exist as the usergroup type. Everything else maps onto an existing type.

## Nextcloud server entities as types

The rule: Tables cannot and must not depend on another app. So a column type may point at anything the Nextcloud server itself provides through `OCP` interfaces, and at nothing that lives in a separate app. Tables already honours this: its usergroup type uses `OCA\Circles` for teams through an optional check, and it implements reference and search providers from `OCP\Collaboration` and `OCP\Search`.

| Entity | Where it lives | Tables today | Plan |
|---|---|---|---|
| user | server, `OCP\IUserManager` | usergroup type, `usergroupSelectUsers` | exists |
| group | server, `OCP\IGroupManager` | usergroup type, `usergroupSelectGroups` | exists |
| team | bundled Circles app, optional | usergroup type, `usergroupSelectTeams` | exists |
| file or folder | server, `OCP\Files` | nothing; text link at best | new type **file** |
| system tag | server, `OCP\SystemTag` | nothing | new type **tag**, S to M; gives the tags tab and facet for free |
| share | server, `OCP\Share` | Tables shares its own nodes | not a cell type; the sharing tab reads Tables shares |
| comment | server, `OCP\Comments` | nothing | not a cell type; a notes tab on a row can use comments on the row, M, later |
| activity | server, `OCP\Activity` | Tables emits activity | the activity tab reads it; the audit trail is Tables' own |
| calendar event | server dav app, `OCP\Calendar\IManager` | nothing | possible as a link type storing calendar and event uid; see question Q8 |
| contact | server dav app, `OCP\Contacts\IManager` | nothing | possible as a link type storing addressbook and contact uid; see question Q8 |
| task | dav app, VTODO through `OCP\Calendar` | nothing | same route as calendar event; not before the release |
| notification | server, `OCP\Notification` | Tables sends some | not a type |

Calendar events and contacts are the border case. The interfaces are in `OCP` and the dav app ships with every server, so a type is allowed. But reading and writing events through `OCP\Calendar` is thinner than the Calendar app's own API, and buildiq's calendar widget reads through the Calendar app. The plan keeps them out of the data layer cluster and asks Q8.

## Integrations we do not carry over

OpenRegister and nextcloud-vue integrate with other Nextcloud apps through providers and leaves. A leaf is another Conduction app that registers its own tabs and widgets on an object. Tables takes none of that as a dependency. The list, with what happens to each.

| Integration | Depends on | In Tables |
|---|---|---|
| files, version history | server | yes, through the file type and Files |
| tags | server | yes, through the tag type |
| shares | server and Tables shares | yes, the sharing tab |
| activity | server | yes, the activity tab |
| audit trail | OpenRegister's own | yes, Tables' own row audit |
| notes, tasks on an object | OpenRegister's own | no; a notes tab may come later on `OCP\Comments` |
| calendar, contacts | dav app | later, question Q8 |
| deck | Deck app | no |
| talk | Talk app | no |
| collectives | Collectives app | no |
| forms, polls | Forms and Polls apps | no; the form page type covers intake forms |
| maps, photos | Maps and Photos apps | no; the map widget renders geo columns itself |
| cospend, openproject, bookmarks, analytics | separate apps | no |
| email, contactmoment, time tracker, field inspection | Mail app and Conduction apps | no |
| xwiki, BRP, KvK, OpenCorporates | external services | no |
| flow, message dispatch | OpenRegister flows | no, out of scope with automation |
| leaves | other Conduction apps | no; a leaf app can consume the Tables API, Tables does not load leaves |

What a Conduction app loses by this: a buildiq app that showed Deck cards or Talk conversations on a detail page will not have those tabs on Tables. That is the price of a Tables that installs anywhere. The widget slot system stays open, so an app that ships its own code can still add a tab; Tables will not ship it.

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
| array of objects | relation, multi-valued, exists | reuse; add filter, sort, inverse lookup, inline resolve, import and export remapping |
| object | relation, single-valued, exists | reuse; same additions |
| dictionary | nothing | **json** type, opaque |
| file | nothing | new type **file**, one cell per Files node id |
| user and group | usergroup | map |
| relation (`$ref`) | relation | map; add inverse lookup and inline extend |
| selection with options | selection | map; `enum` in JSON Schema |
| datetime, date, time | datetime, subtypes date and time | map |

Three new column types: array, json and file. Relation exists and is extended, not rebuilt: today it stores several target ids per cell with `targetId`, `relationType` and `labelColumn` in the column settings and validates that the target exists, but it cannot filter, sort, resolve inline, answer an inverse lookup or survive import and export. Boolean and integer need no new storage, only a mapping rule.

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
4. Build the three new storage types: json first, then file, then array. Extend relation with filter, sort, inverse and resolve.

Sizes: the registry with the first twelve formats is S to M. Each storage type is M, because a column type touches about twenty-nine files from migration to OpenAPI.
