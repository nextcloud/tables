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
    Given table "my new awesome table" with emoji "🤓" exists for user "participant1"
    Then user "participant1" renames table with keyword "awesome" with title "renamed table" and emoji "🍓"
    Then user "participant1" deletes table with keyword "renamed"
    Then user "participant1" has the following tables
      | Tutorial |

  Scenario: Table sharing with a user
    Given table "Ready to share" with emoji "🥪" exists for user "participant1"
    Then user "participant1" shares table with keyword "Ready to share" with user "participant2"
    Then user "participant2" has the following tables
      | Tutorial | Ready to share |
    Then user "participant1" deletes table with keyword "Ready to share"
    Then user "participant1" has the following tables
      | Tutorial |
    Then user "participant2" has the following tables
      | Tutorial |

  Scenario: Table sharing with a group
    Given table "Ready to share" with emoji "🥪" exists for user "participant1"
    Then user "participant1" shares table with keyword "Ready to share" with group "phoenix"
    Then user "participant2" has the following tables
      | Tutorial | Ready to share |
    Then user "participant1" deletes table with keyword "Ready to share"
    Then user "participant1" has the following tables
      | Tutorial |
    Then user "participant2" has the following tables
      | Tutorial |


  Scenario: Cleanup

    Given user "participant1" is deleted
    Given user "participant2" is deleted
