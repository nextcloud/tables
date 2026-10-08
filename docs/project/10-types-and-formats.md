<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V.
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Types and formats

Part of the [buildiq parity project](README.md). We measured OpenRegister at 1dc6a466 and the Tables fork at `feat/application-shell`.

## Types Tables is missing

- **json**: an opaque dictionary or blob, validated against an optional schema fragment, never queried.
- **file**: a Files node id per cell, multi-valued for attachments; Files owns access, versions and sharing.
- **array**: a list of scalars with a subtype, one cell per value.
- **tag**: a Nextcloud system tag per cell, multi-valued.
- **calendar item**: a calendar and event uid per cell, through `OCP\Calendar`.
- **contact**: an address book and contact uid per cell, through `OCP\Contacts`.
- **geo**: a point or GeoJSON shape, for the map widget and page; stored as json with format `geojson`, so it needs no own storage.

Relation exists and gets extended. User, group and team exist as the usergroup type. Boolean, integer, rich text, link, progress and rating map onto existing types and subtypes. Money, percentage and duration are formats on number and text, not types. A formula column is not needed for parity, because aggregations cover what the widgets ask. Nothing else is missing.

Legend in the tables below: ✅ Tables has it, ◐ a smaller version exists, ❌ missing.

## Nextcloud server entities as types

The rule is that Tables cannot and must not depend on another app. So a column type may point at anything the Nextcloud server provides through `OCP` interfaces, and at nothing that lives in a separate app. Tables already honours this in part. Its usergroup type uses `OCA\Circles` for teams behind an optional check, and it implements reference and search providers from `OCP\Collaboration` and `OCP\Search`.

| Entity | Where it lives | Tables today | Plan |
|---|---|---|---|
| user | server, `OCP\IUserManager` | ✅ usergroup type, `usergroupSelectUsers` | exists |
| group | server, `OCP\IGroupManager` | ✅ usergroup type, `usergroupSelectGroups` | exists |
| team | bundled Circles app, optional | ✅ usergroup type, `usergroupSelectTeams`, see the note below | exists |
| file or folder | server, `OCP\Files` | nothing; text link at best | new type **file** |
| system tag | server, `OCP\SystemTag` | nothing | new type **tag**, S to M; gives the tags tab and facet for free |
| share | server, `OCP\Share` | Tables shares its own nodes | not a cell type; the sharing tab reads Tables shares |
| comment | server, `OCP\Comments` | nothing | not a cell type; a notes tab on a row can use comments on the row, M, later |
| activity | server, `OCP\Activity` | Tables emits activity | the activity tab reads it; the audit trail is Tables' own |
| calendar event | server dav app, `OCP\Calendar\IManager` | nothing | possible as a link type storing calendar and event uid; see question Q8 |
| contact | server dav app, `OCP\Contacts\IManager` | nothing | possible as a link type storing addressbook and contact uid; see question Q8 |
| task | dav app, VTODO through `OCP\Calendar` | nothing | same route as calendar event; not before the release |
| notification | server, `OCP\Notification` | Tables sends some | not a type |

Calendar items and contacts go through `OCP\Calendar\IManager` and `OCP\Contacts\IManager`. Those interfaces are in the server and the dav app ships with every server, so the types are allowed, proposal P11. They are thinner than the Calendar and Contacts apps' own APIs. A cell stores the uid, the server resolves it to a title and a date or a name, and editing happens in the owning app. That is enough for the people and calendar widgets.

## Integrations we do not carry over

OpenRegister and nextcloud-vue integrate with other Nextcloud apps through providers and leaves. A leaf is another Conduction app that registers its own tabs and widgets on an object. Tables takes none of that as a dependency. Here is the list, with what happens to each.

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

What a Conduction app loses by this: a buildiq app that showed Deck cards or Talk conversations on a detail page will not have those tabs on Tables. That is the price of a Tables that installs anywhere. The widget slot stays open, so an app that ships its own code can still add a tab. Tables will not ship it.

## How each side models a value

OpenRegister follows JSON Schema. A property has a `type` from eight values and an optional `format` that narrows it. Validation runs on the server through Opis JSON Schema, with custom resolvers for the formats outside the standard.

Tables has a `type` from six values and a `subtype` that changes storage, editor and rendering. There is no `format`. On write it checks mandatory, text length, number range, unique text, link protocol, relation target existence and parsing. A regex per text column is stored as `textAllowedPattern` and not enforced on the server. Every column also has a free `customSettings` JSON field.

So Tables does not support `format` the way we do. It needs the concept, a place to store it, a validator per format on the server, and a mapping to JSON Schema in both directions.

## Types

