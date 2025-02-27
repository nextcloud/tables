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
    And user "participant1-v2" tries to create a row using v2 on "table" "t1" with following values
      | one           | Row one                 |
      | two           | 1600                    |
      | three         | true                    |
      | four          | 2025-01-01              |
      | five          | [{"id": "alice", "type": 0}] |
    And user "participant1-v2" tries to create a row using v2 on "table" "t1" with following values
      | one           | Row two                 |
      | two           | 1604                    |
      | three         | false                   |
      | four          | 2025-01-12              |
      | five          | [{"id": "bob", "type": 0},{"id": "clarence", "type": 0}] |
    And user "participant1-v2" tries to create a row using v2 on "table" "t1" with following values
      | one           | Row three               |
      | two           | 1628                    |
      | three         | true                    |
      | four          | 2025-01-23              |
      | five          | [{"id": "dany", "type": 0}] |
    And user "participant1-v2" tries to create a row using v2 on "table" "t1" with following values
      | one           | Row four                |
      | two           | 1669                    |
      | three         | false                   |
      | four          | 2025-02-03              |
      | five          | [{"id": "elias", "type": 0},{"id": "fran", "type": 0}] |
    And user "participant1-v2" tries to create a row using v2 on "table" "t1" with following values
      | one           | Row five                |
      | two           | 1711                    |
      | three         | true                    |
      | four          | 2025-02-14              |
      | five          | [{"id": "george", "type": 0},{"id": "hannah", "type": 0},{"id": "ines", "type": 0}] |
    And user "participant1-v2" tries to create a row using v2 on "table" "t1" with following values
      | one           | Row six                 |
      | two           | 1729                    |
      | three         | true                    |
      | four          | 2025-02-25              |
      | five          | [{"id": "jamie", "type": 0}] |
    And user "participant1-v2" tries to create a row using v2 on "table" "t1" with following values
      | one           | Row seven               |
      | two           | 1794                    |
      | three         | false                   |
      | four          | 2025-03-08              |
      | five          | [{"id": "kate", "type": 0},{"id": "lena", "type": 0}] |
    And user "participant1-v2" tries to create a row using v2 on "table" "t1" with following values
      | one           | Row eight               |
      | two           | 1827                    |
      | three         | false                   |
      | four          | 2025-03-19              |
      | five          | [{"id": "moe", "type": 0}] |
    And user "participant1-v2" tries to create a row using v2 on "table" "t1" with following values
      | one           | Row nine                |
      | two           | 1924                    |
      | three         | true                    |
      | four          | 2025-03-30              |
      | five          | [{"id": "nora", "type": 0}] |
    And user "participant1-v2" tries to create a row using v2 on "table" "t1" with following values
      | one           | Row ten                 |
      | two           | 1994                    |
      | three         | true                    |
      | four          | 2025-04-10              |
      | five          | [{"id": "otto", "type": 0},{"id": "pierre", "type": 0}] |
    And user "participant1-v2" tries to create a row using v2 on "table" "t1" with following values
      | one           | Row eleven              |
      | two           | 2006                    |
      | three         | true                    |
      | four          | 2025-04-21              |
      | five          | [{"id": "quinn", "type": 0},{"id": "roberta", "type": 0}] |
    And user "participant1-v2" tries to create a row using v2 on "table" "t1" with following values
      | one           | Row twelve              |
      | two           | 2023                    |
      | three         | false                   |
      | four          | 2025-05-05              |
      | five          | [{"id": "samir", "type": 0},{"id": "teresa", "type": 0}] |
    And user "participant1-v2" tries to create a row using v2 on "table" "t1" with following values
      | one           | Row thirteen            |
      | two           | 2061                    |
      | three         | false                   |
      | four          | 2025-05-16              |
      | five          | [{"id": "udai", "type": 0},{"id": "vera", "type": 0},{"id": "xuan", "type": 0}] |
    And user "participant1-v2" tries to create a row using v2 on "table" "t1" with following values
      | one           | Row fourteen            |
      | two           | 2083                    |
      | three         | false                   |
      | four          | 2025-05-27              |
      | five          | [{"id": "yvonne", "type": 0},{"id": "zara", "type": 0},{"id": "ahmad", "type": 0}] |
    And user "participant1-v2" tries to create a row using v2 on "table" "t1" with following values
      | one           | Row fifteen             |
      | two           | 2137                    |
      | three         | true                   |
      | four          | 2025-06-07              |
      | five          | [{"id": "bertram", "type": 0}] |
    And user "participant1-v2" shares table with user "participant2-v2"
    And user "participant1-v2" create view "v1" with emoji "⚡️" for "t1" as "v1"
    And user "participant1-v2" shares view "v1" with "participant3-v2"

  Scenario: Get all rows from a table
    Given as user "participant2-v2"
    When the current user fetches all rows from "table" "t1"
    Then the reported status is 200
    And 15 rows have been loaded

  Scenario: Get all rows from a view
    Given as user "participant3-v2"
    When the current user fetches all rows from "view" "v1"
    Then the reported status is 200
    And 15 rows have been loaded

  Scenario: Get rows with invalid offset
    Given as user "participant3-v2"
    When the current user fetches rows from "view" "v1" with those parameters
      | offset | -1 |
    Then the reported status is 400
    And 0 rows have been loaded
