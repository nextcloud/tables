<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * This file is needed, because API extractor will only read this one and not all the php files
 * Otherwise API extractor won't know all the return types...
 */

namespace OCA\Tables;

/**
 * @psalm-type TablesView = array{
 * 	id: int,
 * 	title: string,
 * 	emoji: string|null,
 *  tableId: int,
 * 	ownership: string,
 * 	ownerDisplayName: string|null,
 * 	createdBy: string,
 * 	createdAt: string,
 * 	lastEditBy: string,
 * 	lastEditAt: string,
 *  description: string|null,
 *  columns: list<int>,
 *  columnSettings:list<array{columnId: int, order: int, readonly: bool}>,
 *  sort: list<array{columnId: int, mode: 'ASC'|'DESC'}>,
 *  filter: list<list<array{columnId: int, operator: 'begins-with'|'ends-with'|'contains'|'does-not-contain'|'is-equal'|'is-not-equal'|'is-greater-than'|'is-greater-than-or-equal'|'is-lower-than'|'is-lower-than-or-equal'|'is-empty', value: string|int|float}>>,
 * 	isShared: bool,
 *	favorite: bool,
 * 	onSharePermissions: ?array{
 * 		read: bool,
 * 		create: bool,
 *     	update: bool,
 * 		delete: bool,
 * 		manage: bool,
 * 	},
 *  hasShares: bool,
 *  rowsCount: int,
 * }
 *
 * @psalm-type TablesTable = array{
 * 	id: int,
 * 	title: string,
 * 	emoji: string|null,
 * 	ownership: string,
 * 	ownerDisplayName: string,
 * 	createdBy: string,
 * 	createdAt: string,
 * 	lastEditBy: string,
 * 	lastEditAt: string,
 *	archived: bool,
 *	favorite: bool,
 * 	isShared: bool,
 * 	onSharePermissions: ?array{
 * 		read: bool,
 * 		create: bool,
 *     	update: bool,
 * 		delete: bool,
 * 		manage: bool,
 * 	},
 *  hasShares: bool,
 *  rowsCount: int,
 *  views: list<TablesView>,
 *  columnsCount: int,
 * }
 *
 * @psalm-type TablesIndex = array{
 * 	tables: list<TablesTable>,
 *  views: list<TablesView>,
 * }
 *
 * @psalm-type TablesRow = array{
 * 	id: int,
 * 	tableId: int,
 * 	createdBy: string,
 * 	createdAt: string,
 * 	lastEditBy: string,
 * 	lastEditAt: string,
 *  data: ?array{columnId: int, value: mixed},
 * }
 *
 * @psalm-type TablesShare = array{
 * 	id: int,
 * 	sender: string,
 * 	receiver: string,
 *  receiverDisplayName: string,
 * 	receiverType: string,
 * 	nodeId: int,
 * 	nodeType: string,
 * 	permissionRead: bool,
 *  permissionCreate: bool,
 *  permissionUpdate: bool,
 *  permissionDelete: bool,
 * 	permissionManage: bool,
 *  createdAt: string,
 *  createdBy: string,
 * }
 *
 * @psalm-type TablesColumn = array{
 *  id: int,
 *  title: string,
 *  tableId: int,
 *  createdBy: string,
 *  createdAt: string,
 *  lastEditBy: string,
 *  lastEditAt: string,
 *  type: string,
 *  subtype: string,
 *  mandatory: bool,
 *  description: string,
 *  orderWeight: int,
 *  numberDefault: float,
 *  numberMin: float,
 *  numberMax: float,
 *  numberDecimals: int,
 *  numberPrefix: string,
 *  numberSuffix: string,
 *  textDefault: string,
 *  textAllowedPattern: string,
 *  textMaxLength: int,
 *  textUnique: bool,
 *  selectionOptions: string,
 *  selectionDefault: string,
 *  datetimeDefault: string,
 *  usergroupDefault: string,
 *  usergroupMultipleItems: bool,
 *  usergroupSelectUsers: bool,
 *  usergroupSelectGroups: bool,
 *  usergroupSelectTeams: bool,
 *  showUserStatus: bool,
 *  viewColumnInformation: ?array{
 *     columnId: int,
 *     order: int,
 *     readonly: bool,
 *     mandatory: bool,
 *  },
 *  customSettings: ?array{
 *     width: int,
 *  },
 * }
 *
 * @psalm-type TablesImportState = array{
 *  found_columns_count: int,
 *  matching_columns_count: int,
 *  created_columns_count: int,
 *  inserted_rows_count: int,
 *  updated_rows_count: int,
 *  errors_parsing_count: int,
 *  errors_count: int,
 * }
 *
 * @psalm-type TablesContext = array{
 *   id: int,
 *   name: string,
 *   iconName: string,
 *   description: string,
 *   owner: string,
 *   ownerType: int,
 * }
 *
 * @psalm-type TablesContextNavigation = array{
 *     id: int,
 *     shareId: int,
 *     displayMode: int,
 *     userId: string,
 * }
 */
class ResponseDefinitions {
}
