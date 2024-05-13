<?php

return [
	'routes' => [

		// enable CORS for api calls
		['name' => 'api1#preflighted_cors', 'url' => '/api/1/{path}',
			'verb' => 'OPTIONS', 'requirements' => ['path' => '.+']],
		['name' => 'api2#preflighted_cors', 'url' => '/api/2/{path}',
			'verb' => 'OPTIONS', 'requirements' => ['path' => '.+']],

		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
		['name' => 'page#context', 'url' => '/app/{contextId}', 'verb' => 'GET'],

		['name' => 'tableTemplate#list', 'url' => '/table/templates', 'verb' => 'GET'],

		// API v1
		// -> tables
		['name' => 'api1#index', 'url' => '/api/1/tables', 'verb' => 'GET'],
		['name' => 'api1#createTable',	'url' => '/api/1/tables', 'verb' => 'POST'],
		['name' => 'api1#updateTable',	'url' => '/api/1/tables/{tableId}', 'verb' => 'PUT'],
		['name' => 'api1#getTable',	'url' => '/api/1/tables/{tableId}', 'verb' => 'GET'],
		['name' => 'api1#deleteTable',	'url' => '/api/1/tables/{tableId}', 'verb' => 'DELETE'],
		// -> views
		['name' => 'api1#indexViews', 'url' => '/api/1/tables/{tableId}/views', 'verb' => 'GET'],
		['name' => 'api1#createView',	'url' => '/api/1/tables/{tableId}/views', 'verb' => 'POST'],
		['name' => 'api1#getView',	'url' => '/api/1/views/{viewId}', 'verb' => 'GET'],
		['name' => 'api1#updateView',	'url' => '/api/1/views/{viewId}', 'verb' => 'PUT'],
		['name' => 'api1#deleteView',	'url' => '/api/1/views/{viewId}', 'verb' => 'DELETE'],
		// -> share
		['name' => 'api1#getShare',	'url' => '/api/1/shares/{shareId}', 'verb' => 'GET'],
		['name' => 'api1#indexViewShares',	'url' => '/api/1/views/{viewId}/shares', 'verb' => 'GET'],
		['name' => 'api1#indexTableShares', 'url' => '/api/1/tables/{tableId}/shares', 'verb' => 'GET'],
		['name' => 'api1#createShare',	'url' => '/api/1/shares', 'verb' => 'POST'],
		['name' => 'api1#deleteShare',	'url' => '/api/1/shares/{shareId}', 'verb' => 'DELETE'],
		['name' => 'api1#updateSharePermissions',	'url' => '/api/1/shares/{shareId}', 'verb' => 'PUT'],
		['name' => 'api1#updateShareDisplayMode',	'url' => '/api/1/shares/{shareId}/display-mode', 'verb' => 'PUT'],
		['name' => 'api1#createTableShare',	'url' => '/api/1/tables/{tableId}/shares', 'verb' => 'POST'],
		// -> columns
		['name' => 'api1#indexTableColumns',	'url' => '/api/1/tables/{tableId}/columns', 'verb' => 'GET'],
		['name' => 'api1#indexViewColumns', 'url' => '/api/1/views/{viewId}/columns', 'verb' => 'GET'],
		['name' => 'api1#createColumn',	'url' => '/api/1/columns', 'verb' => 'POST'],
		['name' => 'api1#createTableColumn', 'url' => '/api/1/tables/{tableId}/columns', 'verb' => 'POST'],
		['name' => 'api1#updateColumn',	'url' => '/api/1/columns/{columnId}', 'verb' => 'PUT'],
		['name' => 'api1#getColumn',	'url' => '/api/1/columns/{columnId}', 'verb' => 'GET'],
		['name' => 'api1#deleteColumn',	'url' => '/api/1/columns/{columnId}', 'verb' => 'DELETE'],
		// -> rows
		['name' => 'api1#indexTableRowsSimple',	'url' => '/api/1/tables/{tableId}/rows/simple', 'verb' => 'GET'],
		['name' => 'api1#indexTableRows',	'url' => '/api/1/tables/{tableId}/rows', 'verb' => 'GET'],
		['name' => 'api1#indexViewRows',	'url' => '/api/1/views/{viewId}/rows', 'verb' => 'GET'],
		['name' => 'api1#createRowInView',	'url' => '/api/1/views/{viewId}/rows', 'verb' => 'POST'],
		['name' => 'api1#createRowInTable', 'url' => '/api/1/tables/{tableId}/rows', 'verb' => 'POST'],

		['name' => 'api1#getRow',	'url' => '/api/1/rows/{rowId}', 'verb' => 'GET'],
		['name' => 'api1#deleteRowByView',	'url' => '/api/1/views/{viewId}/rows/{rowId}', 'verb' => 'DELETE'],
		['name' => 'api1#updateRow',	'url' => '/api/1/rows/{rowId}', 'verb' => 'PUT'],
		['name' => 'api1#deleteRow',	'url' => '/api/1/rows/{rowId}', 'verb' => 'DELETE'],
		// -> import
		['name' => 'api1#importInTable', 'url' => '/api/1/import/table/{tableId}', 'verb' => 'POST'],
		['name' => 'api1#importInView', 'url' => '/api/1/import/views/{viewId}', 'verb' => 'POST'],

		// table
		['name' => 'table#index', 'url' => '/table', 'verb' => 'GET'],
		['name' => 'table#show', 'url' => '/table/{id}', 'verb' => 'GET'],
		['name' => 'table#create', 'url' => '/table', 'verb' => 'POST'],
		['name' => 'table#update', 'url' => '/table/{id}', 'verb' => 'PUT'],
		['name' => 'table#destroy', 'url' => '/table/{id}', 'verb' => 'DELETE'],

		// view
		['name' => 'view#index', 'url' => '/view/table/{tableId}', 'verb' => 'GET'],
		['name' => 'view#indexSharedWithMe', 'url' => '/view', 'verb' => 'GET'],
		['name' => 'view#show', 'url' => '/view/{id}', 'verb' => 'GET'],
		['name' => 'view#create', 'url' => '/view', 'verb' => 'POST'],
		['name' => 'view#update', 'url' => '/view/{id}', 'verb' => 'PUT'],
		['name' => 'view#destroy', 'url' => '/view/{id}', 'verb' => 'DELETE'],

		// columns
		['name' => 'column#indexTableByView', 'url' => '/column/table/{tableId}/view/{viewId}', 'verb' => 'GET'],
		['name' => 'column#index', 'url' => '/column/table/{tableId}', 'verb' => 'GET'],
		['name' => 'column#show', 'url' => '/column/{id}', 'verb' => 'GET'],
		['name' => 'column#indexView', 'url' => '/column/view/{viewId}', 'verb' => 'GET'],
		['name' => 'column#create', 'url' => '/column', 'verb' => 'POST'],
		['name' => 'column#update', 'url' => '/column/{id}', 'verb' => 'PUT'],
		['name' => 'column#destroy', 'url' => '/column/{id}', 'verb' => 'DELETE'],

		// rows
		['name' => 'row#index', 'url' => '/row/table/{tableId}', 'verb' => 'GET'],
		['name' => 'row#show', 'url' => '/row/{id}', 'verb' => 'GET'],
		['name' => 'row#indexView', 'url' => '/row/view/{viewId}', 'verb' => 'GET'],
		['name' => 'row#create', 'url' => '/row', 'verb' => 'POST'],
		['name' => 'row#update', 'url' => '/row/{id}/column/{columnId}', 'verb' => 'PUT'],
		['name' => 'row#updateSet', 'url' => '/row/{id}', 'verb' => 'PUT'],
		['name' => 'row#destroyByView', 'url' => '/view/{viewId}/row/{id}', 'verb' => 'DELETE'],
		['name' => 'row#destroy', 'url' => '/row/{id}', 'verb' => 'DELETE'],

		// shares
		['name' => 'share#index', 'url' => '/share/table/{tableId}', 'verb' => 'GET'],
		['name' => 'share#indexView', 'url' => '/share/view/{viewId}', 'verb' => 'GET'],
		['name' => 'share#show', 'url' => '/share/{id}', 'verb' => 'GET'],
		['name' => 'share#create', 'url' => '/share', 'verb' => 'POST'],
		['name' => 'share#updatePermission', 'url' => '/share/{id}/permission', 'verb' => 'PUT'],
		['name' => 'share#updateDisplayMode', 'url' => '/share/{id}/display-mode', 'verb' => 'PUT'],
		['name' => 'share#destroy', 'url' => '/share/{id}', 'verb' => 'DELETE'],

		// import
		['name' => 'import#importInTable', 'url' => '/import/table/{tableId}', 'verb' => 'POST'],
		['name' => 'import#importInView', 'url' => '/import/view/{viewId}', 'verb' => 'POST'],
		['name' => 'import#importUploadInTable', 'url' => '/importupload/table/{tableId}', 'verb' => 'POST'],
		['name' => 'import#importUploadInView', 'url' => '/importupload/view/{viewId}', 'verb' => 'POST'],

		// search
		['name' => 'search#all', 'url' => '/search/all', 'verb' => 'GET'],
	],
	'ocs' => [
		// API v2
		['name' => 'ApiGeneral#index', 'url' => '/api/2/init', 'verb' => 'GET'],
		// -> tables
		['name' => 'ApiTables#index', 'url' => '/api/2/tables', 'verb' => 'GET'],
		['name' => 'ApiTables#show', 'url' => '/api/2/tables/{id}', 'verb' => 'GET'],
		['name' => 'ApiTables#create', 'url' => '/api/2/tables', 'verb' => 'POST'],
		['name' => 'ApiTables#update', 'url' => '/api/2/tables/{id}', 'verb' => 'PUT'],
		['name' => 'ApiTables#destroy', 'url' => '/api/2/tables/{id}', 'verb' => 'DELETE'],
		['name' => 'ApiTables#transfer', 'url' => '/api/2/tables/{id}/transfer', 'verb' => 'PUT'],

		['name' => 'ApiColumns#index', 'url' => '/api/2/columns/{nodeType}/{nodeId}', 'verb' => 'GET'],
		['name' => 'ApiColumns#show', 'url' => '/api/2/columns/{id}', 'verb' => 'GET'],
		['name' => 'ApiColumns#createNumberColumn', 'url' => '/api/2/columns/number', 'verb' => 'POST'],
		['name' => 'ApiColumns#createTextColumn', 'url' => '/api/2/columns/text', 'verb' => 'POST'],
		['name' => 'ApiColumns#createSelectionColumn', 'url' => '/api/2/columns/selection', 'verb' => 'POST'],
		['name' => 'ApiColumns#createDatetimeColumn', 'url' => '/api/2/columns/datetime', 'verb' => 'POST'],
		['name' => 'ApiColumns#createUsergroupColumn', 'url' => '/api/2/columns/usergroup', 'verb' => 'POST'],

		['name' => 'ApiFavorite#create', 'url' => '/api/2/favorites/{nodeType}/{nodeId}', 'verb' => 'POST', 'requirements' => ['nodeType' => '(\d+)', 'nodeId' => '(\d+)']],
		['name' => 'ApiFavorite#destroy', 'url' => '/api/2/favorites/{nodeType}/{nodeId}', 'verb' => 'DELETE', 'requirements' => ['nodeType' => '(\d+)', 'nodeId' => '(\d+)']],
		['name' => 'Context#index', 'url' => '/api/2/contexts', 'verb' => 'GET'],
		['name' => 'Context#show', 'url' => '/api/2/contexts/{contextId}', 'verb' => 'GET'],
		['name' => 'Context#create', 'url' => '/api/2/contexts', 'verb' => 'POST'],
		['name' => 'Context#update', 'url' => '/api/2/contexts/{contextId}', 'verb' => 'PUT'],
		['name' => 'Context#destroy', 'url' => '/api/2/contexts/{contextId}', 'verb' => 'DELETE'],
		['name' => 'Context#transfer', 'url' => '/api/2/contexts/{contextId}/transfer', 'verb' => 'PUT'],
		['name' => 'Context#addNode', 'url' => '/api/2/contexts/{contextId}/nodes', 'verb' => 'POST'],
		['name' => 'Context#removeNode', 'url' => '/api/2/contexts/{contextId}/nodes/{nodeRelId}', 'verb' => 'DELETE'],
		['name' => 'Context#updateContentOrder', 'url' => '/api/2/contexts/{contextId}/pages/{pageId}', 'verb' => 'PUT'],
	]
];
