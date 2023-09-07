Feature: api/tablesapi
  Background:
    Given user "participant1" exists
     Given user "participant2" exists
     And group "phoenix" exists
     And user "participant1" is member of group "phoenix"
     And user "participant2" is member of group "phoenix"

  Scenario: User has initial table
    Then user "participant1" has the following tables
      | Tutorial |

  Scenario: User creates, rename and delete a table
    Given table "my new awesome table" with emoji "🤓" exists for user "participant1" as "base1"
    Then user "participant1" has the following tables
      | my new awesome table |
    Then user "participant1" updates table with keyword "awesome" set title "renamed table" and optional emoji "🍓"
    Then user "participant1" updates table with keyword "renamed table" set title "renamed table without emoji" and optional emoji ""∆
    Then user "participant1" deletes table with keyword "without emoji"
    Then user "participant1" has the following tables
      | Tutorial |

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
      | Tutorial | Ready to share |
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
      | Tutorial |
    Then user "participant2" has the following tables
      | Tutorial |

  Scenario: Table sharing with a group
    Given table "Ready to share" with emoji "🥪" exists for user "participant1" as "base1"
    Then user "participant1" shares table with group "phoenix"
    Then user "participant2" has the following tables
      | Tutorial | Ready to share |
    Then user "participant1" deletes table with keyword "Ready to share"
    Then user "participant1" has the following tables
      | Tutorial |
    Then user "participant2" has the following tables
      | Tutorial |

  Scenario: Create and check columns
    Given table "Column test" with emoji "🥶" exists for user "participant1" as "base1"
    Then table has at least following columns
    Then column "First column" exists with following properties
      | type          | text                    |
      | subtype       | line                    |
      | mandatory     | 0                       |
      | description   | This is a description!  |
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
      | three         | 1                       |
      | four          | 2023-12-24              |
    Then set following values for last created row
      | one           | AHA!                    |
      | two           | 99                      |
      | three         | 0                       |
      | four          | 2020-02-04              |
    Then user deletes last created row
    Then user "participant1" deletes table with keyword "Rows check"




  Scenario: Import csv table
    Given file "/import.csv" exists for user "participant1" with following data
      | Col1    | Col2   | Col3   | num   | emoji | special  |
      | Val1    | Val2   | Val3   | 1     | 💙    | Ä        |
      | great   | news   | here   | 99    | ⚠️    | Ö        |
    Given table "Import test" with emoji "👨🏻‍💻" exists for user "participant1" as "base1"
    When user imports file "/import.csv" into last created table
    Then import results have the following data
      | found_columns_count     | 6 |
      | created_columns_count   | 6 |
      | inserted_rows_count     | 2 |
      | errors_count            | 0 |
    Then table has at least following columns
      | Col1    |
      | Col2    |
      | Col3    |
      | num     |
      | emoji   |
      | special |
    Then table contains at least following rows
      | Col1    | Col2   | Col3   | num   | emoji | special  |
      | Val1    | Val2   | Val3   | 1     | 💙    | Ä        |
      | great   | news   | here   | 99    | ⚠️    | Ö        |

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
