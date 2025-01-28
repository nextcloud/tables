# SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
Feature: RowOCS
  Background:
    Given user "participant1-v2" exists
    Given user "participant2-v2" exists
    Given user "participant3-v2" exists
    Given table "Table 1 via api v2" with emoji "👋" exists for user "participant1-v2" as "t1" via v2
    And column "one" exists with following properties
      | type          | text                    |
      | subtype       | line                    |
      | mandatory     | 0                       |
    And column "two" exists with following properties
      | type          | number                  |
      | mandatory     | 1                       |
      | numberDefault | 10                      |
    And column "three" exists with following properties
      | type          | selection               |
      | subtype       | check                   |
      | mandatory     | 1                       |
    And column "four" exists with following properties
      | type          | datetime                |
      | subtype       | date                    |
      | mandatory     | 0                       |
    And column "five" exists with following properties
      | type          | usergroup               |
      | mandatory     | 1                       |
      | usergroupMultipleItems  | true          |
      | usergroupSelectUsers    | true          |
      | usergroupSelectGroups   | false         |
      | usergroupSelectTeams    | false         |
    And using "table" "t1"
    And user "participant1-v2" creates row "r1" with following values:
      | one           | Row one                 |
      | two           | 1600                    |
      | three         | true                    |
      | four          | 2025-01-01              |
      | five          | [{"id": "alice", "type": 0}] |
    And user "participant1-v2" creates row "r2" with following values:
      | one           | Row two                 |
      | two           | 1604                    |
      | three         | false                   |
      | four          | 2025-01-12              |
      | five          | [{"id": "bob", "type": 0},{"id": "clarence", "type": 0}] |
    And user "participant1-v2" creates row "r3" with following values:
      | one           | Row three               |
      | two           | 1628                    |
      | three         | true                    |
      | four          | 2025-01-23              |
      | five          | [{"id": "dany", "type": 0}] |
    And user "participant1-v2" creates row "r4" with following values:
      | one           | Row four                |
      | two           | 1669                    |
      | three         | false                   |
      | four          | 2025-02-03              |
      | five          | [{"id": "elias", "type": 0},{"id": "fran", "type": 0}] |
    And user "participant1-v2" creates row "r5" with following values:
      | one           | Row five                |
      | two           | 1711                    |
      | three         | true                    |
      | four          | 2025-02-14              |
      | five          | [{"id": "george", "type": 0},{"id": "hannah", "type": 0},{"id": "ines", "type": 0}] |
    And user "participant1-v2" creates row "r6" with following values:
      | one           | Row six                 |
      | two           | 1729                    |
      | three         | true                    |
      | four          | 2025-02-25              |
      | five          | [{"id": "jamie", "type": 0}] |
    And user "participant1-v2" creates row "r7" with following values:
      | one           | Row seven               |
      | two           | 1794                    |
      | three         | false                   |
      | four          | 2025-03-08              |
      | five          | [{"id": "kate", "type": 0},{"id": "lena", "type": 0}] |
    And user "participant1-v2" creates row "r8" with following values:
      | one           | Row eight               |
      | two           | 1827                    |
      | three         | false                   |
      | four          | 2025-03-19              |
      | five          | [{"id": "moe", "type": 0}] |
    And user "participant1-v2" creates row "r9" with following values:
      | one           | Row nine                |
      | two           | 1924                    |
      | three         | true                    |
      | four          | 2025-03-30              |
      | five          | [{"id": "nora", "type": 0}] |
    And user "participant1-v2" creates row "r10" with following values:
      | one           | Row ten                 |
      | two           | 1994                    |
      | three         | true                    |
      | four          | 2025-04-10              |
      | five          | [{"id": "otto", "type": 0},{"id": "pierre", "type": 0}] |
    And user "participant1-v2" creates row "r11" with following values:
      | one           | Row eleven              |
      | two           | 2006                    |
      | three         | true                    |
      | four          | 2025-04-21              |
      | five          | [{"id": "quinn", "type": 0},{"id": "roberta", "type": 0}] |
    And user "participant1-v2" creates row "r12" with following values:
      | one           | Row twelve              |
      | two           | 2023                    |
      | three         | false                   |
      | four          | 2025-05-05              |
      | five          | [{"id": "samir", "type": 0},{"id": "teresa", "type": 0}] |
    And user "participant1-v2" creates row "r13" with following values:
      | one           | Row thirteen            |
      | two           | 2061                    |
      | three         | false                   |
      | four          | 2025-05-16              |
      | five          | [{"id": "udai", "type": 0},{"id": "vera", "type": 0},{"id": "xuan", "type": 0}] |
    And user "participant1-v2" creates row "r14" with following values:
      | one           | Row fourteen            |
      | two           | 2083                    |
      | three         | false                   |
      | four          | 2025-05-27              |
      | five          | [{"id": "yvonne", "type": 0},{"id": "zara", "type": 0},{"id": "ahmad", "type": 0}] |
    And user "participant1-v2" creates row "r15" with following values:
      | one           | Row fifteen             |
      | two           | 2137                    |
      | three         | true                   |
      | four          | 2025-06-07              |
      | five          | [{"id": "bertram", "type": 0}] |
    And user "participant1-v2" shares table with user "participant2-v2"
    And user "participant1-v2" create view "v1" with emoji "⚡️" for "t1" as "v1"
    And user "participant1-v2" shares view "v1" with "participant3-v2"

  @tables
  Scenario: Get all rows from a table
    Given as user "participant2-v2"
    When the current user fetches all rows from "table" "t1"
    Then the reported status is 200
    And 15 rows have been loaded

  @views
  Scenario: Get all rows from a view
    Given as user "participant3-v2"
    When the current user fetches all rows from "view" "v1"
    Then the reported status is 200
    And 15 rows have been loaded

  @tables @views
  Scenario Outline: Get rows from a table or view With Offset
    Given as user "<user>"
    When the current user fetches rows from "<type>" "<alias>" with those parameters
      | offset | <offset> |
    Then the reported status is <responseCode>
    And <rowsReturned> rows have been loaded

  Examples:
    | user            | type  | alias | offset | responseCode | rowsReturned  |
    | participant2-v2 | table |    t1 |     -1 |          400 |            0  |
    | participant3-v2 |  view |    v1 |     -1 |          400 |            0  |
    | participant2-v2 | table |    t1 |    200 |          200 |            0  |
    | participant3-v2 |  view |    v1 |    200 |          200 |            0  |

  @tables @views
  Scenario Outline: Get rows from a table or view With Offset
    Given as user "<user>"
    When the current user fetches rows from "<type>" "<alias>" with those parameters
      | offset | 5 |
    Then the reported status is 200
    And 10 rows have been loaded
    And rows "r1,r2,r3,r4,r5" are not included in the response

    Examples:
      | user            | type  | alias |
      | participant2-v2 | table |    t1 |
      | participant3-v2 |  view |    v1 |

  @tables @views
  Scenario Outline: Get rows from a table or view With Offset
    Given as user "<user>"
    When the current user fetches rows from "<type>" "<alias>" with those parameters
      | limit | <limit> |
    Then the reported status is <responseCode>
    And <rowsReturned> rows have been loaded

    Examples:
      | user            | type  | alias | limit | responseCode | rowsReturned  |
      | participant2-v2 | table |    t1 |    -1 |          400 |            0  |
      | participant3-v2 |  view |    v1 |    -1 |          400 |            0  |
      | participant2-v2 | table |    t1 |     0 |          400 |            0  |
      | participant3-v2 |  view |    v1 |     0 |          400 |            0  |
      | participant2-v2 | table |    t1 |   555 |          400 |            0  |
      | participant3-v2 |  view |    v1 |   555 |          400 |            0  |

  @tables @views
  Scenario Outline: Get rows from a table or view With Offset
    Given as user "<user>"
    When the current user fetches rows from "<type>" "<alias>" with those parameters
      | limit | 5 |
    Then the reported status is 200
    And 5 rows have been loaded
    And rows "r6,r7,r8,r9,r10,r11,r12,r13,r14,r15" are not included in the response

    Examples:
      | user            | type  | alias |
      | participant2-v2 | table |    t1 |
      | participant3-v2 |  view |    v1 |

  @tables @views @current
  Scenario Outline: Get rows from a table or view with a filter
    Given as user "<user>"
    When the current user fetches rows from "<type>" "<alias>" with those parameters
      | filter | one,contains,t |
    Then the reported status is 200
    And 8 rows have been loaded
    And rows "r6,r7,r8,r9,r10,r11,r12,r13,r14,r15" are not included in the response

    Examples:
      | user            | type  | alias |
      | participant2-v2 | table |    t1 |
      | participant3-v2 |  view |    v1 |
