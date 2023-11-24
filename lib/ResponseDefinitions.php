<?php
/**
 * This file is needed, because API extractor will only read this one and not all the php files
 * Otherwise API extractor won't know all the return types...
 */

namespace OCA\Tables;

/**
 * @psalm-type TablesView = array{
 * 	id: int,
 * 	title: string,
 * 	emoji: string,
 *  tableId: int,
 * 	ownership: string,
 * 	ownerDisplayName: string,
 * 	createdBy: string,
 * 	createdAt: string,
 * 	lastEditBy: string,
 * 	lastEditAt: string,
 *  description: string,
 *  columns: ?int[],
 *  sort: ?array{columnId: int, mode: 'ASC'|'DESC'},
 *  filter: ?array{columnId: int, operator: 'begins-with'|'ends-with'|'contains'|'is-equal'|'is-greater-than'|'is-greater-than-or-equal'|'is-lower-than'|'is-lower-than-or-equal'|'is-empty', value: string|int|float},
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
 * }
 *
 * @psalm-type TablesTable = array{
 * 	id: int,
 * 	title: string,
 * 	emoji: string,
 * 	ownership: string,
 * 	ownerDisplayName: string,
 * 	createdBy: string,
 * 	createdAt: string,
 * 	lastEditBy: string,
 * 	lastEditAt: string,
 * 	isShared: bool,
 * 	onSharePermissions: ?array{
 * 		read: bool,
 * 		create: bool,
 *     	update: bool,
 * 		delete: bool,
 * 		manage: bool
 * 	},
 *  hasShares: bool,
 *  rowsCount: int,
 *  views: TablesView[],
 * }
 *
 * @psalm-type TablesIndex = array{
 * 	tables: TablesTable[],
 *  views: TablesView[],
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
 * @psalm-type TablesCellNumber = array{
 *     columnId: int,
 *     columnType: 'number',
 *     format: 'float|int',
 *     value: int|float,
 * }
 * @psalm-type TablesCellNumberProgress = array{
 *     columnId: int,
 *     columnType: 'number-progress',
 *     format: 'int<0, 100>',
 *     value: int,
 * }
 * @psalm-type TablesCellNumberStars = array{
 *     columnId: int,
 *     columnType: 'number-stars',
 *     format: 'int<0, 5>',
 *     value: int,
 * }
 * @psalm-type TablesCellText = array{
 *     columnId: int,
 *     columnType: 'text-line'|'text-rich',
 *     format: 'string',
 *     value: string,
 * }
 * @psalm-type TablesCellTextLink = array{
 *     columnId: int,
 *     columnType: 'text-link',
 *     format: 'json array{title: string, subline: string, providerId: string, value: string}',
 *     value: array{title: string, subline: string, providerId: string, value: string},
 * }
 * @psalm-type TablesCellSelection = array{
 *     columnId: int,
 *     columnType: 'selection',
 *     format: 'json ?array{optionId: int, optionLabel: string}',
 *     value: ?array{optionId: int, optionLabel: string},
 * }
 * @psalm-type TablesCellSelectionMulti = array{
 *     columnId: int,
 *     columnType: 'selection-multi',
 *     format: 'json ?array<array{optionId: int, optionLabel: string}>',
 *     value: ?array<array{optionId: int, optionLabel: string}>,
 * }
 * @psalm-type TablesCellSelectionCheck = array{
 *     columnId: int,
 *     columnType: 'selection-check',
 *     format: 'bool',
 *     value: bool,
 * }
 * @psalm-type TablesCellDatetime = array{
 *     columnId: int,
 *     columnType: 'datetime',
 *     format: 'string "Y-m-d H:i"',
 *     value: string,
 * }
 * @psalm-type TablesCellDatetimeDate = array{
 *     columnId: int,
 *     columnType: 'datetime-date',
 *     format: 'string "Y-m-d"',
 *     value: string,
 * }
 * @psalm-type TablesCellDatetimeTime = array{
 *     columnId: int,
 *     columnType: 'datetime-time',
 *     format: 'string "H:i"',
 *     value: string,
 * }
 *
 * @psalm-type TablesApiRow = array{
 * 	id: int,
 * 	tableId: int,
 * 	createdBy: string,
 * 	createdAt: string,
 * 	lastEditBy: string,
 * 	lastEditAt: string,
 *  data: ?array<TablesCellNumber|TablesCellNumberProgress|TablesCellNumberStars|TablesCellText|TablesCellTextLink|TablesCellSelection|TablesCellSelectionMulti|TablesCellSelectionCheck|TablesCellDatetime|TablesCellDatetimeDate|TablesCellDatetimeTime>,
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
 *  	numberMax: float,
 *  	numberDecimals: int,
 *  	numberPrefix: string,
 *  	numberSuffix: string,
 *  	textDefault: string,
 *  	textAllowedPattern: string,
 *  	textMaxLength: int,
 *  	selectionOptions: string,
 *  	selectionDefault: string,
 *  	datetimeDefault: string,
 * }
 *
 * @psalm-type TablesImportState = array{
 *  found_columns_count: int,
 *  matching_columns_count: int,
 *  created_columns_count: int,
 *  inserted_rows_count: int,
 *  errors_parsing_count: int,
 *  errors_count: int,
 * }
 */
class ResponseDefinitions {
}
