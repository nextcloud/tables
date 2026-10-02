<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Activity and integrations

## Activity

When the Activity app is enabled, open a table menu and click **Activity**, or open the sidebar and select **Activity**. The list shows changes for that table or view.

The sidebar also shows **Created at**, **Ownership**, and the **Table ID** or **View ID**.

## Notifications

Open **View settings** or **Table settings** and go to **Notifications**. These settings are stored separately for each table and each view.

| Setting | When you are notified |
| --- | --- |
| Content changes | A row is created, updated, or deleted |
| Structure changes | A column is created, updated, or deleted |
| Assigned | You are added in a user or group field |

## Smart picker

In an app that supports smart pickers, such as Text, choose **Nextcloud Tables**. Search for a table or view, then set **Render mode**:

- **Link** inserts a link.
- **Content** inserts the rendered content.

**Preview** shows the result before you insert it.

## Integration tab

Open the sidebar and select **Integration**. It shows the API endpoint for the open table or view, and whether you can **Read**, **Create**, **Update**, **Delete**, and **Manage** it. Copy the endpoint if another app or script should call this table or view.

The request format is described by [`openapi.json`](../openapi.json).
