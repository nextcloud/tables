<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Sharing

You can share a table or a single view. Sharing a view shares that view's columns, filters, and sort. People who only have the view do not see the rest of the table.

Open the table or view menu and click **Share**, or open the sidebar and select **Sharing**.

## Share with people on this server

Under **Share with accounts or groups** (or **Share with accounts, groups or teams** when Teams is enabled), search for an account, group, or team.

Each share has a permission preset:

- **View only** can read rows.
- **Can edit** can read, create, update, and delete rows.
- **Custom permissions** lets you turn **Read**, **Create**, **Update**, and **Delete** on individually. Update and delete stay off unless read is on.

The share menu names the same rights **Read data**, **Create data**, **Update data**, and **Delete data**.

On a view share you can also enable **Manage view**, which lets that person change the view. On a table share, **Promote to table manager** lets that person manage the table, including its structure. **Demote to normal share** removes that manager role. A manager is marked **Table manager** in the share list.

## Links

- **Copy internal link to clipboard** copies a link for people who already have an account on this server and access to the table or view.
- **Create public link** creates a link that works without an account. You can **Set password** before you click **Create**. Use **Copy public share link** to copy it later. The same **View only**, **Can edit**, and **Custom permissions** presets apply to the public link.

## What the levels allow

| Action | View only | Can edit | Manage |
| --- | --- | --- | --- |
| See rows | Yes | Yes | Yes |
| Create, update, and delete rows | No | Yes | Yes |
| Change columns, views, or shares | No | No | Yes, for the shared table or view |

The **Integration** tab in the sidebar lists **Read**, **Create**, **Update**, **Delete**, and **Manage** for your own access to the open table or view.
