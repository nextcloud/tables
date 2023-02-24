<?php

namespace OCA\Tables\Service;

use OCA\Tables\Db\Column;
use OCA\Tables\Db\Table;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\PermissionError;
use OCP\DB\Exception;
use OCP\IL10N;

class TableTemplateService {
	private IL10N $l;

	private ColumnService $columnService;

	private RowService $rowService;

	private ?string $userId;

	public function __construct(IL10N $l, ColumnService $columnService, ?string $userId, RowService $rowService) {
		$this->l = $l;
		$this->columnService = $columnService;
		$this->rowService = $rowService;
		$this->userId = $userId;
	}

	/**
	 * @return array[]
	 */
	public function getTemplateList(): array {
		return [
			[
				'name' => 'todo',
				'title' => $this->l->t('ToDo list'),
				'icon' => '✅',
				'description' => $this->l->t('Setup a simple todo-list.')
			],
			[
				'name' => 'members',
				'title' => $this->l->t('Members'),
				'icon' => '🫂',
				'description' => $this->l->t('List of members with some basic attributes.')
			],
			[
				'name' => 'customers',
				'title' => $this->l->t('Customers'),
				'icon' => '💼',
				'description' => $this->l->t('Manage your customers.')
			],
			[
				'name' => 'vacation-requests',
				'title' => $this->l->t('Vacation requests'),
				'icon' => '️🏝',
				'description' => $this->l->t('Track your weight and other health measures.')
			],
			[
				'name' => 'weight',
				'title' => $this->l->t('Weight tracking'),
				'icon' => '📉',
				'description' => $this->l->t('Track your weight and other health measures.')
			],
		];
	}

	/**
	 * @param Table $table
	 * @param string $template
	 * @return Table
	 * @throws InternalError
	 * @throws PermissionError
	 */
	public function makeTemplate(Table $table, string $template): Table {
		if ($template === 'todo') {
			$this->makeTodo($table);
		} elseif ($template === 'members') {
			$this->makeMembers($table);
		} elseif ($template === 'weight') {
			$this->makeWeight($table);
		} elseif ($template === 'vacation-requests') {
			$this->makeVacationRequests($table);
		} elseif ($template === 'customers') {
			$this->makeCustomers($table);
		}
		return $table;
	}

	/**
	 * @param Table $table
	 * @throws InternalError
	 * @throws PermissionError
	 */
	private function makeWeight(Table $table):void {
		$columns = [];

		$params = [
			'title' => $this->l->t('Date'),
			'type' => 'datetime',
			'subtype' => 'date',
			'mandatory' => true,
			'datetimeDefault' => 'today',
			'orderWeight' => 50,
		];
		$columns['date'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Weight'),
			'type' => 'number',
			'numberSuffix' => 'kg',
			'numberMin' => 0,
			'numberMax' => 200,
			'orderWeight' => 40,
		];
		$columns['weight'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Body fat'),
			'type' => 'number',
			'numberMin' => 0,
			'numberMax' => 100,
			'numberSuffix' => '%',
			'orderWeight' => 30,
		];
		$columns['bodyFat'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Feeling over all'),
			'type' => 'number',
			'subtype' => 'stars',
			'orderWeight' => 20,
		];
		$columns['feeling'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Comments'),
			'type' => 'text',
			'subtype' => 'long',
			'orderWeight' => 10,
		];
		$columns['comment'] = $this->createColumn($table->id, $params);

		// let's add some example rows
		$this->createRow($table, [
			$columns['date']->getId() => '2022-03-01',
			$columns['weight']->getId() => 92.5,
			$columns['bodyFat']->getId() => 30,
			$columns['feeling']->getId() => 4,
			$columns['comment']->getId() => '',
		]);
		$this->createRow($table, [
			$columns['date']->getId() => '2022-03-02',
			$columns['weight']->getId() => 92.7,
			$columns['bodyFat']->getId() => 30.3,
			$columns['feeling']->getId() => 3,
			$columns['comment']->getId() => $this->l->t('feel sick'),
		]);
		$this->createRow($table, [
			$columns['date']->getId() => '2022-03-10',
			$columns['weight']->getId() => 91,
			$columns['bodyFat']->getId() => 33.1,
			$columns['feeling']->getId() => 4,
			$columns['comment']->getId() => '',
		]);
		$this->createRow($table, [
			$columns['date']->getId() => '2022-03-19',
			$columns['weight']->getId() => 92.5,
			$columns['bodyFat']->getId() => 30.7,
			$columns['feeling']->getId() => 5,
			$columns['comment']->getId() => $this->l->t('party-time'),
		]);
	}

