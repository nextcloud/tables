Feature: APIv2
  Background:
    Given user "participant1-v2" exists

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
    Then user "participant1-v2" updates table "t1" set title "updated title" and emoji "⛵︎" via v2
    Then user "participant1-v2" has the following tables via v2
      | updated title |
    Then user "participant1-v2" deletes table "t1" via v2

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
