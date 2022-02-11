<?php

return [
	'resources' => [
        'table' => ['url' => '/table'],
	],
	'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

        ['name' => 'tableTemplate#list', 'url' => '/table/templates', 'verb' => 'GET'],

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
        ['name' => 'row#destroy', 'url' => '/row/{id}', 'verb' => 'DELETE'],
    ]
];
