Feature: APIv2
  Background:
    Given user "participant1-v2" exists
    Given user "participant2-v2" exists
    Given user "participant3-v2" exists

  @api2
  Scenario: Test initial setup
    Then user "participant1-v2" has the following tables via v2
    | Tutorial |
    Then user "participant1-v2" has the following resources via v2
    | Tutorial |

  @api2
  Scenario: Basic table actions
    Given table "Table 1 via api v2" with emoji "👋" exists for user "participant1-v2" as "t1" via v2
    Then user "participant1-v2" has the following tables via v2
      | Table 1 via api v2 |
    And user "participant1-v2" sees the following table attributes on table "t1"
      | archived | 0 |
    Then user "participant1-v2" updates table "t1" set title "updated title" and emoji "⛵︎" via v2
    Then user "participant1-v2" has the following tables via v2
      | updated title |
    Then user "participant1-v2" updates table "t1" set archived 1 via v2
    And user "participant1-v2" sees the following table attributes on table "t1"
      | archived | 1 |
    Then user "participant1-v2" updates table "t1" set archived 0 via v2
    And user "participant1-v2" sees the following table attributes on table "t1"
      | archived | 0 |
    Then user "participant1-v2" deletes table "t1" via v2

  @api2
  Scenario: Favorite tables
    Given table "Own table" with emoji "👋" exists for user "participant1-v2" as "t1" via v2
    And user "participant1-v2" shares table with user "participant2-v2"
    And user "participant1-v2" adds the table "t1" to favorites
    And user "participant1-v2" sees the following table attributes on table "t1"
      | favorite | 1 |
    And user "participant2-v2" fetches table info for table "t1"
    And user "participant2-v2" sees the following table attributes on table "t1"
      | favorite | 0 |
    When user "participant1-v2" removes the table "t1" from favorites
    And user "participant1-v2" sees the following table attributes on table "t1"
      | favorite | 0 |
    When user "participant3-v2" adds the table "t1" to favorites
    Then the last response should have a "403" status code



  @api2
  Scenario: Basic column actions
    Given table "Table 2" with emoji "👋" exists for user "participant1-v2" as "t2" via v2
    Then column from main type "text" for node type "table" and node name "t2" exists with name "c1" and following properties via v2
      | subtype       | line                    |
      | title         | Beautiful text column   |
      | mandatory     | 0                       |
      | description   | This is a description!  |
    Then column from main type "text" for node type "table" and node name "t2" exists with name "c2" and following properties via v2
      | subtype       | rich                    |
      | title         | Rich is cool            |
      | mandatory     | 1                       |
      | description   | Another description     |
    Then column from main type "number" for node type "table" and node name "t2" exists with name "c3" and following properties via v2
      | title         | Counter                 |
      | mandatory     | 0                       |
    Then column from main type "number" for node type "table" and node name "t2" exists with name "c4" and following properties via v2
      | subtype       | progress                |
      | title         | Progress                |
    Then column from main type "number" for node type "table" and node name "t2" exists with name "c5" and following properties via v2
      | subtype       | check                   |
      | title         | Checking                |
    Then column from main type "datetime" for node type "table" and node name "t2" exists with name "c6" and following properties via v2
      | subtype       | date                   |
      | title         | A single date          |
      | datetimeDefault | today                |
    Then node with node type "table" and node name "t2" has the following columns via v2
      | Beautiful text column | Rich is cool | Counter | Progress | Checking | A single date |
    Then print register

  @api2selection
  Scenario: Create selection columns
    Given table "Table 3" with emoji "👋" exists for user "participant1-v2" as "t3" via v2
    Then column from main type "selection" for node type "table" and node name "t3" exists with name "sel-c1" and following properties via v2
      | title             | sel single                                                  |
      | selectionOptions  | [{"id": 1, "label": "first"},{"id": 2, "label": "second"}]  |
      | selectionDefault  | 2                                                           |
    Then column from main type "selection" for node type "table" and node name "t3" exists with name "sel-c2" and following properties via v2
      | title             | sel multi                                                   |
      | subtype           | multi                                                       |
      | selectionOptions  | [{"id": 1, "label": "first"},{"id": 2, "label": "second"}]  |
      | selectionDefault  | ["1","2"]                                                   |
    Then node with node type "table" and node name "t3" has the following columns via v2
      | sel single  | sel multi |
    Then print register

  @api2usergroup
  Scenario: Create usergroup columns
    Given table "Table 5" with emoji "👋" exists for user "participant1-v2" as "t5" via v2
    Then column from main type "usergroup" for node type "table" and node name "t5" exists with name "ug-c1" and following properties via v2
      | title             | ug column                                                 |
      | usergroupDefault  | [{"id": "admin", "displayName": "admin", "isUser": true}] |
    Then node with node type "table" and node name "t5" has the following columns via v2
      | ug column  |
    Then print register

  @api2transfer
  Scenario: Transfer table
    Given table "Table 4" with emoji "👋" exists for user "participant1-v2" as "t4" via v2
    Then table "t4" is owned by "participant1-v2"
    Then change owner for table "t4" from user "participant1-v2" to user "participant2-v2"
    Then table "t4" is owned by "participant2-v2"

  @api2 @contexts
  Scenario: Create a simple context containing one table and one view
    Given table "Table 1 via api v2" with emoji "👋" exists for user "participant1-v2" as "t1" via v2
    And table "Table 2 via api v2" with emoji "👋" exists for user "participant1-v2" as "t2" via v2
    And user "participant1-v2" create view "v1" with emoji "⚡️" for "t1" as "v1"
    When user "participant1-v2" creates the Context "c1" with name "Enchanting Guitar" with icon "tennis" and description "Lorem ipsum dolor etc pp" and nodes:
      | alias | type  | permissions         |
      | t1    | table | read,create,update  |
      | v1    | view  | read                |
    Then user "participant1-v2" has access to Context "c1"
    And the fetched Context "c1" has following data:
      | field | value                        |
      | name  | Enchanting Guitar            |
      | icon  | tennis                       |
      | node  | table:t1:read,create,update  |
      | node  | view:v1:read                 |
      | page  | startpage:2                  |

  @api2 @contexts
  Scenario: Create a simple shared context containing one table and one view
    Given table "Table 1 via api v2" with emoji "👋" exists for user "participant1-v2" as "t1" via v2
    And table "Table 2 via api v2" with emoji "👋" exists for user "participant1-v2" as "t2" via v2
    And user "participant1-v2" create view "v1" with emoji "⚡️" for "t1" as "v1"
    When user "participant1-v2" creates the Context "c1" with name "Enchanting Guitar" with icon "tennis" and description "Lorem ipsum dolor etc pp" and nodes:
      | alias | type  | permissions         |
      | t1    | table | read,create,update  |
      | v1    | view  | read                |
    Then user "participant1-v2" has access to Context "c1"
    When user "participant1-v2" shares the Context "c1" to "user" "participant2-v2"
    Then user "participant2-v2" has access to Context "c1"
    And user "participant2-v2" fetches Context "c1"
    And the fetched Context "c1" has following data:
      | field | value                        |
      | name  | Enchanting Guitar            |
      | icon  | tennis                       |
      | node  | table:t1:read,create,update  |
      | node  | view:v1:read                 |
      | page  | startpage:2                  |

  @api2 @contexts
  Scenario: Attempt to create a context containing an inaccessible table
    Given table "Table 1 via api v2" with emoji "👋" exists for user "participant1-v2" as "t1" via v2
    And table "Table 2 via api v2" with emoji "👋" exists for user "participant2-v2" as "t2" via v2
    When user "participant1-v2" attempts to create the Context "c1" with name "Enchanting Guitar" with icon "tennis" and description "Lorem ipsum dolor etc pp" and nodes:
      | alias | type  | permissions         |
      | t2    | table | read,create,update  |
    Then the reported status is "403"
    And user "participant1-v2" fetches all Contexts
    Then they will find Contexts "" and no other

  @api2 @contexts
  Scenario: Attempt to create a context containing an inaccessible view
    Given table "Table 1 via api v2" with emoji "👋" exists for user "participant1-v2" as "t1" via v2
    And table "Table 2 via api v2" with emoji "👋" exists for user "participant2-v2" as "t2" via v2
    And user "participant2-v2" create view "v2" with emoji "⚡️" for "t2" as "v2"
    When user "participant1-v2" attempts to create the Context "c1" with name "Enchanting Guitar" with icon "tennis" and description "Lorem ipsum dolor etc pp" and nodes:
      | alias | type  | permissions         |
      | v2    | view | read,create,update  |
    Then the reported status is "403"
    And user "participant1-v2" fetches all Contexts
    Then they will find Contexts "" and no other

  @api2 @contexts
  Scenario: Fetch the overview over existing Contexts as owner
    Given table "Table 1 via api v2" with emoji "👋" exists for user "participant1-v2" as "t1" via v2
    And table "Table 2 via api v2" with emoji "📸" exists for user "participant1-v2" as "t2" via v2
    And table "Table X via api v2" with emoji "📋" exists for user "participant2-v2" as "t3" via v2
    And user "participant1-v2" create view "v1" with emoji "⚡️" for "t1" as "v1"
    And user "participant1-v2" create view "v2" with emoji "🦉" for "t2" as "v2"
    And user "participant1-v2" creates the Context "c1" with name "Enchanting Guitar" with icon "tennis" and description "Lorem ipsum dolor etc pp" and nodes:
      | alias | type  | permissions         |
      | t1    | table | read,created,update |
      | v1    | view  | read                |
    And user "participant1-v2" creates the Context "c2" with name "Placid Ring" with icon "headphones" and description "Lacus suspendisse faucibus etc pp" and nodes:
      | alias | type  | permissions         |
      | t2    | table | read,create,update  |
      | v2    | view  | read                |
    And user "participant2-v2" creates the Context "c3" with name "Chilly Lumber" with icon "monitor" and description "Sed blandit libero etc pp" and nodes:
      | alias | type  | permissions         |
      | t3    | table | all                 |
    When user "participant1-v2" fetches all Contexts
    Then they will find Contexts "c1, c2" and no other

  @api2 @contexts
  Scenario: Fetch the a specific Context as owner
    Given table "Table 1 via api v2" with emoji "👋" exists for user "participant1-v2" as "t1" via v2
    And table "Table 2 via api v2" with emoji "📸" exists for user "participant1-v2" as "t2" via v2
    And table "Table X via api v2" with emoji "📋" exists for user "participant2-v2" as "t3" via v2
    And user "participant1-v2" create view "v1" with emoji "⚡️" for "t1" as "v1"
    And user "participant1-v2" create view "v2" with emoji "🦉" for "t2" as "v2"
    And user "participant1-v2" creates the Context "c1" with name "Enchanting Guitar" with icon "tennis" and description "Lorem ipsum dolor etc pp" and nodes:
      | alias | type  | permissions         |
      | t1    | table | read,created,update |
      | v1    | view  | read                |
    And user "participant1-v2" creates the Context "c2" with name "Placid Ring" with icon "headphones" and description "Lacus suspendisse faucibus etc pp" and nodes:
      | alias | type  | permissions         |
      | t2    | table | read,create,update  |
      | v2    | view  | read                |
    And user "participant2-v2" creates the Context "c3" with name "Chilly Lumber" with icon "monitor" and description "Sed blandit libero etc pp" and nodes:
      | alias | type  | permissions         |
      | t3    | table | all                 |
    When user "participant1-v2" fetches Context "c2"
    Then known Context "c2" has "name" set to "Placid Ring"
    And known Context "c2" has "icon" set to "headphones"
    And known Context "c2" has "description" set to "Lacus suspendisse faucibus etc pp"
    And known Context "c2" contains "table" "t2" with permissions "read,create,update"
    And known Context "c2" contains "view" "v2" with permissions "read"

  @api2 @contexts
  Scenario: Fetch a specific, but inaccessible Context
    Given table "Table 1 via api v2" with emoji "👋" exists for user "participant1-v2" as "t1" via v2
    And table "Table 2 via api v2" with emoji "📸" exists for user "participant1-v2" as "t2" via v2
    And user "participant1-v2" creates the Context "c1" with name "Enchanting Guitar" with icon "tennis" and description "Lorem ipsum dolor etc pp" and nodes:
      | alias | type  | permissions         |
      | t1    | table | read,created,update |
    When user "participant2-v2" attempts to fetch Context "c1"
    Then the reported status is "404"

  @api2 @contexts
  Scenario: Fetch a specific, but non-existing Context
    Given table "Table 1 via api v2" with emoji "👋" exists for user "participant1-v2" as "t1" via v2
    And table "Table 2 via api v2" with emoji "📸" exists for user "participant1-v2" as "t2" via v2
    And user "participant1-v2" creates the Context "c1" with name "Enchanting Guitar" with icon "tennis" and description "Lorem ipsum dolor etc pp" and nodes:
      | alias | type  | permissions         |
      | t1    | table | read,created,update |
    When user "participant1-v2" attempts to fetch Context "NON-EXISTENT"
    Then the reported status is "404"

  @api2 @contexts
  Scenario: Delete an owned context
    Given table "Table 1 via api v2" with emoji "👋" exists for user "participant1-v2" as "t1" via v2
    And table "Table 2 via api v2" with emoji "📸" exists for user "participant1-v2" as "t2" via v2
    And user "participant1-v2" creates the Context "c1" with name "Enchanting Guitar" with icon "tennis" and description "Lorem ipsum dolor etc pp" and nodes:
      | alias | type  | permissions         |
      | t1    | table | read,created,update |
    When user "participant1-v2" deletes Context "c1"
    Then the reported status is "200"
    When user "participant1-v2" attempts to fetch Context "c1"
    Then the reported status is "404"

  @api2 @contexts
  Scenario: Delete a shared context
    Given table "Table 1 via api v2" with emoji "👋" exists for user "participant1-v2" as "t1" via v2
    And table "Table 2 via api v2" with emoji "📸" exists for user "participant1-v2" as "t2" via v2
    And user "participant1-v2" creates the Context "c1" with name "Enchanting Guitar" with icon "tennis" and description "Lorem ipsum dolor etc pp" and nodes:
      | alias | type  | permissions         |
      | t1    | table | read,created,update |
    And user "participant1-v2" shares the Context "c1" to "user" "participant2-v2"
    When user "participant2-v2" attempts to delete Context "c1"
    Then the reported status is "403"
    When user "participant1-v2" fetches Context "c1"
    Then the reported status is "200"

  @api2 @contexts
  Scenario: Delete an inaccessible context
    Given table "Table 1 via api v2" with emoji "👋" exists for user "participant1-v2" as "t1" via v2
    And table "Table 2 via api v2" with emoji "📸" exists for user "participant1-v2" as "t2" via v2
    And user "participant1-v2" creates the Context "c1" with name "Enchanting Guitar" with icon "tennis" and description "Lorem ipsum dolor etc pp" and nodes:
      | alias | type  | permissions         |
      | t1    | table | read,created,update |
    When user "participant2-v2" attempts to delete Context "c1"
    Then the reported status is "404"
    When user "participant2-v2" attempts to fetch Context "c1"
    Then the reported status is "404"
    When user "participant1-v2" fetches Context "c1"
    Then the reported status is "200"

  @api2 @contexts
  Scenario: Delete an non-existing context
    Given table "Table 1 via api v2" with emoji "👋" exists for user "participant1-v2" as "t1" via v2
    And table "Table 2 via api v2" with emoji "📸" exists for user "participant1-v2" as "t2" via v2
    And user "participant1-v2" creates the Context "c1" with name "Enchanting Guitar" with icon "tennis" and description "Lorem ipsum dolor etc pp" and nodes:
      | alias | type  | permissions         |
      | t1    | table | read,created,update |
    When user "participant1-v2" attempts to delete Context "NON-EXISTENT"
    Then the reported status is "404"
    When user "participant1-v2" attempts to fetch Context "NON-EXISTENT"
    Then the reported status is "404"

  @api2 @contexts @contexts-update
  Scenario: Update an owned context
    Given table "Table 1 via api v2" with emoji "👋" exists for user "participant1-v2" as "t1" via v2
    And table "Table 2 via api v2" with emoji "📸" exists for user "participant1-v2" as "t2" via v2
    And user "participant1-v2" creates the Context "c1" with name "Enchanting Guitar" with icon "tennis" and description "Lorem ipsum dolor etc pp" and nodes:
      | alias | type  | permissions         |
      | t1    | table | read,created,update |
    When user "participant1-v2" updates Context "c1" by setting
      | property    | value                 |
      | name        | Psychedelic Drawer    |
      | iconName    | thermostat            |
      | description | Roll With the Punches |
    Then the reported status is "200"
    When user "participant1-v2" fetches Context "c1"
    Then known Context "c1" has "name" set to "Psychedelic Drawer"
    And known Context "c1" has "icon" set to "thermostat"
    And known Context "c1" has "description" set to "Roll With the Punches"

  @api2 @contexts @contexts-update
  Scenario: Update a shared context
    Given table "Table 1 via api v2" with emoji "👋" exists for user "participant1-v2" as "t1" via v2
    And table "Table 2 via api v2" with emoji "📸" exists for user "participant1-v2" as "t2" via v2
    And user "participant1-v2" creates the Context "c1" with name "Enchanting Guitar" with icon "tennis" and description "Lorem ipsum dolor etc pp" and nodes:
      | alias | type  | permissions         |
      | t1    | table | read,created,update |
    And user "participant1-v2" shares the Context "c1" to "user" "participant2-v2"
    When user "participant2-v2" updates Context "c1" by setting
      | property    | value                 |
      | name        | Psychedelic Drawer    |
      | iconName    | thermostat            |
      | description | Roll With the Punches |
    Then the reported status is "403"
    When user "participant1-v2" fetches Context "c1"
    Then known Context "c1" has "name" set to "Enchanting Guitar"
    And known Context "c1" has "icon" set to "tennis"
    And known Context "c1" has "description" set to "Lorem ipsum dolor etc pp"

  @api2 @contexts @contexts-update
  Scenario: Update an inaccessible context
    Given table "Table 1 via api v2" with emoji "👋" exists for user "participant1-v2" as "t1" via v2
    And table "Table 2 via api v2" with emoji "📸" exists for user "participant1-v2" as "t2" via v2
    And user "participant1-v2" creates the Context "c1" with name "Enchanting Guitar" with icon "tennis" and description "Lorem ipsum dolor etc pp" and nodes:
      | alias | type  | permissions         |
      | t1    | table | read,created,update |
    When user "participant2-v2" updates Context "c1" by setting
      | property    | value                 |
      | name        | Psychedelic Drawer    |
      | iconName    | thermostat            |
      | description | Roll With the Punches |
    Then the reported status is "404"
    When user "participant1-v2" fetches Context "c1"
    Then known Context "c1" has "name" set to "Enchanting Guitar"
    And known Context "c1" has "icon" set to "tennis"
    And known Context "c1" has "description" set to "Lorem ipsum dolor etc pp"

  @api2 @contexts @contexts-update
  Scenario: Add a table to an owned context
    Given table "Table 1 via api v2" with emoji "👋" exists for user "participant1-v2" as "t1" via v2
    And table "Table 2 via api v2" with emoji "📸" exists for user "participant1-v2" as "t2" via v2
    And user "participant1-v2" creates the Context "c1" with name "Enchanting Guitar" with icon "tennis" and description "Lorem ipsum dolor etc pp" and nodes:
      | alias | type  | permissions         |
      | t1    | table | read,create,update  |
    When user "participant1-v2" updates the nodes of the Context "c1" to
      | alias | type  | permissions         |
      | t1    | table | read,create,update  |
      | t2    | table | read                |
    Then the reported status is "200"
    When user "participant1-v2" fetches Context "c1"
    Then the fetched Context "c1" has following data:
      | field | value                        |
      | node  | table:t1:read,create,update  |
      | node  | table:t2:read                |

  @api2 @contexts @contexts-update
  Scenario: Add a table to a shared context
    Given table "Table 1 via api v2" with emoji "👋" exists for user "participant1-v2" as "t1" via v2
    And table "Table 2 via api v2" with emoji "📸" exists for user "participant2-v2" as "t2" via v2
    And user "participant1-v2" creates the Context "c1" with name "Enchanting Guitar" with icon "tennis" and description "Lorem ipsum dolor etc pp" and nodes:
      | alias | type  | permissions         |
      | t1    | table | read,create,update  |
    And user "participant1-v2" shares the Context "c1" to "user" "participant2-v2"
    When user "participant2-v2" updates the nodes of the Context "c1" to
      | alias | type  | permissions         |
      | t1    | table | read,create         |
      | t2    | table | read                |
    Then the reported status is "403"
    When user "participant1-v2" fetches Context "c1"
    Then the fetched Context "c1" has following data:
      | field | value                        |
      | node  | table:t1:read,create,update  |

  @api2 @contexts @contexts-update
  Scenario: Add a table to an inaccessible context
    Given table "Table 1 via api v2" with emoji "👋" exists for user "participant1-v2" as "t1" via v2
    And table "Table 2 via api v2" with emoji "📸" exists for user "participant2-v2" as "t2" via v2
    And user "participant1-v2" creates the Context "c1" with name "Enchanting Guitar" with icon "tennis" and description "Lorem ipsum dolor etc pp" and nodes:
      | alias | type  | permissions         |
      | t1    | table | read,create,update  |
    When user "participant2-v2" updates the nodes of the Context "c1" to
      | alias | type  | permissions         |
      | t1    | table | read,create         |
      | t2    | table | read                |
    Then the reported status is "404"
    When user "participant1-v2" fetches Context "c1"
    Then the fetched Context "c1" has following data:
      | field | value                        |
      | node  | table:t1:read,create,update  |

  @api2 @contexts @contexts-update
  Scenario: Add an inaccessible table to an owned context
    Given table "Table 1 via api v2" with emoji "👋" exists for user "participant1-v2" as "t1" via v2
    And table "Table 2 via api v2" with emoji "📸" exists for user "participant2-v2" as "t2" via v2
    And user "participant1-v2" creates the Context "c1" with name "Enchanting Guitar" with icon "tennis" and description "Lorem ipsum dolor etc pp" and nodes:
      | alias | type  | permissions         |
      | t1    | table | read,created,update |
    When user "participant1-v2" updates the nodes of the Context "c1" to
      | alias | type  | permissions         |
      | t1    | table | read,create,update  |
      | t2    | table | read                |
    Then the reported status is "403"
    When user "participant1-v2" fetches Context "c1"
    Then the fetched Context "c1" has following data:
      | field | value                        |
      | node  | table:t1:read,create,update  |
    And the fetched Context "c1" does not contain following data:
      | field | value                        |
      | node  | table:t2:read                |

  @api2 @contexts @contexts-update
  Scenario: Add an inaccessible table to an inaccessible context
    Given table "Table 1 via api v2" with emoji "👋" exists for user "participant1-v2" as "t1" via v2
    And table "Table 2 via api v2" with emoji "📸" exists for user "participant2-v2" as "t2" via v2
    And user "participant1-v2" creates the Context "c1" with name "Enchanting Guitar" with icon "tennis" and description "Lorem ipsum dolor etc pp" and nodes:
      | alias | type  | permissions         |
      | t1    | table | read,created,update |
    When user "participant2-v2" updates the nodes of the Context "c1" to
      | alias | type  | permissions         |
      | t1    | table | read,create,update  |
      | t2    | table | read                |
    Then the reported status is "404"
    When user "participant1-v2" fetches Context "c1"
    Then the fetched Context "c1" has following data:
      | field | value                        |
      | node  | table:t1:read,create,update  |
    And the fetched Context "c1" does not contain following data:
      | field | value                        |
      | node  | table:t2:read                |

  @api2 @contexts @contexts-update
  Scenario: Remove a table from an owned context
    Given table "Table 1 via api v2" with emoji "👋" exists for user "participant1-v2" as "t1" via v2
    And table "Table 2 via api v2" with emoji "📸" exists for user "participant1-v2" as "t2" via v2
    And user "participant1-v2" creates the Context "c1" with name "Enchanting Guitar" with icon "tennis" and description "Lorem ipsum dolor etc pp" and nodes:
      | alias | type  | permissions         |
      | t1    | table | read,create,update  |
      | t2    | table | read                |
    When user "participant1-v2" updates the nodes of the Context "c1" to
      | alias | type  | permissions         |
      | t1    | table | read,create,update  |
    Then the reported status is "200"
    When user "participant1-v2" fetches Context "c1"
    Then the fetched Context "c1" has following data:
      | field | value                        |
      | node  | table:t1:read,create,update  |
    And the fetched Context "c1" does not contain following data:
      | field | value                        |
      | node  | table:t2:read                |

  @api2 @contexts @contexts-update
  Scenario: Remove a table from a shared context
    Given table "Table 1 via api v2" with emoji "👋" exists for user "participant1-v2" as "t1" via v2
    And table "Table 2 via api v2" with emoji "📸" exists for user "participant1-v2" as "t2" via v2
    And user "participant1-v2" creates the Context "c1" with name "Enchanting Guitar" with icon "tennis" and description "Lorem ipsum dolor etc pp" and nodes:
      | alias | type  | permissions         |
      | t1    | table | read,create,update  |
      | t2    | table | read                |
    And user "participant1-v2" shares the Context "c1" to "user" "participant2-v2"
    When user "participant2-v2" updates the nodes of the Context "c1" to
      | alias | type  | permissions         |
      | t1    | table | read,create,update  |
    Then the reported status is "403"
    When user "participant1-v2" fetches Context "c1"
    Then the fetched Context "c1" has following data:
      | field | value                        |
      | node  | table:t1:read,create,update  |
      | node  | table:t2:read                |

  @api2 @contexts @contexts-update
  Scenario: Remove a non-existing table from an inaccessible context
    Given table "Table 1 via api v2" with emoji "👋" exists for user "participant1-v2" as "t1" via v2
    And table "Table 2 via api v2" with emoji "📸" exists for user "participant1-v2" as "t2" via v2
    And user "participant1-v2" creates the Context "c1" with name "Enchanting Guitar" with icon "tennis" and description "Lorem ipsum dolor etc pp" and nodes:
      | alias | type  | permissions         |
      | t1    | table | read,create,update  |
      | t2    | table | read                |
    When user "participant2-v2" updates the nodes of the Context "c1" to
      | alias | type  | permissions         |
      | t1    | table | read,create,update  |
    Then the reported status is "404"
    When user "participant1-v2" fetches Context "c1"
    Then the fetched Context "c1" has following data:
      | field | value                        |
      | node  | table:t1:read,create,update  |
      | node  | table:t2:read                |

  @api2 @contexts @contexts-ownership
  Scenario: Transfer an owned context
    Given table "Table 1 via api v2" with emoji "👋" exists for user "participant1-v2" as "t1" via v2
    And table "Table 2 via api v2" with emoji "📸" exists for user "participant1-v2" as "t2" via v2
    And user "participant1-v2" creates the Context "c1" with name "Enchanting Guitar" with icon "tennis" and description "Lorem ipsum dolor etc pp" and nodes:
      | alias | type  | permissions         |
      | t1    | table | read,create,update  |
      | t2    | table | read                |
    When user "participant1-v2" transfers the Context "c1" to "participant2-v2"
    Then the reported status is "200"
    When user "participant1-v2" attempts to fetch Context "c1"
    Then the reported status is "404"
    When user "participant2-v2" fetches Context "c1"
    Then the fetched Context "c1" has following data:
      | field | value                        |
      | node  | table:t1:read,create,update  |
      | node  | table:t2:read                |

  @api2 @contexts @contexts-ownership
  Scenario: Transfer a shared context
    Given table "Table 1 via api v2" with emoji "👋" exists for user "participant1-v2" as "t1" via v2
    And table "Table 2 via api v2" with emoji "📸" exists for user "participant1-v2" as "t2" via v2
    And user "participant1-v2" creates the Context "c1" with name "Enchanting Guitar" with icon "tennis" and description "Lorem ipsum dolor etc pp" and nodes:
      | alias | type  | permissions         |
      | t1    | table | read,create,update  |
      | t2    | table | read                |
    And user "participant1-v2" shares the Context "c1" to "user" "participant2-v2"
    When user "participant2-v2" transfers the Context "c1" to "participant3-v2"
    Then the reported status is "403"
    When user "participant1-v2" fetches Context "c1"
    Then the fetched Context "c1" has following data:
      | field | value                        |
      | node  | table:t1:read,create,update  |
      | node  | table:t2:read                |
    When user "participant2-v2" fetches Context "c1"
    Then the reported status is "200"
    When user "participant3-v2" attempts to fetch Context "c1"
    Then the reported status is "404"

  @api2 @contexts @contexts-ownership
  Scenario: Transfer an inaccessible context
    Given table "Table 1 via api v2" with emoji "👋" exists for user "participant1-v2" as "t1" via v2
    And table "Table 2 via api v2" with emoji "📸" exists for user "participant1-v2" as "t2" via v2
    And user "participant1-v2" creates the Context "c1" with name "Enchanting Guitar" with icon "tennis" and description "Lorem ipsum dolor etc pp" and nodes:
      | alias | type  | permissions         |
      | t1    | table | read,create,update  |
      | t2    | table | read                |
    When user "participant2-v2" transfers the Context "c1" to "participant3-v2"
    Then the reported status is "404"
    When user "participant1-v2" fetches Context "c1"
    Then the fetched Context "c1" has following data:
      | field | value                        |
      | node  | table:t1:read,create,update  |
      | node  | table:t2:read                |
    When user "participant2-v2" attempts to fetch Context "c1"
    Then the reported status is "404"
    When user "participant3-v2" attempts to fetch Context "c1"
    Then the reported status is "404"

  @api2 @rows
  Scenario: Create rows via v2 and check them
    Given table "Rows check" with emoji "👨🏻‍💻" exists for user "participant1-v2" as "base1" via v2
    And column "one" exists with following properties
      | type          | text                    |
      | subtype       | line                    |
      | mandatory     | 0                       |
      | description   | This is a description!  |
    And column "two" exists with following properties
      | type          | number                  |
      | mandatory     | 1                       |
      | numberDefault | 10                      |
      | description   | This is a description!  |
    And column "three" exists with following properties
      | type          | selection               |
      | subtype       | check                   |
      | mandatory     | 1                       |
      | description   | This is a description!  |
    And column "four" exists with following properties
      | type          | datetime                |
      | subtype       | date                    |
      | mandatory     | 0                       |
      | description   | This is a description!  |
    When row exists using v2 with following values
      | one           | AHA                     |
      | two           | 161                     |
      | three         | true                    |
      | four          | 2023-12-24              |
    Then the reported status is 200

  @api2 @rows
  Scenario: Try to create rows via v2 with permissions
    Given table "Rows check" with emoji "👨🏻‍💻" exists for user "participant1-v2" as "base1" via v2
    And column "one" exists with following properties
      | type          | text                    |
      | subtype       | line                    |
      | mandatory     | 0                       |
      | description   | This is a description!  |
    And column "two" exists with following properties
      | type          | number                  |
      | mandatory     | 1                       |
      | numberDefault | 10                      |
      | description   | This is a description!  |
    And column "three" exists with following properties
      | type          | selection               |
      | subtype       | check                   |
      | mandatory     | 1                       |
      | description   | This is a description!  |
    And column "four" exists with following properties
      | type          | datetime                |
      | subtype       | date                    |
      | mandatory     | 0                       |
      | description   | This is a description!  |
    And user "participant1-v2" shares table with user "participant2-v2"
    And user "participant2-v2" has the following permissions
      | read    | 1 |
      | create  | 1 |
      | update  | 1 |
      | delete  | 0 |
      | manage  | 0 |
    When user "participant2-v2" tries to create a row using v2 with following values
      | one           | AHA                     |
      | two           | 161                     |
      | three         | true                    |
      | four          | 2023-12-24              |
    Then the reported status is 200

  @api2 @rows
  Scenario: Try to create rows via v2 without permissions
    Given table "Rows check" with emoji "👨🏻‍💻" exists for user "participant1-v2" as "base1" via v2
    And column "one" exists with following properties
      | type          | text                    |
      | subtype       | line                    |
      | mandatory     | 0                       |
      | description   | This is a description!  |
    And column "two" exists with following properties
      | type          | number                  |
      | mandatory     | 1                       |
      | numberDefault | 10                      |
      | description   | This is a description!  |
    And column "three" exists with following properties
      | type          | selection               |
      | subtype       | check                   |
      | mandatory     | 1                       |
      | description   | This is a description!  |
    And column "four" exists with following properties
      | type          | datetime                |
      | subtype       | date                    |
      | mandatory     | 0                       |
      | description   | This is a description!  |
    And user "participant1-v2" shares table with user "participant2-v2"
    And user "participant1-v2" sets permission "create" to 0
    And user "participant2-v2" has the following permissions
      | read    | 1 |
      | create  | 0 |
      | update  | 1 |
      | delete  | 0 |
      | manage  | 0 |
    When user "participant2-v2" tries to create a row using v2 with following values
      | one           | AHA                     |
      | two           | 161                     |
      | three         | true                    |
      | four          | 2023-12-24              |
    Then the reported status is 403

  @api2 @rows
  Scenario: Try to create rows via v2 without access
    Given table "Rows check" with emoji "👨🏻‍💻" exists for user "participant1-v2" as "base3" via v2
    And column "one" exists with following properties
      | type          | text                    |
      | subtype       | line                    |
      | mandatory     | 0                       |
      | description   | This is a description!  |
    And column "two" exists with following properties
      | type          | number                  |
      | mandatory     | 1                       |
      | numberDefault | 10                      |
      | description   | This is a description!  |
    And column "three" exists with following properties
      | type          | selection               |
      | subtype       | check                   |
      | mandatory     | 1                       |
      | description   | This is a description!  |
    And column "four" exists with following properties
      | type          | datetime                |
      | subtype       | date                    |
      | mandatory     | 0                       |
      | description   | This is a description!  |
    When user "participant2-v2" tries to create a row using v2 with following values
      | one           | AHA                     |
      | two           | 161                     |
      | three         | true                    |
      | four          | 2023-12-24              |
    Then the reported status is 404

  @api2 @rows @views
  Scenario: Create rows on a view via v2
    Given table "Table 1 via api v2" with emoji "👋" exists for user "participant1-v2" as "t1" via v2
    And column "one" exists with following properties
      | type          | text                    |
      | subtype       | line                    |
      | mandatory     | 0                       |
      | description   | This is a description!  |
    And column "two" exists with following properties
      | type          | number                  |
      | mandatory     | 1                       |
      | numberDefault | 10                      |
      | description   | This is a description!  |
    And column "three" exists with following properties
      | type          | selection               |
      | subtype       | check                   |
      | mandatory     | 1                       |
      | description   | This is a description!  |
    And column "four" exists with following properties
      | type          | datetime                |
      | subtype       | date                    |
      | mandatory     | 0                       |
      | description   | This is a description!  |
    And user "participant1-v2" create view "v1" with emoji "⚡️" for "t1" as "v1"
    When user "participant1-v2" tries to create a row using v2 on "view" "v1" with following values
      | one           | AHA                     |
      | two           | 161                     |
      | three         | true                    |
      | four          | 2023-12-24              |
    Then the reported status is 200

  @api2 @rows @views
  Scenario: Create rows on a view via v2 with permissions
    Given table "Table 1 via api v2" with emoji "👋" exists for user "participant1-v2" as "t1" via v2
    And column "one" exists with following properties
      | type          | text                    |
      | subtype       | line                    |
      | mandatory     | 0                       |
      | description   | This is a description!  |
    And column "two" exists with following properties
      | type          | number                  |
      | mandatory     | 1                       |
      | numberDefault | 10                      |
      | description   | This is a description!  |
    And column "three" exists with following properties
      | type          | selection               |
      | subtype       | check                   |
      | mandatory     | 1                       |
      | description   | This is a description!  |
    And column "four" exists with following properties
      | type          | datetime                |
      | subtype       | date                    |
      | mandatory     | 0                       |
      | description   | This is a description!  |
    And user "participant1-v2" create view "v1" with emoji "⚡️" for "t1" as "v1"
    And user "participant1-v2" shares view "v1" with "participant2-v2"
    When user "participant2-v2" tries to create a row using v2 on "view" "v1" with following values
      | one           | AHA                     |
      | two           | 161                     |
      | three         | true                    |
      | four          | 2023-12-24              |
    Then the reported status is 200

  @api2 @rows @views
  Scenario: Create rows on a view via v2 without permissions
    Given table "Table 1 via api v2" with emoji "👋" exists for user "participant1-v2" as "t1" via v2
    And column "one" exists with following properties
      | type          | text                    |
      | subtype       | line                    |
      | mandatory     | 0                       |
      | description   | This is a description!  |
    And column "two" exists with following properties
      | type          | number                  |
      | mandatory     | 1                       |
      | numberDefault | 10                      |
      | description   | This is a description!  |
    And column "three" exists with following properties
      | type          | selection               |
      | subtype       | check                   |
      | mandatory     | 1                       |
      | description   | This is a description!  |
    And column "four" exists with following properties
      | type          | datetime                |
      | subtype       | date                    |
      | mandatory     | 0                       |
      | description   | This is a description!  |
    And user "participant1-v2" create view "v1" with emoji "⚡️" for "t1" as "v1"
    And user "participant1-v2" shares view "v1" with "participant2-v2"
    And user "participant1-v2" sets permission "create" to 0
    When user "participant2-v2" tries to create a row using v2 on "view" "v1" with following values
      | one           | AHA                     |
      | two           | 161                     |
      | three         | true                    |
      | four          | 2023-12-24              |
    Then the reported status is 403

  @api2 @rows @views
  Scenario: Create rows on a view via v2 without access
    Given table "Table 1 via api v2" with emoji "👋" exists for user "participant1-v2" as "t1" via v2
    And column "one" exists with following properties
      | type          | text                    |
      | subtype       | line                    |
      | mandatory     | 0                       |
      | description   | This is a description!  |
    And column "two" exists with following properties
      | type          | number                  |
      | mandatory     | 1                       |
      | numberDefault | 10                      |
      | description   | This is a description!  |
    And column "three" exists with following properties
      | type          | selection               |
      | subtype       | check                   |
      | mandatory     | 1                       |
      | description   | This is a description!  |
    And column "four" exists with following properties
      | type          | datetime                |
      | subtype       | date                    |
      | mandatory     | 0                       |
      | description   | This is a description!  |
    And user "participant1-v2" create view "v1" with emoji "⚡️" for "t1" as "v1"
    When user "participant2-v2" tries to create a row using v2 on "view" "v1" with following values
      | one           | AHA                     |
      | two           | 161                     |
      | three         | true                    |
      | four          | 2023-12-24              |
    Then the reported status is 404