| OpenRegister type | Tables today | Plan |
|---|---|---|
| string | ✅ text, subtypes line, link, long, rich | map; formats narrow it |
| number | ✅ number | map |
| integer | ◐ number with decimals 0 | map; export as integer when decimals is 0 |
| boolean | ✅ selection-check | map |
| array of scalars | ◐ selection-multi and usergroup hold lists; no general array | new type **array** with a scalar subtype, one cell per value |
| array of objects | ✅ relation, multi-valued | reuse; add filter, sort, inverse lookup, inline resolve, import and export remapping |
| object | ✅ relation, single-valued | reuse; same additions |
| dictionary | ❌ | **json** type, opaque |
| file | ❌ | new type **file**, one cell per Files node id |
| user and group | ✅ usergroup | map |
| relation (`$ref`) | ✅ relation | map; add inverse lookup and inline extend |
| selection with options | ✅ selection | map; `enum` in JSON Schema |
| datetime, date, time | ✅ datetime, subtypes date and time | map |

New column types: json, file, array, tag, calendar item and contact. Relation exists and gets extended, not rebuilt. Today it stores several target ids per cell, with `targetId`, `relationType` and `labelColumn` in the column settings, and it checks that the target exists. It cannot filter, sort, resolve inline, answer an inverse lookup, or survive import and export. Boolean and integer need no new storage, only a mapping rule.

## Formats

OpenRegister's schema editor offers these formats. The backend registers resolvers for the ones marked with a validator.

| Format | OpenRegister validator | Tables today | Plan |
|---|---|---|---|
| text, markdown, html | none, rendering only | ✅ text long and text rich | map to subtypes |
| date-time, date, time | date-time has a resolver | ✅ datetime subtypes | map |
| duration | standard | ❌ | validator, S |
| email, idn-email | standard | ❌ | validator, S |
| hostname, idn-hostname, ipv4, ipv6 | standard | ❌ | validator, S, low priority |
| uri, uri-reference, iri, iri-reference, url, uri-template | standard | ◐ text link checks protocol | validator, S |
| uuid | resolver | ❌ | validator, S |
| regex | standard | ❌ | validator, S |
| json-pointer, relative-json-pointer | standard | ❌ | skip unless a widget needs it |
| color and six colour variants | resolver, hex default, rgba, oklch | ❌ | validator, S; the grid widgets already validate hex |
| bsn | resolver | ❌ | validator, S |
| semver | resolver | ❌ | validator, S |
| cron | resolver | ❌ | skip, schedules are out of scope |
| user | resolver against the user manager | ✅ usergroup type | map |
| binary, csv, pdf, ods, json | used as file content types | ❌ | file type with an accepted content type list |
| pattern (regex on the property) | standard | ◐ stored as `textAllowedPattern`, not enforced | folded into format, see the proposal |

buildiq apps also use formats that OpenRegister never named: postal code, phone, iban, kvk, rsin and percentage. The registry carries them from day one. Each is a validator of a few lines.

## Proposal: format on columns

A column gets a `format`. It narrows a type the way JSON Schema does. A text column with format `email` only accepts email addresses. A number with format `percentage` stays between 0 and 100. A json column with format `geojson` must parse as GeoJSON. The current `textAllowedPattern` becomes the format `pattern` with the regex as its argument. Nothing existing breaks, and existing patterns are enforced at last. For the person configuring a column it is one dropdown instead of a regex, with the common cases ready: email, phone, url, uuid, postal code, bsn, iban, kvk, rsin, color, percentage, money, duration.

Backwards compatibility, proposal P12. A column without a format behaves exactly as today. Importing an old scheme sets no format. The API accepts rows for a formatted column as before and only rejects values that fail the format, which is what an enforced pattern would have done.

Storage is a nullable `format` column on `tables_columns`, added by a migration, plus a `formatOptions` json for the argument, such as the regex or the currency. A dedicated column rather than a key in `customSettings`, because the export, the filter UI and the JSON Schema mapping all read it.

## What this means for the build

1. Add `format` and `formatOptions` to a column with a migration. Expose format in the column editor as a dropdown filtered by type.
2. Add a server-side formats registry: one class per format, a `validate(value, options): ?string` method, registered by type. Run it in the column type's `validateValue`, next to the existing checks. Migrate `textAllowedPattern` into format `pattern`.
3. Map both ways in the JSON Schema export and import: `type` and `subtype` and `format` to JSON Schema `type` and `format`, with `enum` for selection, `$ref` for relation, `items` for array.
4. Build the new storage types: json first, then file and tag, then array, then calendar item and contact. Extend relation with filter, sort, inverse and resolve.

Sizes: the registry with the first twelve formats is S to M. Each storage type is M, because a column type touches about twenty-nine files from migration to OpenAPI. If you add a type, start from the usergroup type and follow its twenty-nine files; if you add a format, start from the registry and add one class and one test.