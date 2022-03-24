<?php

namespace OCA\Tables\Service;

use OCA\Tables\Db\Table;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\PermissionError;
use OCP\IL10N;

class TableTemplateService {

    /** @var IL10N */
    private $l;

    /** @var ColumnService */
    private $columnService;

    private $userId;

    public function __construct(IL10N $l, ColumnService $columnService, $userId) {
        $this->l = $l;
        $this->columnService = $columnService;
        $this->userId = $userId;
    }

    public function getTemplateList(): array
    {
        return [
            [
                'name'          => 'todo',
                'title'         => $this->l->t('ToDo list'),
                'icon'          => 'icon-checkmark',
                'description'   => $this->l->t('Setup a simple todo-list.')
            ],
            [
                'name'          => 'members',
                'title'         => $this->l->t('Members'),
                'icon'          => 'icon-menu-sidebar',
                'description'   => $this->l->t('List of members with some basic attributes.')
            ],
        ];
    }

    /**
     * @throws InternalError|PermissionError
     */
    public function makeTemplate(Table $table, string $template): Table {
        if($template === 'todo') {
            $this->makeTodo($table);
        } elseif ($template === 'members') {
            $this->makeMembers($table);
        }
        return $table;
    }

    /**
     * @throws InternalError
     * @throws PermissionError
     */
    private function makeMembers(Table $table) {

        $params = [
            'title' => $this->l->t('Name'),
            'type' => 'text',
            'subtype' => 'line',
            'mandatory' => true,
            'orderWeight' => 50,
        ];
        $this->createColumn($table->id, $params);

        $params = [
            'title' => $this->l->t('Position'),
            'type' => 'text',
            'subtype' => 'line',
            'orderWeight' => 40,
        ];
        $this->createColumn($table->id, $params);

        $params = [
            'title' => $this->l->t('Skills'),
            'type' => 'text',
            'subtype' => 'long',
            'orderWeight' => 30,
        ];
        $this->createColumn($table->id, $params);

        $params = [
            'title' => $this->l->t('Birthday'),
            'type' => 'text',
            'subtype' => 'line',
            'orderWeight' => 20,
        ];
        $this->createColumn($table->id, $params);

        $params = [
            'title' => $this->l->t('Comments'),
            'type' => 'text',
            'subtype' => 'long',
            'orderWeight' => 10,
        ];
        $this->createColumn($table->id, $params);
    }

    /**
     * @throws InternalError
     * @throws PermissionError
     */
    private function makeTodo(Table $table) {

        $params = [
            'title' => $this->l->t('Task'),
            'type' => 'text',
            'subtype' => 'line',
            'mandatory' => true,
            'orderWeight' => 50,
        ];
        $this->createColumn($table->id, $params);

        $params = [
            'title' => $this->l->t('Description'),
            'type' => 'text',
            'subtype' => 'long',
            'description' => $this->l->t('Title or short description'),
            'textMultiline' => true,
            'orderWeight' => 40,
        ];
        $this->createColumn($table->id, $params);

        $params = [
            'title' => $this->l->t('Target'),
            'type' => 'text',
            'subtype' => 'long',
            'description' => $this->l->t('Date, time or whatever'),
            'orderWeight' => 30,
        ];
        $this->createColumn($table->id, $params);

        $params = [
            'title' => $this->l->t('Progress'),
            'type' => 'number',
            'subtype' => 'progress',
            'orderWeight' => 20,
            'numberDefault' => 0,
        ];
        $this->createColumn($table->id, $params);

        $params = [
            'title' => $this->l->t('Comments'),
            'type' => 'text',
            'subtype' => 'long',
            'orderWeight' => 10,
        ];
        $this->createColumn($table->id, $params);
    }

    /**
     * @throws InternalError|PermissionError
     */
    private function createColumn($tableId, $parameters): void
    {
        $this->columnService->create(

            // tableId
            $tableId,

            // title
            (isset($parameters['title']) && $parameters['title'] != '') ? $parameters['title'] : $this->l->t('No title given'),

            // userId
            $this->userId,

            // column type
            (isset($parameters['type'])) ? $parameters['type'] : 'text',

            // column subtype
            (isset($parameters['subtype'])) ? $parameters['subtype'] : '',

            // prefix
            (isset($parameters['numberPrefix'])) ? $parameters['numberPrefix'] : '',

            // suffix
            (isset($parameters['numberSuffix'])) ? $parameters['numberSuffix'] : '',

            // mandatory
            isset($parameters['mandatory']) && !!$parameters['mandatory'],

            // description
            (isset($parameters['description'])) ? $parameters['description'] : '',

            // textDefault
            (isset($parameters['textDefault'])) ? $parameters['textDefault'] : '',

            // textAllowedPattern
            (isset($parameters['textAllowedPattern'])) ? $parameters['textAllowedPattern'] : '',

            // textMaxLength
            (isset($parameters['textMaxLength'])) ? $parameters['textMaxLength'] : -1,

            // numberDefault
            (isset($parameters['numberDefault'])) ? $parameters['numberDefault'] : null,

            // numberMin
            (isset($parameters['numberMin'])) ? $parameters['numberMin'] : null,

            // numberMax
            (isset($parameters['numberMax'])) ? $parameters['numberMax'] : null,

            // numberDecimals
            (isset($parameters['numberDecimals'])) ? $parameters['numberDecimals'] : null,

            // selectionOptions
            (isset($parameters['selectionOptions'])) ? $parameters['selectionOptions'] : '',

            // selectionDefault
            (isset($parameters['selectionDefault'])) ? $parameters['selectionDefault'] : '',

            // orderWeight
            (isset($parameters['orderWeight'])) ? $parameters['orderWeight'] : 0,
        );
    }
}
