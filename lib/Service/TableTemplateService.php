<?php

namespace OCA\Tables\Service;

use OCA\Tables\Db\Table;
use OCP\DB\Exception;
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

    public function makeTemplate(Table $table, string $template): Table {
        if($template === 'todo') {
            $this->makeTodo($table);
        } elseif ($template === 'members') {
            $this->makeMembers($table);
        }
        return $table;
    }

    private function makeMembers(Table $table) {

        $params = [
            'title' => $this->l->t('Name'),
            'type' => 'text',
            'mandatory' => true,
            'orderWeight' => 50,
        ];
        $this->createColumn($table->id, $params);

        $params = [
            'title' => $this->l->t('Position'),
            'type' => 'text',
            'orderWeight' => 40,
        ];
        $this->createColumn($table->id, $params);

        $params = [
            'title' => $this->l->t('Skills'),
            'type' => 'text',
            'textMultiline' => true,
            'orderWeight' => 30,
        ];
        $this->createColumn($table->id, $params);

        $params = [
            'title' => $this->l->t('Birthday'),
            'type' => 'text',
            'orderWeight' => 20,
        ];
        $this->createColumn($table->id, $params);

        $params = [
            'title' => $this->l->t('Comments'),
            'type' => 'text',
            'textMultiline' => true,
            'orderWeight' => 10,
        ];
        $this->createColumn($table->id, $params);
    }

    private function makeTodo(Table $table) {

        $params = [
            'title' => $this->l->t('Task'),
            'type' => 'text',
            'mandatory' => true,
            'orderWeight' => 50,
        ];
        $this->createColumn($table->id, $params);

        $params = [
            'title' => $this->l->t('Description'),
            'type' => 'text',
            'description' => 'Title or short description',
            'textMultiline' => true,
            'orderWeight' => 40,
        ];
        $this->createColumn($table->id, $params);

        $params = [
            'title' => $this->l->t('Target'),
            'type' => 'text',
            'description' => 'Date, time or whatever',
            'orderWeight' => 30,
        ];
        $this->createColumn($table->id, $params);

        $params = [
            'title' => $this->l->t('Progress'),
            'type' => 'number',
            'suffix' => '%',
            'orderWeight' => 20,
            'numberDefault' => 0,
        ];
        $this->createColumn($table->id, $params);

        $params = [
            'title' => $this->l->t('Comments'),
            'type' => 'text',
            'textMultiline' => true,
            'orderWeight' => 10,
        ];
        $this->createColumn($table->id, $params);
    }

    private function createColumn($tableId, $parameters) {
        return $this->columnService->create(

            // tableId
            $tableId,

            // title
            (isset($parameters['title']) && $parameters['title'] != '') ? $parameters['title'] : $this->l->t('Dummy title'),

            // userId
            $this->userId,

            // column type
            (isset($parameters['type'])) ? $parameters['type']: 'text',

            // prefix
            (isset($parameters['prefix'])) ? $parameters['prefix'] : '',

            // suffix
            (isset($parameters['suffix'])) ? $parameters['suffix'] : '',

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

            // textMultiline
            (isset($parameters['textMultiline'])) ? $parameters['textMultiline'] : false,

            // numberDefault
            (isset($parameters['numberDefault'])) ? $parameters['numberDefault'] : null,

            // numberMin
            (isset($parameters['numberMin'])) ? $parameters['numberMin'] : null,

            // numberMax
            (isset($parameters['numberMax'])) ? $parameters['numberMax'] : null,

            // numberDecimals
            (isset($parameters['numberDecimals'])) ? $parameters['numberDecimals'] : null,

            // orderWeight
            (isset($parameters['orderWeight'])) ? $parameters['orderWeight'] : 0,
        );
    }
}
