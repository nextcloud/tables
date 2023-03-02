Feature: api/tablesapi
  Background:
    Given user "participant1" exists
    And user "participant2" exists
    And user "participant3" exists
    And user "admin" exists
    And group "attendees1" exists
    And user "participant2" is member of group "attendees1"

  Scenario: User has no tables
    Then user "participant1" has the following tables

  Scenario: User has tables
    Given as user "admin"
    Given user "admin" logs in
    Then user "admin" has the following tables
    | Staffing |
