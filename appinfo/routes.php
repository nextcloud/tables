<?php

return [
	'resources' => [
        'table' => ['url' => '/table'],
        // 'column' => ['url' => '/column'],
	],
	'routes' => [
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'column#index', 'url' => '/column/{tableId}', 'verb' => 'GET'],
        ['name' => 'column#show', 'url' => '/column/{id}', 'verb' => 'GET'],
        ['name' => 'column#create', 'url' => '/column', 'verb' => 'POST'],
        ['name' => 'column#update', 'url' => '/column/{id}', 'verb' => 'PUT'],
        ['name' => 'column#destroy', 'url' => '/column/{id}', 'verb' => 'DELETE']
	]
];
