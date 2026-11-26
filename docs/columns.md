<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Columns

Open a table and create a column. Every column has a **Title**, an optional **Description**, and a **Type**.

**Mandatory** requires a value before a row can be saved. **Add column to other views** also shows the new column in views you select. **Add more** keeps the dialog open so you can create another column. Click **Save** when the column is ready.

Under **Advanced settings** you can set:

- **Technical name**, used by the API and integrations. It must start with a lowercase letter and contain only lowercase letters, numbers, and underscores. Changing it later means updating anything that calls the API with the old name.
- **Column width**, in pixels, within the minimum and maximum the form accepts.

## Text

Choose **Text**, then one of:

- **Text line** for a single line.
- **Rich text** when the Text app is available.
- **Simple text** when the Text app is not available.

For a text line you can set **Default**, **Allowed pattern (regex)**, **Maximum text length**, and **Unique value**.

## Link

A **Link** column stores a URL or another Nextcloud resource, such as a file. Under **Allowed types**, enable the providers this column may use. The list depends on which apps are enabled on the server. **Show previews** displays a preview when the resource has one.

## Number

- **Number** stores a numeric value. You can set **Default value**, **Decimals**, **Minimum**, **Maximum**, **Prefix**, and **Suffix**. The default must sit between the minimum and the maximum.
- **Stars rating** stores a 0 to 5 star value.
- **Progress bar** stores a progress value.

## Selection

Choose **Selection**, then one of:

- **Single selection**
- **Multiple selection**
- **Yes/No**

Define the options people can pick. Yes/No is a checkbox.

## Date and time

Choose **Date and time**, then one of:

- **Date**
- **Time**
- **Date and time**

## Users and groups

A **Users and groups** column stores accounts and groups. Enable **Users**, **Groups**, and, when Teams is available, **Teams**. At least one of those must stay enabled.

- **Default** pre-selects people, groups, or teams.
- **Select multiple items** allows more than one value.
- **Show user status** shows the status of selected users.

## Relation

A **Relation** column points at a row in another table or view.

1. Set **Relation type** to **Table** or **View**.
2. Set **Target** to the table or view that holds the related rows.
3. Set **Label for relation selection** to the column people see when they pick a row. Only text and number columns can be used as that label.
