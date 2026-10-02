<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Administration

Run these commands on the Nextcloud server as the web user, from the Nextcloud root directory:

```bash
sudo -E -u www-data php occ tables:list
```

In the Nextcloud Docker development container the equivalent is `php occ` from `/var/www/html` as `www-data`.

If `occ` reports that Nextcloud or an app requires an upgrade, run `occ upgrade` first. Until that finishes, Tables commands are not registered.

## Requirements

The database must support a native JSON type. PostgreSQL, MySQL, and MariaDB do. SQLite is supported for development.

Tables is not built for thousands of rows in one table. Loading, memory use, and browser responsiveness can suffer at that size.

## List and show tables

`tables:list` prints tables as JSON. Pass a user ID to limit the list to that user. `--no-shares` keeps only tables that user owns. `--count` prints a count.

```text
occ tables:list [-c|--count] [-s|--no-shares] [--] [<user-id>]
```

## Add a table

`tables:add` creates a table for `user-id` with the given title. `--emoji` sets an emoji. `--template` copies a template's columns and sample rows.

Template names are `todo`, `members`, `customers`, `vacation-requests`, `weight`, and `tutorial`. `tutorial` is available to this command only. The create dialog in the app offers the other five, under the titles ToDo list, Members, Customers, Vacation requests, and Weight tracking.

```text
occ tables:add [-e|--emoji [EMOJI]] [-t|--template [TEMPLATE]] [--] <user-id> <title>
```

## Update a table

`tables:update` changes the title, description, emoji, or archive status. The title is optional when you only change another field. `--archived` marks the table archived. The same command is available as `tables:rename`.

```text
occ tables:update [-e|--emoji [EMOJI]] [-d|--description DESCRIPTION] [-a|--archived] [--] <ID> [<title>]
```

## Owner, delete, and cleanup

`tables:owner` transfers table `ID` to `user-id`.

```text
occ tables:owner <ID> <user-id>
```

`tables:remove` deletes the table with that ID.

```text
occ tables:remove <ID>
```

`tables:clean` checks table data. `--dry` prints the changes and does not write them.

```text
occ tables:clean [-d|--dry]
```

`tables:legacy:clean` does the same check for legacy data.

```text
occ tables:legacy:clean [-d|--dry]
```

`tables:legacy:transfer:rows` copies legacy rows into the current schema. Pass comma-separated table IDs, or `--all` for every table. `--delete` removes rows already stored in the new structure before the transfer.

```text
occ tables:legacy:transfer:rows [--all] [--delete [DELETE]] [--] [<table-ids>]
```

## Applications

In the app these are applications. The commands call them contexts.

`tables:contexts:list` prints applications as JSON. Without a user ID it lists every application. With a user ID it lists the applications available to that user.

```text
occ tables:contexts:list [<user-id>]
```

`tables:contexts:show` prints one application. `context-id` is required and comes from `tables:contexts:list`. The user ID is optional and shows the application as that user sees it.

```text
occ tables:contexts:show <context-id> [<user-id>]
```

## Webhooks

When webhook listeners are enabled on the server, Tables emits:

- `OCA\Tables\Event\RowAddedEvent`
- `OCA\Tables\Event\RowUpdatedEvent`
- `OCA\Tables\Event\RowDeletedEvent`

The payload `values` object uses column indexes as keys (`"0"`, `"1"`, `"2"`, and so on), in column order. The keys are not column titles. `previousValues` is set on update and delete. Read a real delivery when you build a filter, because the JSON shape is what listeners receive.

See the [Nextcloud webhook listeners documentation](https://docs.nextcloud.com/server/latest/admin_manual/webhook_listeners/index.html) for how to register a listener.
