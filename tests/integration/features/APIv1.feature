# SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
Feature: APIv1

	Background:
		Given user "participant1" exists
		Given user "participant2" exists
		Given user "participant3" exists
		And group "phoenix" exists
		And group "HierarchyA" exists
		And group "HierarchyB" exists
		And group "HierarchyC" exists
		And user "participant1" is member of group "phoenix"
		And user "participant1" is member of group "HierarchyA"
		And user "participant1" is member of group "HierarchyB"
		And user "participant2" is member of group "phoenix"
		And user "participant2" is member of group "HierarchyB"
		And user "participant2" is member of group "HierarchyC"
		And user "participant3" is member of group "HierarchyC"

  @api1
  Scenario: User has initial table
    Then user "participant1" has the following tables
      | Welcome to Nextcloud Tables! |

  @api1
  Scenario: User creates, rename and delete a table
    Given table "my new awesome table" with emoji "🤓" exists for user "participant1" as "base1"
    Then user "participant1" has the following tables
      | my new awesome table |
    Then user "participant1" updates table with keyword "awesome" set title "renamed table" and optional emoji "🍓"
    Then user "participant1" updates table with keyword "renamed table" set title "renamed table without emoji" and optional emoji ""
    Then user "participant1" deletes table with keyword "without emoji"
    Then user "participant1" has the following tables
      | Welcome to Nextcloud Tables! |

  @api1
  Scenario: Table sharing with a user
    Given table "Ready to share" with emoji "🥪" exists for user "participant1" as "base1"
    Then user "participant1" shares table with user "participant2"
    Then user "participant2" has the following permissions
      | read    | 1 |
      | create  | 1 |
      | update  | 1 |
      | delete  | 0 |
      | manage  | 0 |
    Then user "participant2" has the following tables
      | Welcome to Nextcloud Tables! | Ready to share |
    Then user "participant1" sets permission "read" to 0
    Then user "participant1" sets permission "update" to 0
    Then user "participant2" has the following permissions
      | read    | 0 |
      | create  | 1 |
      | update  | 0 |
      | delete  | 0 |
      | manage  | 0 |
    Then user "participant1" deletes table with keyword "Ready to share"
    Then user "participant1" has the following tables
      | Welcome to Nextcloud Tables! |
    Then user "participant2" has the following tables
      | Welcome to Nextcloud Tables! |

	@api1 @table-sharing @view-sharing @permissions
	Scenario: Table sharing with inheriting permissions
		Given table "Main table" with emoji "🥪" exists for user "participant1" as "t1"
		And user "participant1" shares "table" "t1" with "group" "HierarchyB"
		And user "participant1" sets permission "delete" to 1
		And user "participant1" sets permission "manage" to 1
		And user "participant1" shares "table" "t1" with "group" "HierarchyC"
		And user "participant1" sets permission "create" to 0
		And user "participant1" sets permission "update" to 0
		When user "participant1" create view "Specific view" with emoji "⚡️" for "t1" as "v1"
		And user "participant1" shares "view" "v1" with "group" "HierarchyC"
		And user "participant1" sets permission "create" to 0
		And user "participant1" sets permission "update" to 0
		Then user "participant2" has the following permissions against "view" "v1"
			| read    | 1 |
			| create  | 1 |
			| update  | 1 |
			| delete  | 1 |
			| manage  | 1 |

  @api1 @table-sharing
  Scenario: Inaccessible table sharing with a user
    Given table "Ready to share" with emoji "🥪" exists for user "participant1" as "base1"
    And user "participant3" exists
    When user "participant2" attempts to share the table with user "participant3"
    Then the reported status is "404"
    And user "participant3" has the following tables
      | Welcome to Nextcloud Tables! |

  @api1
  Scenario: Table sharing with a group
    Given table "Ready to share" with emoji "🥪" exists for user "participant1" as "base1"
    Then user "participant1" shares table with group "phoenix"
    Then user "participant2" has the following tables
      | Welcome to Nextcloud Tables! | Ready to share |
    Then user "participant1" deletes table with keyword "Ready to share"
    Then user "participant1" has the following tables
      | Welcome to Nextcloud Tables! |
    Then user "participant2" has the following tables
      | Welcome to Nextcloud Tables! |

  @api1
  Scenario: Create and check columns
    Given table "Column test" with emoji "🥶" exists for user "participant1" as "base1"
    Then table has at least following columns
    Then column "First column" exists with following properties
      | type           | text                   |
      | subtype        | line                   |
      | mandatory      | 0                      |
      | description    | This is a description! |
      | customSettings | {"width": 100} |
    Then column "Resources" exists with following properties
      | type               | text                    |
      | subtype            | link                    |
      | mandatory          | 0                       |
      | textAllowedPattern | files,tables,url        |
    Then column "Second column" exists with following properties
      | type          | number                  |
      | mandatory     | 1                       |
      | description   | Scala from 0 to 15      |
      | numberMin     | 0                       |
      | numberMax     | 15                      |
      | numberDefault | 8                       |
    Then column "Third column" exists with following properties
      | type          | number                  |
      | subtype       | progress                |
      | mandatory     | 0                       |
      | description   | Progress test           |
      | numberDefault | 20                      |
    Then table has at least following columns
      | First column  |
      | Second column |
      | Third column  |
      | Resources     |
    Then set following properties for last created column
      | title         | Second column renamed   |
      | mandatory     | 0                       |
      | description   | Scala from 5 to 10      |
      | numberMin     | 5                       |
      | numberMax     | 10                      |
      | numberDefault | 5                       |
    Then user deletes last created column
    Then table has at least following columns
      | First column  |
    Then user "participant1" deletes table with keyword "Column test"

  @api1 @rows
  Scenario: Create, modify and delete rows
    Given table "Rows check" with emoji "👨🏻‍💻" exists for user "participant1" as "base1"
    Then column "one" exists with following properties
      | type          | text                    |
      | subtype       | line                    |
      | mandatory     | 0                       |
      | description   | This is a description!  |
    Then column "two" exists with following properties
      | type          | number                  |
      | mandatory     | 1                       |
      | numberDefault | 10                      |
      | description   | This is a description!  |
    Then column "three" exists with following properties
      | type          | selection               |
      | subtype       | check                   |
      | mandatory     | 1                       |
      | description   | This is a description!  |
    Then column "four" exists with following properties
      | type          | datetime                |
      | subtype       | date                    |
      | mandatory     | 0                       |
      | description   | This is a description!  |
    Then row exists with following values
      | one           | AHA                     |
      | two           | 88                      |
      | three         | true                    |
      | four          | 2023-12-24              |
    Then set following values for last created row
      | one           | AHA!                    |
      | two           | 99                      |
      | three         | false                   |
      | four          | 2020-02-04              |
    Then user deletes last created row
    Then user "participant1" deletes table with keyword "Rows check"

  @api1 @rows
  Scenario: Create, modify and delete rows (legacy interface)
    Given table "Rows check legacy" with emoji "👨🏻‍💻" exists for user "participant1" as "base1"
    Then column "one" exists with following properties
      | type          | text                    |
      | subtype       | line                    |
      | mandatory     | 0                       |
      | description   | This is a description!  |
    Then column "two" exists with following properties
      | type          | number                  |
      | mandatory     | 1                       |
      | numberDefault | 10                      |
      | description   | This is a description!  |
    Then column "three" exists with following properties
      | type          | selection               |
      | subtype       | check                   |
      | mandatory     | 1                       |
      | description   | This is a description!  |
    Then column "four" exists with following properties
      | type          | datetime                |
      | subtype       | date                    |
      | mandatory     | 0                       |
      | description   | This is a description!  |
    Then row exists with following values via legacy interface
      | one           | AHA                     |
      | two           | 88                      |
      | three         | true                    |
      | four          | 2023-12-24              |
    Then set following values for last created row via legacy interface
      | one           | AHA!                    |
      | two           | 99                      |
      | three         | true                    |
      | four          | 2020-02-04              |
    Then user deletes last created row
    Then user "participant1" deletes table with keyword "Rows check"

  @api1 @import
  Scenario Outline: Import a document file
    Given user "participant1" uploads file "<importfile>"
    And table "Import test" with emoji "👨🏻‍💻" exists for user "participant1" as "base1"
    When user imports file "/<importfile>" into last created table
    Then import results have the following data
      | found_columns_count     | 10 |
      | created_columns_count   | 10 |
      | inserted_rows_count     |  2 |
      | errors_count            |  0 |
    Then table has at least following typed columns
      | Col1    | text      |
      | Col2    | text      |
      | Col3    | text      |
      | num     | number    |
      | emoji   | text      |
      | special | text      |
      | date    | datetime  |
      | truth   | selection |
    Then table contains at least following rows
      | Date and Time    | Col1    | Col2   | Col3   | num   | emoji | special  | date       | truth | time  |
      | 2022-02-20 08:42 | Val1    | Val2   | Val3   | 1     | 💙    | Ä        | 2024-02-24 | false | 18:48 |
      | 2016-06-01 13:37 | great   | news   | here   | 99    | ⚠     | Ö        | 2016-06-01 | true  | 01:23 |

  Examples:
    | importfile                   |
    | import-from-libreoffice.ods  |
    | import-from-libreoffice.xlsx |
    | import-from-ms365.xlsx       |
    | import-from-libreoffice.csv  |

  @api1 @import @rows
  Scenario: Import a document file that updates existing rows
    Given table "Import check" with emoji "👨🏻‍💻" exists for user "participant1" as "base1"
    Then column "one" exists with following properties
      | type        | text                   |
      | subtype     | line                   |
      | mandatory   | 1                      |
      | description | This is a description! |
    Then column "two" exists with following properties
      | type        | number                 |
      | mandatory   | 1                      |
      | description | This is a description! |
    Then row exists with following values
      | one | AHA |
      | two | 88  |
    Given file "update-rows.csv" exists for user "participant1" with the following data
      | ID      | one         | two     |
      | {rowId} | AHA updated | 99      |
      |         | new row     | 100     |
    When user imports file "update-rows.csv" into last created table
    Then import results have the following data
      | found_columns_count    | 3 |
      | matching_columns_count | 2 |
      | created_columns_count  | 1 |
      | inserted_rows_count    | 1 |
      | updated_rows_count     | 1 |
      | errors_count           | 0 |
    Then table contains at least following rows
      | one         | two |
      | AHA updated | 99  |
      | new row     | 100 |

  @api1 @import
  Scenario: Import a document with optional field
    Given user "participant1" uploads file "import-from-libreoffice-optional-fields.csv"
    And table "Import test" with emoji "👨🏻‍💻" exists for user "participant1" as "base1"
    When user imports file "/import-from-libreoffice-optional-fields.csv" into last created table
    Then import results have the following data
      | found_columns_count     |  9 |
      | created_columns_count   |  9 |
      | inserted_rows_count     |  2 |
      | errors_count            |  0 |
    # At the moment, we only take the first row into account, when determining the cell format
    # Hence, it is expected that all turn out to be text
    Then table has at least following typed columns
      | Case    | text      |
      | Col1    | text      |
      | num     | text    |
      | emoji   | text      |
      | special | text      |
      | date    | text  |
      | truth   | text |
    # the library handles "true" as boolean and so is converted into the text representation "1"
    Then table contains at least following rows
      | Case | Date and Time    | Col1    | num   | emoji | special  | date       | truth | time  |
      | A    | | | | | | | | |
      | B    | 2016-06-01 13:37 | great   |  99    | ⚠     | Ö        | 2016-06-01 | 1     | 01:23 |

  @api1
  Scenario: Create, edit and delete views
    Given table "View test" with emoji "👨🏻‍💻" exists for user "participant1" as "view-test"
    # Then print register
    Then table "view-test" has the following views for user "participant1"
    # Then print register
    When user "participant1" create view "first view" with emoji "⚡️" for "view-test" as "first-view"
    Then table "view-test" has the following views for user "participant1"
      | first view |
    # Then print register
    When user "participant1" update view "first-view" with title "updated first view" and emoji "💾"
    Then table "view-test" has the following views for user "participant1"
      | updated first view |
    When user "participant1" deletes view "first-view"
    Then table "view-test" has the following views for user "participant1"

  @api1 @views
  Scenario: Column can be added to a view
    Given table "Private One" with emoji "🤫" exists for user "participant1" as "table_p1"
    Then column "Volatile Notes" exists with following properties
      | type          | text            |
      | subtype       | line            |
      | mandatory     | 0               |
      | description   | Note me a thing |
    And user "participant1" create view "Simple View" with emoji "🙃" for "table_p1" as "simple-view"
    When user "participant1" sets columnSettings "Volatile Notes,-1" to view "simple-view"
    Then the reported status is "200"

  @api1 @views
  Scenario: Foreign or nonexistent columns cannot be added to a view
    Given table "Private One" with emoji "🤫" exists for user "participant1" as "table_p1"
    Then column "Volatile Notes" exists with following properties
      | type          | text            |
      | subtype       | line            |
      | mandatory     | 0               |
      | description   | Note me a thing |
    And table "Private Two" with emoji "🥶" exists for user "participant2" as "table_p2"
    And user "participant2" create view "Sneaky View" with emoji "🫣" for "table_p2" as "sneaky-view"
    When user "participant2" sets columnSettings "Volatile Notes" to view "sneaky-view"
    Then the reported status is "400"

  @api1 @contexts @contexts-sharing
  Scenario: Share an owned context
    Given table "Table 1 via api v2" with emoji "👋" exists for user "participant1" as "t1" via v2
    And table "Table 2 via api v2" with emoji "📸" exists for user "participant1" as "t2" via v2
    And user "participant1" creates the Context "c1" with name "Enchanting Guitar" with icon "tennis" and description "Lorem ipsum dolor etc pp" and nodes:
      | alias | type  | permissions         |
      | t1    | table | read,create,update  |
      | t2    | table | read                |
    When user "participant2" attempts to fetch Context "c1"
    Then the reported status is "404"
    When user "participant1" shares the Context "c1" to "user" "participant2"
    Then the reported status is "200"
    When user "participant2" fetches Context "c1"
    Then the fetched Context "c1" has following data:
      | field | value                        |
      | node  | table:t1:read,create,update  |
      | node  | table:t2:read                |

  @api1 @contexts @contexts-sharing
  Scenario: Share an inaccessible context
    Given table "Table 1 via api v2" with emoji "👋" exists for user "participant1" as "t1" via v2
    And table "Table 2 via api v2" with emoji "📸" exists for user "participant1" as "t2" via v2
    And user "participant1" creates the Context "c1" with name "Enchanting Guitar" with icon "tennis" and description "Lorem ipsum dolor etc pp" and nodes:
      | alias | type  | permissions         |
      | t1    | table | read,create,update  |
      | t2    | table | read                |
    And user "participant3" exists
    When user "participant2" shares the Context "c1" to "user" "participant3"
    Then the reported status is "404"
    When user "participant3" attempts to fetch Context "c1"
    Then the reported status is "404"

  @api1 @views @view-filters
  Scenario: Create row through filtered view with selection filter
    Given table "Team Features" with emoji "✨" exists for user "participant1" as "team-features"
    Then column "team" exists with following properties
      | type          | selection              |
      | mandatory     | 1                      |
      | description   | The team responsible   |
      | selectionOptions | [{"id":0,"label":"Office team"},{"id":1,"label":"Dev team"},{"id":2,"label":"Design team"}] |
    Then column "feature" exists with following properties
      | type          | text                   |
      | subtype       | line                   |
      | mandatory     | 1                      |
      | description   | The feature to build   |
    And user "participant1" create view "Office Features" with emoji "👔" for "team-features" as "office-features"
    When user "participant1" sets columnSettings "feature" to view "office-features"
    And user "participant1" sets filter to view "office-features"
      | column  | operator | value           |
      | team    | is-equal | @selection-id-0 |
    When user "participant1" tries to create a row using v2 on "view" "office-features" with following values
      | feature                | Fancy tables features |
    And user "participant1" create view "Design Features" with emoji "🎨" for "team-features" as "design-features"
    When user "participant1" sets columnSettings "feature" to view "design-features"
    And user "participant1" sets filter to view "design-features"
      | column  | operator | value           |
      | team    | is-equal | @selection-id-2 |
      | feature | contains | Design          |
    When user "participant1" tries to create a row using v2 on "view" "design-features" with following values
      | feature                | Design system components |
    Then table contains at least following rows
      | team          | feature                |
      | 0  | Fancy tables features  |
      | 2  | Design system components |
    Then view "office-features" has exactly the following rows
      | feature                |
      | Fancy tables features  |
    Then view "design-features" has exactly the following rows
      | feature                |
      | Design system components |

  @api1 @views @view-filters
  Scenario: Create row through filtered view with text filter
    Given table "Project Tasks" with emoji "📝" exists for user "participant1" as "project-tasks"
    Then column "status" exists with following properties
      | type          | text                   |
      | subtype       | line                   |
      | mandatory     | 1                      |
      | description   | The task status        |
    Then column "task" exists with following properties
      | type          | text                   |
      | subtype       | line                   |
      | mandatory     | 1                      |
      | description   | The task description   |
    And user "participant1" create view "Open Tasks" with emoji "🔔" for "project-tasks" as "open-tasks"
    When user "participant1" sets columnSettings "task" to view "open-tasks"
    And user "participant1" sets filter to view "open-tasks"
      | column  | operator | value    |
      | status  | is-equal | open     |
    When user "participant1" tries to create a row using v2 on "view" "open-tasks" with following values
      | task                   | Implement new feature |
    And user "participant1" create view "Completed Tasks" with emoji "✅" for "project-tasks" as "completed-tasks"
    When user "participant1" sets columnSettings "task" to view "completed-tasks"
    And user "participant1" sets filter to view "completed-tasks"
      | column  | operator | value      |
      | status  | is-equal | completed  |
    When user "participant1" tries to create a row using v2 on "view" "completed-tasks" with following values
      | task                   | Fix reported bug |
    Then table contains at least following rows
      | status     | task                  |
      | open       | Implement new feature |
      | completed  | Fix reported bug      |
    Then view "open-tasks" has exactly the following rows
      | task                  |
      | Implement new feature |
    Then view "completed-tasks" has exactly the following rows
      | task              |
      | Fix reported bug  |

  @api1 @views @view-filters
  Scenario: Create row through filtered view with boolean selection filter
    Given table "Task List" with emoji "📋" exists for user "participant1" as "task-list"
    Then column "is_important" exists with following properties
      | type          | selection             |
      | subtype       | check                 |
      | mandatory     | 1                     |
      | description   | Important task flag   |
    Then column "task" exists with following properties
      | type          | text                  |
      | subtype       | line                  |
      | mandatory     | 1                     |
      | description   | The task description  |
    And user "participant1" create view "Important Tasks" with emoji "⭐" for "task-list" as "important-tasks"
    When user "participant1" sets columnSettings "task" to view "important-tasks"
    And user "participant1" sets filter to view "important-tasks"
      | column       | operator  | value |
      | is_important | is-equal  | true  |
    When user "participant1" tries to create a row using v2 on "view" "important-tasks" with following values
      | task                   | Critical feature |
    And user "participant1" create view "Regular Tasks" with emoji "📌" for "task-list" as "regular-tasks"
    When user "participant1" sets columnSettings "task" to view "regular-tasks"
    And user "participant1" sets filter to view "regular-tasks"
      | column       | operator  | value |
      | is_important | is-equal  | false |
    When user "participant1" tries to create a row using v2 on "view" "regular-tasks" with following values
      | task                   | Minor update |
    Then table contains at least following rows
      | is_important | task            |
      | true        | Critical feature |
      | false       | Minor update     |
    Then view "important-tasks" has exactly the following rows
      | task             |
      | Critical feature |
    Then view "regular-tasks" has exactly the following rows
      | task         |
      | Minor update |

  @api1 @views @view-filters
  Scenario: Create row through filtered view with text column default value
    Given table "Task List" with emoji "📋" exists for user "participant1" as "task-list"
    Then column "task" exists with following properties
      | type          | text                  |
      | subtype       | line                  |
      | mandatory     | 1                     |
      | description   | The task description  |
    Then column "category" exists with following properties
      | type        | text          |
      | subtype     | line          |
      | mandatory   | 1             |
      | description | Task category |
      | textDefault | general       |
    And user "participant1" create view "General Tasks" with emoji "📝" for "task-list" as "general-tasks"
    When user "participant1" sets columnSettings "task" to view "general-tasks"
    And user "participant1" sets filter to view "general-tasks"
      | column       | operator  | value   |
      | category     | is-equal  | general |
    When user "participant1" tries to create a row using v2 on "view" "general-tasks" with following values
      | task                   | New general task |
    Then table contains at least following rows
      | task             | category |
      | New general task | general  |
    Then view "general-tasks" has exactly the following rows
      | task             |
      | New general task |
