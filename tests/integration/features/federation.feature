# SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
Feature: Federation
  Share tables with users on other Nextcloud instances

  Background:
    Given acting on server "REMOTE"
    And user "federation-receiver" exists
    And acting on server "LOCAL"
    And user "federation-owner" exists
    And table "Federated table" with emoji "🌍" exists for user "federation-owner" as "t1" via v2
    And column "statement" exists with following properties
      | type      | text |
      | subtype   | line |
      | mandatory | 0    |
    And row exists using v2 with following values
      | statement | from owner |
    And user "federation-owner" shares "table" "t1" with "remote" "federation-receiver@http://localhost:8180"
    And acting on server "REMOTE"
    And user "federation-receiver" has federated table "Federated table" as "federated-t1"

  @federation
  Scenario: Receiver reads the rows of a federated table
    Then user "federation-receiver" sees the following rows in table "federated-t1"
      | from owner |

  @federation
  Scenario: Receiver creates a row after the owner grants the permission
    Given acting on server "LOCAL"
    And user "federation-owner" sets permission "create" to 1
    And acting on server "REMOTE"
    When user "federation-receiver" tries to create a row using v2 on "table" "federated-t1" with following values
      | statement | from receiver |
    Then the reported status is "200"
    And acting on server "LOCAL"
    And user "federation-owner" sees the following rows in table "t1"
      | from owner | from receiver |

  @federation
  Scenario: Deleting the table removes it for the receiver
    Given acting on server "LOCAL"
    When user "federation-owner" deletes table "t1" via v2
    And acting on server "REMOTE"
    Then user "federation-receiver" has no federated table "Federated table"

  @federation
  Scenario: Receiver reads the rows of a federated view
    Given acting on server "LOCAL"
    And user "federation-owner" create view "Federated view" with emoji "🌍" for "t1" as "v1"
    And user "federation-owner" sets columnSettings "statement" to view "v1"
    And user "federation-owner" shares "view" "v1" with "remote" "federation-receiver@http://localhost:8180"
    And acting on server "REMOTE"
    Then user "federation-receiver" has federated view "Federated view" as "federated-v1"
    And user "federation-receiver" sees the following rows in view "federated-v1"
      | from owner |

  @federation
  Scenario: Receiver creates a row in a federated view after the owner grants the permission
    Given acting on server "LOCAL"
    And user "federation-owner" create view "Federated view" with emoji "🌍" for "t1" as "v1"
    And user "federation-owner" sets columnSettings "statement" to view "v1"
    And user "federation-owner" shares "view" "v1" with "remote" "federation-receiver@http://localhost:8180"
    And user "federation-owner" sets permission "create" to 1
    And acting on server "REMOTE"
    And user "federation-receiver" has federated view "Federated view" as "federated-v1"
    When user "federation-receiver" tries to create a row using v2 on "view" "federated-v1" with following values
      | statement | from receiver |
    Then the reported status is "200"
    And acting on server "LOCAL"
    And user "federation-owner" sees the following rows in table "t1"
      | from owner | from receiver |

  @federation
  Scenario: Deleting the view removes it for the receiver
    Given acting on server "LOCAL"
    And user "federation-owner" create view "Federated view" with emoji "🌍" for "t1" as "v1"
    And user "federation-owner" shares "view" "v1" with "remote" "federation-receiver@http://localhost:8180"
    And acting on server "REMOTE"
    And user "federation-receiver" has federated view "Federated view" as "federated-v1"
    And acting on server "LOCAL"
    When user "federation-owner" deletes view "v1"
    And acting on server "REMOTE"
    Then user "federation-receiver" has no federated view "Federated view"
