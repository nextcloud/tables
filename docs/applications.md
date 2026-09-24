<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Applications

An application groups tables and views into one place in the sidebar, and can show up as its own entry in the Nextcloud app menu.

`occ` calls an application a context. The screen calls it an application. See [Administration](administration.md) for `tables:contexts:list` and `tables:contexts:show`.

## Create an application

1. Next to **Applications**, click **Create application**.
2. Enter a **Title**. It is required and can be at most 200 characters. Choose an icon with **Select icon**.
3. Optionally enter a **Description**.
4. Under **Resources**, add the tables and views this application contains, and who receives them.
5. Turn on **Show in app list** if this application should appear in the Nextcloud app menu. The note on the switch says this can be overridden by a per-account preference.
6. Click **Create application**.

Open the application from the sidebar to see its tables and views on one page.

## Manage an application

Open the application menu in the sidebar:

- **Edit application** changes the title, icon, description, and resources.
- **Show in app list** is also on this menu. Each account can turn that display on or off for themselves.
- **Import scheme** and **Export scheme** save or restore the application structure as a file. See [Import and export](import-export.md).
- **Transfer application** moves ownership to another user. Pick the user and click **Transfer**.
- **Delete application** removes the application. It does not delete the underlying tables.
