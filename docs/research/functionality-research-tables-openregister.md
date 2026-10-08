<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V.
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Functionality Research Nextcloud Tables - OpenRegister

Draft 2026-10-06

This document describes the functionality comparison between Nextcloud Tables and OpenRegister at the time of writing, covering the basics needed for the app builder in conduction style.

## Differences in overall architecture

This section describes the biggest differences in overall architecture. This section treats the most eye-catching differences that have implications for adding functionality from OpenRegister into Nextcloud Tables.

As always: we are far from married to our architecture, this is merely here to point out the differences that prompt choices further down the line.

### Data model

The first thing that stands out is the difference in data models. OpenRegister generates dynamic tables in the database, able to adapt definitions, column definitions and more, whereas Nextcloud Tables uses a EAV-model with all values of a certain type in one table, all column definitions in a table and tables in yet another table.

As said before, we (conduction) are far from married to this architecture, but it raises questions and challenges for other architectural decisions. Our biggest concern is performance, in our experience EAV models have challenges when it comes to scalability, with tables that have many columns and a large number of records. We are perfectly happy to accept this can be well-mitigated already, but it is something we are wondering about.

### Identifiers

This one is something we really need to discuss: at this moment we see that Nextcloud Tables only has numeric identifiers. Although it is a perfectly understandable solution, there are uses where the use of numeric identifiers is actively discouraged by standards. We might have to think about an option to use other types of identifiers like a UUID.

We can consider a large number of variations here, for example to accomodate both numeric ids and uuids as separate columns on a table (where the uuid might then be optional, or possibly disabling access to the records by numeric id for example).

## Adding functionalities

### Grouping tables

In Nextcloud Tables we already have the nice possibility to create various views, have applications etc., we might encounter the need to group tables by application, in order to administrate the data for an application efficiently. This might only be a visual thing, but it is good to consider our options here.

### Extending import/export

The current implementation of import and exporting tables is functionally already very nice and we wouldn’t want to touch it. However, what we would like to do is add the possibility to import and export according to the JSON Schema specification, increasing interoperability with definitions from Schema.org, or OpenAPI Specifications. According to the standard, this would only exporting or importing the table definitions, not the table contents itself.

### Custom formats and validators

Closely knit to json schema support would be the possibility to add custom formats for column definitions. An example would be a validator for a postal code or a social security number, for which most countries have their own definitions. If we want to allow for custom formats, we would need to describe if we only want to add a set of extra formats, or if we would add some extra formats, and a way for people to import formats (and corresponding validators) into their installation.

### Relations to files

Another functionality that will have impact, is the possibility to connect an object to files in the filesystem. The most obvious way to do this is to leverage the structures also used by Collaborations in order to get this right on the terms of access control.

More complicated would be connecting a row to a file that already exists in the filesystem, again, because of access control. It might be that we start out (or even have the permanent solution of) only showing a connected file if that file is accessible to you by means of the existing shares.

### GraphQL

In order to allow applications to display nice graphs, we would need GraphQL to request the data specifically needed for the graph, minimising the amount of data requested for that graph.

This is one of the first points where the EAV model might have impact, as adding a GraphQL backend on top of a EAV model might quickly become complex.

### Grid system

To be able to have a drag & drop configuration of pages we need a grid system.

# Conclusion

Nextcloud Tables gives already a great starting point for the App Builder. However, we found a number of fundamental features we would like to start working on.

Most importantly, there are some discussion points we will have to decide on:

- Table grouping
- Extending import/export with import and export functionality for the JSON Schema standard
- Connected to that: how to handle custom formats? Would we want the possibility to add custom formats and validators?
- File connections: Where to start and what about access control?
- GraphQL
- Grid System

### Other discussion points

We like to keep our PRs clean and small, but in some cases (like in case of the grid system), features have the potential to produce large amounts of changes. We will do a first review to make sure we do not send you PRs that are unchecked, untested or otherwise unready for your review, but we hope for your lenience if some of the PRs in the early stages are larger and have more impact than usual.
