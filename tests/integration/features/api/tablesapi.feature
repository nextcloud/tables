Feature: api/tablesapi
  Background:
    Given user "participant1" exists

  Scenario: User has initial table
    Then user "participant1" has the following tables
      | Tutorial |

  Scenario: User creates, rename and delete a table
    Then user "participant1" creates a table with title "my new awesome table" and optionally emoji "🤓"
    Then user "participant1" renames table with keyword "awesome" with title "renamed table" and emoji "🍓"
    Then user "participant1" deletes table with keyword "renamed"
    Then user "participant1" has the following tables
      | Tutorial |

  Scenario: Cleanup
      Given user "participant1" is deleted