	/**
	 * @param Table $table
	 * @throws InternalError
	 * @throws PermissionError
	 */
	private function makeCustomers(Table $table):void {
		$columns = [];

		$params = [
			'title' => $this->l->t('Date'),
			'type' => 'datetime',
			'subtype' => 'date',
			'mandatory' => true,
			'datetimeDefault' => 'today',
			'orderWeight' => 50,
		];
		$columns['date'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Weight'),
			'type' => 'number',
			'numberSuffix' => 'kg',
			'numberMin' => 0,
			'numberMax' => 200,
			'orderWeight' => 40,
		];
		$columns['weight'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Body fat'),
			'type' => 'number',
			'numberMin' => 0,
			'numberMax' => 100,
			'numberSuffix' => '%',
			'orderWeight' => 30,
		];
		$columns['bodyFat'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Feeling over all'),
			'type' => 'number',
			'subtype' => 'stars',
			'orderWeight' => 20,
		];
		$columns['feeling'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Comments'),
			'type' => 'text',
			'subtype' => 'long',
			'orderWeight' => 10,
		];
		$columns['comment'] = $this->createColumn($table->id, $params);

		// let's add some example rows
		$this->createRow($table, [
			$columns['date']->getId() => '2022-03-01',
			$columns['weight']->getId() => 92.5,
			$columns['bodyFat']->getId() => 30,
			$columns['feeling']->getId() => 4,
			$columns['comment']->getId() => '',
		]);
		$this->createRow($table, [
			$columns['date']->getId() => '2022-03-02',
			$columns['weight']->getId() => 92.7,
			$columns['bodyFat']->getId() => 30.3,
			$columns['feeling']->getId() => 3,
			$columns['comment']->getId() => $this->l->t('feel sick'),
		]);
		$this->createRow($table, [
			$columns['date']->getId() => '2022-03-10',
			$columns['weight']->getId() => 91,
			$columns['bodyFat']->getId() => 33.1,
			$columns['feeling']->getId() => 4,
			$columns['comment']->getId() => '',
		]);
		$this->createRow($table, [
			$columns['date']->getId() => '2022-03-19',
			$columns['weight']->getId() => 92.5,
			$columns['bodyFat']->getId() => 30.7,
			$columns['feeling']->getId() => 5,
			$columns['comment']->getId() => $this->l->t('party-time'),
		]);
	}

	/**
	 * @param Table $table
	 * @throws InternalError
	 * @throws PermissionError
	 */
	private function makeVacationRequests(Table $table):void {
		$columns = [];

		$params = [
			'title' => $this->l->t('Employee name'),
			'type' => 'text',
			'subtype' => 'line',
			'mandatory' => true,
			'orderWeight' => 50,
		];
		$columns['employee'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('from'),
			'description' => $this->l->t('When is your vacation starting?'),
			'type' => 'datetime',
			'subtype' => 'date',
			'orderWeight' => 41,
		];
		$columns['from'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('to'),
			'description' => $this->l->t('When is your vacation ending?'),
			'type' => 'datetime',
			'subtype' => 'date',
			'orderWeight' => 40,
		];
		$columns['to'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Number of working days'),
			'description' => $this->l->t('How many working days are included?'),
			'type' => 'number',
			'numberMin' => 0,
			'numberMax' => 100,
			'orderWeight' => 30,
		];
		$columns['workingDays'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Request date'),
			'type' => 'datetime',
			'subtype' => 'date',
			'orderWeight' => 20,
		];
		$columns['dateRequest'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Approved'),
			'type' => 'selection',
			'subtype' => 'check',
			'orderWeight' => 25,
		];
		$columns['approved'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Approve date'),
			'type' => 'datetime',
			'subtype' => 'date',
			'orderWeight' => 24,
		];
		$columns['dateApprove'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Approved by'),
			'type' => 'text',
			'subtype' => 'line',
			'orderWeight' => 23,
		];
		$columns['approveBy'] = $this->createColumn($table->id, $params);


		$params = [
			'title' => $this->l->t('Comments'),
			'type' => 'text',
			'subtype' => 'long',
			'orderWeight' => 10,
		];
		$columns['comment'] = $this->createColumn($table->id, $params);

		// let's add some example rows
		$this->createRow($table, [
			$columns['employee']->getId() => 'Alice',
			$columns['from']->getId() => '2023-02-05',
			$columns['to']->getId() => '2023-02-10',
			$columns['workingDays']->getId() => 4,
			$columns['dateRequest']->getId() => '2023-01-08',
			$columns['approved']->getId() => 'true',
			$columns['dateApprove']->getId() => '2023-02-02',
			$columns['approveBy']->getId() => 'The Boss',
			$columns['comment']->getId() => 'Bob will help for this time',
		]);
		$this->createRow($table, [
			$columns['employee']->getId() => 'Bob',
			$columns['from']->getId() => '2023-04-05',
			$columns['to']->getId() => '2023-04-12',
			$columns['workingDays']->getId() => 5,
			$columns['dateRequest']->getId() => '2023-01-18',
			$columns['approved']->getId() => 'true',
			$columns['dateApprove']->getId() => '2023-02-02',
			$columns['approveBy']->getId() => 'The Boss',
			$columns['comment']->getId() => '',
		]);
		$this->createRow($table, [
			$columns['employee']->getId() => 'Evil',
			$columns['from']->getId() => '2023-03-05',
			$columns['to']->getId() => '2023-04-10',
			$columns['workingDays']->getId() => 34,
			$columns['dateRequest']->getId() => '2023-01-30',
			$columns['approved']->getId() => 'false',
			$columns['dateApprove']->getId() => '',
			$columns['approveBy']->getId() => '',
			$columns['comment']->getId() => 'We have to talk about that.',
		]);
	}








