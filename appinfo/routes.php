<?php

return [
	'routes' => [

		// enable CORS for api calls (API version 1)
		['name' => 'api1#preflighted_cors', 'url' => '/api/1/{path}',
			'verb' => 'OPTIONS', 'requirements' => ['path' => '.+']],

		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

		['name' => 'tableTemplate#list', 'url' => '/table/templates', 'verb' => 'GET'],

		// API
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
		['name' => 'api1#deleteView',	'url' => '/api/1/views/{viewId}', 'verb' => 'DELETE'],
		['name' => 'api1#updateView',	'url' => '/api/1/views/{viewId}', 'verb' => 'PUT'],
		// -> share
		['name' => 'api1#getShare',	'url' => '/api/1/shares/{shareId}', 'verb' => 'GET'],
		['name' => 'api1#deleteShare',	'url' => '/api/1/shares/{shareId}', 'verb' => 'DELETE'],
		['name' => 'api1#updateSharePermissions',	'url' => '/api/1/shares/{shareId}', 'verb' => 'PUT'],
		// -> share -> table
		['name' => 'api1#indexTableShares',	'url' => '/api/1/tables/{tableId}/shares', 'verb' => 'GET'],
		['name' => 'api1#createTableShare',	'url' => '/api/1/tables/{tableId}/shares', 'verb' => 'POST'],
		// -> columns
		['name' => 'api1#indexTableColumns',	'url' => '/api/1/tables/{tableId}/columns', 'verb' => 'GET'],
		['name' => 'api1#createTableColumn',	'url' => '/api/1/tables/{tableId}/columns', 'verb' => 'POST'],
		['name' => 'api1#getColumn',	'url' => '/api/1/columns/{columnId}', 'verb' => 'GET'],
		['name' => 'api1#deleteColumn',	'url' => '/api/1/columns/{columnId}', 'verb' => 'DELETE'],
		['name' => 'api1#updateColumn',	'url' => '/api/1/columns/{columnId}', 'verb' => 'PUT'],
		// -> rows -> table
		['name' => 'api1#indexTableRowsSimple',	'url' => '/api/1/tables/{tableId}/rows/simple', 'verb' => 'GET'],
		['name' => 'api1#indexTableRows',	'url' => '/api/1/tables/{tableId}/rows', 'verb' => 'GET'],
		['name' => 'api1#createRow',	'url' => '/api/1/tables/{tableId}/rows', 'verb' => 'POST'],
		// -> row
		['name' => 'api1#getRow',	'url' => '/api/1/rows/{rowId}', 'verb' => 'GET'],
		['name' => 'api1#updateRow',	'url' => '/api/1/rows/{rowId}', 'verb' => 'PUT'],
		['name' => 'api1#deleteRow',	'url' => '/api/1/rows/{rowId}', 'verb' => 'DELETE'],
		// -> import
		['name' => 'api1#createImport', 'url' => '/api/1/import/table/{tableId}', 'verb' => 'POST'],


		// table
		['name' => 'table#index', 'url' => '/table', 'verb' => 'GET'],
		['name' => 'table#show', 'url' => '/table/{id}', 'verb' => 'GET'],
		['name' => 'table#create', 'url' => '/table', 'verb' => 'POST'],
		['name' => 'table#update', 'url' => '/table/{id}', 'verb' => 'PUT'],
		['name' => 'table#destroy', 'url' => '/table/{id}', 'verb' => 'DELETE'],

		// view
		['name' => 'view#index', 'url' => '/view/{tableId}', 'verb' => 'GET'],
		['name' => 'view#show', 'url' => '/view/{id}', 'verb' => 'GET'],
		['name' => 'view#create', 'url' => '/view', 'verb' => 'POST'],
		['name' => 'view#update', 'url' => '/view/{id}', 'verb' => 'PUT'],
		['name' => 'view#destroy', 'url' => '/view/{id}', 'verb' => 'DELETE'],

		// columns
		['name' => 'column#index', 'url' => '/column/{tableId}', 'verb' => 'GET'],
		['name' => 'column#show', 'url' => '/column/{id}', 'verb' => 'GET'],
		['name' => 'column#create', 'url' => '/column', 'verb' => 'POST'],
		['name' => 'column#update', 'url' => '/column/{id}', 'verb' => 'PUT'],
		['name' => 'column#destroy', 'url' => '/column/{id}', 'verb' => 'DELETE'],

		// rows
		['name' => 'row#index', 'url' => '/row/{tableId}', 'verb' => 'GET'],
		['name' => 'row#show', 'url' => '/row/{id}', 'verb' => 'GET'],
		['name' => 'row#create', 'url' => '/row/column/{columnId}', 'verb' => 'POST'],
		['name' => 'row#createComplete', 'url' => '/row', 'verb' => 'POST'],
		['name' => 'row#update', 'url' => '/row/{id}/column/{columnId}', 'verb' => 'PUT'],
		['name' => 'row#updateSet', 'url' => '/row/{id}', 'verb' => 'PUT'],
		['name' => 'row#destroy', 'url' => '/row/{id}', 'verb' => 'DELETE'],

		// shares
		['name' => 'share#index', 'url' => '/share/table/{tableId}', 'verb' => 'GET'],
		['name' => 'share#show', 'url' => '/share/{id}', 'verb' => 'GET'],
		['name' => 'share#create', 'url' => '/share', 'verb' => 'POST'],
		['name' => 'share#updatePermission', 'url' => '/share/{id}/permission', 'verb' => 'PUT'],
		['name' => 'share#destroy', 'url' => '/share/{id}', 'verb' => 'DELETE'],

		// import
		['name' => 'import#import', 'url' => '/import/table/{tableId}', 'verb' => 'POST'],
	]
];
