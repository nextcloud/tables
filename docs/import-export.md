<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Import and export

## Import rows

Open the table menu and click **Import rows**, or choose **Import table** while creating a table.

1. Click **Select from Files** or **Upload from device**.
2. The dialog accepts xlsx, xls, csv, html, and xml. The first row must contain column headings, with no empty heading cells between them.
3. Turn on **Create missing columns** if headings that do not exist yet should be added. You need permission to create columns. Otherwise the dialog warns that you cannot create columns.
4. Click **Preview** and map each file column to a table column. Do not map two file columns onto the same existing column.
5. Click **Import**.

Large files are imported in the background. Tables notifies you when the import finishes.

## Export rows

Export writes a CSV file. The file name starts with the date and time and the table or view title. Emoji are removed from the file name.

From the table or view menu:

- **Export** downloads the rows.
- **Export all rows** downloads every row you can see.
- **Export filtered rows** downloads the rows that match the current filter.
- **Export selected rows** downloads the rows you have selected.

Visible metadata columns, such as **ID** and **Created at**, are included.

## Schemes

A scheme is the structure, not the row data.

- **Import scheme** on a table or application menu loads a scheme file. While creating a table, the same action is **Import Scheme**.
- **Export scheme** on an application downloads that application's structure.

Scheme files used by **Import scheme** are JSON.
