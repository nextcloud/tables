<?php

return [
	'routes' => [

		// enable CORS for api calls (API version 1)
		['name' => 'api1#preflighted_cors', 'url' => '/api/1/{path}',
			'verb' => 'OPTIONS', 'requirements' => ['path' => '.+']],

		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

		['name' => 'tableTemplate#list', 'url' => '/table/templates', 'verb' => 'GET'],

		// API
		['name' => 'api1#index', 'url' => '/api/1/tables', 'verb' => 'GET'],
		['name' => 'api1#data',	'url' => '/api/1/table/{tableId}', 'verb' => 'GET'],

		// table
		['name' => 'table#index', 'url' => '/table', 'verb' => 'GET'],
		['name' => 'table#show', 'url' => '/table/{id}', 'verb' => 'GET'],
		['name' => 'table#create', 'url' => '/table', 'verb' => 'POST'],
		['name' => 'table#update', 'url' => '/table/{id}', 'verb' => 'PUT'],
		['name' => 'table#destroy', 'url' => '/table/{id}', 'verb' => 'DELETE'],

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
	]
];