	/**
	 * @param Table $table
	 * @throws InternalError
	 * @throws PermissionError
	 */
	private function makeMembers(Table $table):void {
		$columns = [];

		$params = [
			'title' => $this->l->t('Name'),
			'type' => 'text',
			'subtype' => 'line',
			'mandatory' => true,
			'orderWeight' => 50,
		];
		$columns['name'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Position'),
			'type' => 'text',
			'subtype' => 'line',
			'orderWeight' => 40,
		];
		$columns['position'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Skills'),
			'type' => 'text',
			'subtype' => 'long',
			'orderWeight' => 30,
		];
		$columns['skills'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Birthday'),
			'type' => 'datetime',
			'subtype' => 'date',
			'orderWeight' => 20,
		];
		$columns['birthday'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Comments'),
			'type' => 'text',
			'subtype' => 'long',
			'orderWeight' => 10,
		];
		$columns['comment'] = $this->createColumn($table->id, $params);

		// let's add some example rows
		$this->createRow($table, [
			$columns['name']->getId() => $this->l->t('Santa Claus'),
			$columns['position']->getId() => $this->l->t('Special'),
			$columns['skills']->getId() => $this->l->t('Make happy people'),
			$columns['birthday']->getId() => '2000-12-24',
			$columns['comment']->getId() => '',
		]);
	}

	/**
	 * @param Table $table
	 * @throws InternalError
	 * @throws PermissionError
	 */
	private function makeTodo(Table $table): void {
		$columns = [];

		$params = [
			'title' => $this->l->t('Task'),
			'type' => 'text',
			'subtype' => 'line',
			'mandatory' => true,
			'orderWeight' => 50,
		];
		$columns['task'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Description'),
			'type' => 'text',
			'subtype' => 'long',
			'description' => $this->l->t('Title or short description'),
			'textMultiline' => true,
			'orderWeight' => 40,
		];
		$columns['description'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Target'),
			'type' => 'text',
			'subtype' => 'long',
			'description' => $this->l->t('Date, time or whatever'),
			'orderWeight' => 30,
		];
		$columns['target'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Progress'),
			'type' => 'number',
			'subtype' => 'progress',
			'orderWeight' => 20,
			'numberDefault' => 0,
		];
		$columns['progress'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Comments'),
			'type' => 'text',
			'subtype' => 'long',
			'orderWeight' => 10,
		];
		$columns['comments'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Proofed'),
			'type' => 'selection',
			'subtype' => 'check',
			'orderWeight' => 5,
		];
		$columns['proofed'] = $this->createColumn($table->id, $params);

		// let's add some example rows
		$this->createRow($table, [
			$columns['task']->getId() => 'Create initial milestones',
			$columns['description']->getId() => 'Create some milestone to structure the project.',
			$columns['target']->getId() => 'Plan to discuss for the kickoff meeting.',
			$columns['comments']->getId() => 'Wow, that was hard work, but now it\'s done.',
			$columns['progress']->getId() => 100,
			$columns['proofed']->getId() => 'true',
		]);
		$this->createRow($table, [
			$columns['task']->getId() => 'Kickoff meeting',
			$columns['description']->getId() => 'We will have a kickoff meeting in person.',
			$columns['target']->getId() => 'Project is kicked-off and we know the vision and our first tasks.',
			$columns['comments']->getId() => 'That was nice in person again. We collected some action points, have a look at the documentation...',
			$columns['progress']->getId() => 80,
			$columns['proofed']->getId() => 'true',
		]);
		$this->createRow($table, [
			$columns['task']->getId() => 'Set up some documentation and collaboration tools',
			$columns['description']->getId() => 'Where do we in what way collaborate?',
			$columns['target']->getId() => 'We know what we are doing.',
			$columns['comments']->getId() => 'We have heard that Nextcloud could be a nice solution for it, should give it a try.',
			$columns['progress']->getId() => 10,
			$columns['proofed']->getId() => 'false',
		]);
		$this->createRow($table, [
			$columns['task']->getId() => 'Add more actions',
			$columns['description']->getId() => 'I guess we need more actions in here...',
		]);
	}

	/**
	 * @param int $tableId
	 * @param (mixed)[] $parameters
	 * @return Column
	 * @throws InternalError
	 * @throws PermissionError
	 */
	private function createColumn(int $tableId, array $parameters): ?Column {
		if ($this->userId === null) {
			return null;
		}

		return $this->columnService->create(

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

			// datetimeDefault
			(isset($parameters['datetimeDefault'])) ? $parameters['datetimeDefault'] : '',
		);
	}

	private function createRow(Table $table, array $values): void {
		$data = [];
		foreach ($values as $columnId => $value) {
			$data[] = [
				'columnId' => $columnId,
				'value' => $value
			];
		}
		try {
			$this->rowService->createComplete($table->getId(), $data);
		} catch (PermissionError|Exception $e) {
		}
	}
}
