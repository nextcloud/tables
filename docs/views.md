<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Views

A view is a saved way to look at one table. It chooses which columns are visible, how rows are filtered, and how they are sorted. A table can have several views. Each view is listed under its table in the sidebar.

You need to own the table, or be a table manager, to create or edit a view.

## Create a view

1. Open the table menu in the sidebar and click **Create view**.
2. Enter a **Title** and, if you want, a **Description** and an emoji.
3. In **Columns**, choose which columns the view shows and their order.
4. In **Filter**, add the filters described below.
5. In **Sort**, click **Add new sorting rule** and choose a column. Rules run in order: the first rule sorts every row, and later rules sort rows that share the same value for the earlier rules.
6. Save the view.

Open **View settings** from the view menu to change this later. **Save as new view** keeps the current view and stores your edits as another one.

Under **Advanced settings**, **Technical name** is the stable name used by the API. It must start with a lowercase letter and contain only lowercase letters, numbers, and underscores.

## Filters

Each filter is a column, an operator, and a value.

Click **Add new filter group** to start a group. Inside a group, every condition must match. The group is labeled **... that meet all of the following conditions**. Add another condition in that group with **Add new filter**.

Separate groups are combined with **OR**. A row is shown when it matches every condition in at least one group.

Operators depend on the column type. Common ones are:

| Operator | Typical columns |
| --- | --- |
| Contains | Text, link, selection, users and groups, relation |
| Does not contain | Text, link, selection, users and groups, relation |
| Contains items | Selection |
| Begins with | Text line, link |
| Ends with | Text line, link |
| Is equal | Most types |
| Is not equal | Most types |
| Is greater than | Number, stars, progress, date and time |
| Is greater than or equal | Number, stars, progress, date and time |
| Is lower than | Number, stars, progress, date and time |
| Is lower than or equal | Number, stars, progress, date and time |
| Is empty | Most types. No value is required. |
| Is not empty | Most types. No value is required. |

## Magic values

In a filter value, or from **Or use magic values** on a column, you can insert a value that is resolved for the person who opens the view.

| Magic value | Resolves to |
| --- | --- |
| Me (user ID) | The current user's ID |
| Me (name) | The current user's display name |
| Checked | Yes, for a Yes/No column |
| Unchecked | No, for a Yes/No column |
| Star rows from empty to five stars | That star rating |
| Today | Today's date |
| This year | The first day of the current year |
| This month | The first day of the current month |
| This week | The first day of the current week |
| Now | The current time, or the current date and time, depending on the column |
| Exact date | A date you pick |
| Number of days ahead | A date that many days from today |
| Number of days ago | A date that many days before today |

Use **Me (user ID)** on a users column to build a view that shows each person only the rows assigned to them.
