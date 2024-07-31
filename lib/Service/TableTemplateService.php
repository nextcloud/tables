<?php

namespace OCA\Tables\Service;

use OCA\Tables\Db\Column;
use OCA\Tables\Db\Table;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

class TableTemplateService {
	private IL10N $l;

	private ColumnService $columnService;

	private RowService $rowService;

	private ViewService $viewService;

	protected LoggerInterface $logger;

	private ?string $userId;

	private string $textRichColumnTypeName = 'rich';

	public function __construct(LoggerInterface $logger, IL10N $l, ColumnService $columnService, ?string $userId, RowService $rowService, ViewService $viewService) {
		$this->logger = $logger;
		$this->l = $l;
		$this->columnService = $columnService;
		$this->rowService = $rowService;
		$this->viewService = $viewService;
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
				'description' => $this->l->t('Use this table to collect and manage vacation requests.')
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
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws InternalError
	 * @throws MultipleObjectsReturnedException
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
		} elseif ($template === 'tutorial') {
			$this->makeStartupTable($table);
		}
		return $table;
	}

	/**
	 * @psalm-suppress PossiblyNullReference
	 * @param Table $table
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws InternalError
	 * @throws MultipleObjectsReturnedException
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
		];
		$columns['date'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Weight'),
			'type' => 'number',
			'numberSuffix' => 'kg',
			'numberMin' => 0,
			'numberMax' => 200,
		];
		$columns['weight'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Body fat'),
			'type' => 'number',
			'numberMin' => 0,
			'numberMax' => 100,
			'numberSuffix' => '%',
		];
		$columns['bodyFat'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Feeling over all'),
			'type' => 'number',
			'subtype' => 'stars',
		];
		$columns['feeling'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Comments'),
			'type' => 'text',
			'subtype' => $this->textRichColumnTypeName,
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
	 * @psalm-suppress PossiblyNullReference
	 * @param Table $table
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws InternalError
	 * @throws MultipleObjectsReturnedException
	 * @throws PermissionError
	 */
	private function makeCustomers(Table $table):void {
		$columns = [];

		$params = [
			'title' => $this->l->t('Name'),
			'type' => 'text',
			'subtype' => 'line',
		];
		$columns['name'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Account manager'),
			'type' => 'text',
			'subtype' => 'line',
		];
		$columns['accountManager'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Contract type'),
			'type' => 'text',
			'subtype' => 'line',
		];
		$columns['contractType'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Contract start'),
			'type' => 'datetime',
			'subtype' => 'date',
			'datetimeDefault' => 'today',
		];
		$columns['contractStart'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Contract end'),
			'type' => 'datetime',
			'subtype' => 'date',
		];
		$columns['contractEnd'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Description'),
			'type' => 'text',
			'subtype' => $this->textRichColumnTypeName,

		];
		$columns['description'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Contact information'),
			'type' => 'text',
			'subtype' => $this->textRichColumnTypeName,

		];
		$columns['contactInformation'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Quality of relationship'),
			'type' => 'number',
			'subtype' => 'progress',

			'numberDefault' => 30,
		];
		$columns['qualityRelationship'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Comment'),
			'type' => 'text',
			'subtype' => $this->textRichColumnTypeName,

		];
		$columns['comment'] = $this->createColumn($table->id, $params);

		// let's add some example rows
		$this->createRow($table, [
			// TRANSLATORS This is an example name
			$columns['name']->getId() => $this->l->t('Dog'),
			// TRANSLATORS This is an example account manager
			$columns['accountManager']->getId() => 'Mr. Smith',
			// TRANSLATORS This is an example contract type
			$columns['contractType']->getId() => $this->l->t('Dog food every week'),
			$columns['contractStart']->getId() => '2023-01-01',
			$columns['contractEnd']->getId() => '2023-12-31',
			// TRANSLATORS This is an example description
			$columns['description']->getId() => $this->l->t('The dog is our best friend.'),
			// TRANSLATORS This is an example contract information
			$columns['contactInformation']->getId() => $this->l->t('Standard, SLA Level 2'),
			$columns['qualityRelationship']->getId() => 80,
			// TRANSLATORS This is an example comment
			$columns['comment']->getId() => $this->l->t('Likes treats'),
		]);
		$this->createRow($table, [
			// TRANSLATORS This is an example name
			$columns['name']->getId() => $this->l->t('Cat'),
			// TRANSLATORS This is an example account manager
			$columns['accountManager']->getId() => 'Mr. Smith',
			// TRANSLATORS This is an example contract type
			$columns['contractType']->getId() => $this->l->t('Cat food every week'),
			$columns['contractStart']->getId() => '2023-03-01',
			$columns['contractEnd']->getId() => '2023-09-15',
			// TRANSLATORS This is an example description
			$columns['description']->getId() => $this->l->t('The cat is also our best friend.'),
			// TRANSLATORS This is an example contract information
			$columns['contactInformation']->getId() => $this->l->t('Standard, SLA Level 1'),
			$columns['qualityRelationship']->getId() => 40,
			// TRANSLATORS This is an example comment
			$columns['comment']->getId() => $this->l->t('New customer, let\'s see if there is more.'),
		]);
		$this->createRow($table, [
			// TRANSLATORS This is an example name
			$columns['name']->getId() => $this->l->t('Horse'),
			// TRANSLATORS This is an example account manager
			$columns['accountManager']->getId() => 'Alice',
			// TRANSLATORS This is an example contract type
			$columns['contractType']->getId() => $this->l->t('Hay and straw'),
			$columns['contractStart']->getId() => '2023-06-01',
			$columns['contractEnd']->getId() => '2023-08-31',
			// TRANSLATORS This is an example description
			$columns['description']->getId() => $this->l->t('Summer only'),
			// TRANSLATORS This is an example contract information
			$columns['contactInformation']->getId() => $this->l->t('Special'),
			$columns['qualityRelationship']->getId() => 60,
			// TRANSLATORS This is an example comment
			$columns['comment']->getId() => $this->l->t('Maybe we can make it fix for every year?!'),
		]);
	}

	/**
	 * @psalm-suppress PossiblyNullReference
	 * @param Table $table
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws InternalError
	 * @throws MultipleObjectsReturnedException
	 * @throws PermissionError
	 */
	private function makeVacationRequests(Table $table):void {
		$columns = [];

		$params = [
			'title' => $this->l->t('Employee name'),
			'type' => 'text',
			'subtype' => 'line',
			'mandatory' => true,

		];
		$columns['employee'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('from'),
			'description' => $this->l->t('When is your vacation starting?'),
			'type' => 'datetime',
			'subtype' => 'date',
			'mandatory' => true,

		];
		$columns['from'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('to'),
			'description' => $this->l->t('When is your vacation ending?'),
			'type' => 'datetime',
			'subtype' => 'date',
			'mandatory' => true,

		];
		$columns['to'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Number of working days'),
			'description' => $this->l->t('How many working days are included?'),
			'type' => 'number',
			'numberMin' => 0,
			'numberMax' => 100,
			'mandatory' => true,

		];
		$columns['workingDays'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Request date'),
			'type' => 'datetime',
			'subtype' => 'date',
			'mandatory' => true,
			'datetimeDefault' => 'today',

		];
		$columns['dateRequest'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Approved'),
			'type' => 'selection',
			'subtype' => 'check',

		];
		$columns['approved'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Approve date'),
			'type' => 'datetime',
			'subtype' => 'date',

		];
		$columns['dateApprove'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Approved by'),
			'type' => 'text',
			'subtype' => 'line',

		];
		$columns['approveBy'] = $this->createColumn($table->id, $params);


		$params = [
			'title' => $this->l->t('Comments'),
			'type' => 'text',
			'subtype' => $this->textRichColumnTypeName,

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
			// TRANSLATORS This is an example for a name or role
			$columns['approveBy']->getId() => $this->l->t('The Boss'),
			// TRANSLATORS This is an example comment
			$columns['comment']->getId() => $this->l->t('Bob will help for this time'),
		]);
		$this->createRow($table, [
			$columns['employee']->getId() => 'Bob',
			$columns['from']->getId() => '2023-04-05',
			$columns['to']->getId() => '2023-04-12',
			$columns['workingDays']->getId() => 5,
			$columns['dateRequest']->getId() => '2023-01-18',
			$columns['approved']->getId() => 'true',
			$columns['dateApprove']->getId() => '2023-02-02',
			// TRANSLATORS This is an example for a name or role
			$columns['approveBy']->getId() => $this->l->t('The Boss'),
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
			// TRANSLATORS This is an example comment
			$columns['comment']->getId() => $this->l->t('We have to talk about that.'),
		]);
		$this->createRow($table, [
			$columns['employee']->getId() => 'Pete',
			$columns['from']->getId() => '2023-12-18',
			$columns['to']->getId() => '2023-12-28',
			$columns['workingDays']->getId() => 8,
			$columns['dateRequest']->getId() => '2023-01-30',
		]);


		// let's add views
		$this->createView($table,
			[
				'title' => $this->l->t('Create Vacation Request'),
				'emoji' => '️➕',
				'columns' => json_encode([$columns['employee']->getId(), $columns['from']->getId(), $columns['to']->getId(), $columns['workingDays']->getId(), $columns['dateRequest']->getId()]),
				'sort' => json_encode([["columnId" => Column::TYPE_META_UPDATED_AT, "mode" => "ASC"]]),
				'filter' => json_encode([[["columnId" => Column::TYPE_META_CREATED_BY, "operator" => "is-equal", "value" => "@my-name"], ["columnId" => $columns['approved']->getId(), "operator" => "is-empty", "value" => ""]]]),
			]
		);
		$this->createView($table,
			[
				'title' => $this->l->t('Open Request'),
				'emoji' => '️📝',
				'columns' => json_encode(array_values(array_map(function ($col) {
					return $col->getId();
				}, $columns))),
				'sort' => json_encode([["columnId" => $columns['from']->getId(), "mode" => "ASC"]]),
				'filter' => json_encode([[["columnId" => $columns['approved']->getId(), "operator" => "is-empty", "value" => ""]]]),
			]
		);
		$this->createView($table,
			[
				'title' => $this->l->t('Request Status'),
				'emoji' => '️❓',
				'columns' => json_encode(array_values(array_map(function ($col) {
					return $col->getId();
				}, $columns))),
				'sort' => json_encode([["columnId" => Column::TYPE_META_UPDATED_BY, "mode" => "ASC"]]),
				'filter' => json_encode([[["columnId" => Column::TYPE_META_CREATED_BY, "operator" => "is-equal", "value" => "@my-name"]]]),
			]
		);
		$this->createView($table,
			[
				'title' => $this->l->t('Closed requests'),
				'emoji' => '️✅',
				'columns' => json_encode(array_values(array_map(function ($col) {
					return $col->getId();
				}, $columns))),
				'sort' => json_encode([["columnId" => Column::TYPE_META_UPDATED_BY, "mode" => "ASC"]]),
				'filter' => json_encode([[["columnId" => $columns['approved']->getId(), "operator" => "is-equal", "value" => "@checked"]], [["columnId" => $columns['approved']->getId(), "operator" => "is-equal", "value" => "@unchecked"]]]),
			]
		);
	}

	/**
	 * @psalm-suppress PossiblyNullReference
	 * @param Table $table
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws InternalError
	 * @throws MultipleObjectsReturnedException
	 * @throws PermissionError
	 */
	private function makeMembers(Table $table):void {
		$columns = [];

		$params = [
			'title' => $this->l->t('Name'),
			'type' => 'text',
			'subtype' => 'line',
			'mandatory' => true,

		];
		$columns['name'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Position'),
			'type' => 'text',
			'subtype' => 'line',

		];
		$columns['position'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Skills'),
			'type' => 'text',
			'subtype' => $this->textRichColumnTypeName,

		];
		$columns['skills'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Birthday'),
			'type' => 'datetime',
			'subtype' => 'date',

		];
		$columns['birthday'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Comments'),
			'type' => 'text',
			'subtype' => $this->textRichColumnTypeName,

		];
		$columns['comment'] = $this->createColumn($table->id, $params);

		// let's add some example rows
		$this->createRow($table, [
			// TRANSLATORS This is an example name
			$columns['name']->getId() => $this->l->t('Santa Claus'),
			// TRANSLATORS This is an example for a "position" for a member
			$columns['position']->getId() => $this->l->t('Special'),
			// TRANSLATORS This is an example for skills
			$columns['skills']->getId() => $this->l->t('Make happy people'),
			$columns['birthday']->getId() => '2000-12-24',
			$columns['comment']->getId() => '',
		]);
	}

	/**
	 * @psalm-suppress PossiblyNullReference
	 * @param Table $table
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws InternalError
	 * @throws MultipleObjectsReturnedException
	 * @throws PermissionError
	 */
	private function makeTodo(Table $table): void {
		$columns = [];

		$params = [
			'title' => $this->l->t('Task'),
			'type' => 'text',
			'subtype' => 'line',
			'mandatory' => true,

		];
		$columns['task'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Description'),
			'type' => 'text',
			'subtype' => $this->textRichColumnTypeName,
			'description' => $this->l->t('Title or short description'),
			'textMultiline' => true,

		];
		$columns['description'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Target'),
			'type' => 'text',
			'subtype' => $this->textRichColumnTypeName,
			'description' => $this->l->t('Date, time or whatever'),

		];
		$columns['target'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Progress'),
			'type' => 'number',
			'subtype' => 'progress',

			'numberDefault' => 0,
		];
		$columns['progress'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Comments'),
			'type' => 'text',
			'subtype' => $this->textRichColumnTypeName,

		];
		$columns['comments'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Proofed'),
			'type' => 'selection',
			'subtype' => 'check',

		];
		$columns['proofed'] = $this->createColumn($table->id, $params);

		// let's add some example rows
		$this->createRow($table, [
			/** @psalm-suppress PossiblyNullArgument */
			// TRANSLATORS This is an example for a task
			$columns['task']->getId() => $this->l->t('Create initial milestones'),
			// TRANSLATORS This is an example description
			$columns['description']->getId() => $this->l->t('Create some milestones to structure the project.'),
			// TRANSLATORS This is an example target
			$columns['target']->getId() => $this->l->t('Plan to discuss for the kickoff meeting.'),
			// TRANSLATORS This is an example comment
			$columns['comments']->getId() => $this->l->t('Wow, that was hard work, but now it\'s done.'),
			$columns['progress']->getId() => 100,
			$columns['proofed']->getId() => 'true',
		]);
		$this->createRow($table, [
			// TRANSLATORS This is an example for a task
			$columns['task']->getId() => $this->l->t('Kickoff meeting'),
			// TRANSLATORS This is an example description
			$columns['description']->getId() => $this->l->t('We will have a kickoff meeting in person.'),
			// TRANSLATORS This is an example target
			$columns['target']->getId() => $this->l->t('Project is kicked-off and we know the vision and our first tasks.'),
			// TRANSLATORS This is an example comment
			$columns['comments']->getId() => $this->l->t('That was nice in person again. We collected some action points, had a look at the documentation...'),
			$columns['progress']->getId() => 80,
			$columns['proofed']->getId() => 'true',
		]);
		$this->createRow($table, [
			// TRANSLATORS This is an example for a task
			$columns['task']->getId() => $this->l->t('Set up some documentation and collaboration tools'),
			// TRANSLATORS This is an example description
			$columns['description']->getId() => $this->l->t('Where and in what way do we collaborate?'),
			// TRANSLATORS This is an example target
			$columns['target']->getId() => $this->l->t('We know what we are doing.'),
			// TRANSLATORS This is an example comment
			$columns['comments']->getId() => $this->l->t('We have heard that Nextcloud could be a nice solution for it, should give it a try.'),
			$columns['progress']->getId() => 10,
			$columns['proofed']->getId() => 'false',
		]);
		$this->createRow($table, [
			// TRANSLATORS This is an example for a task
			$columns['task']->getId() => $this->l->t('Add more actions'),
			// TRANSLATORS This is an example description
			$columns['description']->getId() => $this->l->t('I guess we need more actions in here...'),
		]);
	}


	/**
	 * @psalm-suppress PossiblyNullReference
	 * @param Table $table
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws InternalError
	 * @throws MultipleObjectsReturnedException
	 * @throws PermissionError
	 */
	private function makeStartupTable(Table $table):void {
		$columns = [];

		$params = [
			// TRANSLATORS This is the title of the first column for a list of actions
			'title' => $this->l->t('What'),
			'type' => 'text',
			'subtype' => 'line',

		];
		$columns['what'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('How to do'),
			'type' => 'text',
			'subtype' => $this->textRichColumnTypeName,

		];
		$columns['how'] = $this->createColumn($table->id, $params);

		$params = [
			'title' => $this->l->t('Ease of use'),
			'type' => 'number',
			'subtype' => 'stars',

		];
		$columns['ease'] = $this->createColumn($table->id, $params);

		$params = [
			// TRANSLATORS This is an example title for a column to show if an action was done
			'title' => $this->l->t('Done'),
			'type' => 'selection',
			'subtype' => 'check',

		];
		$columns['done'] = $this->createColumn($table->id, $params);


		// let's add some example rows
		$this->createRow($table, [
			// TRANSLATORS This is an example name
			$columns['what']->getId() => $this->l->t('Open the tables app'),
			// TRANSLATORS This is an example account manager
			$columns['how']->getId() => 'Click on tables icon in the menu bar.',
			$columns['ease']->getId() => 5,
			$columns['done']->getId() => 'true',
		]);
		$this->createRow($table, [
			// TRANSLATORS This is an example name
			$columns['what']->getId() => $this->l->t('Add your first row'),
			// TRANSLATORS This is an example account manager
			$columns['how']->getId() => 'Just click on "new row" and enter some data inside of the form. At the end click on the bottom "save".',
			$columns['ease']->getId() => 5,
			$columns['done']->getId() => 'false',
		]);
		$this->createRow($table, [
			// TRANSLATORS This is an example name
			$columns['what']->getId() => $this->l->t('Edit a row'),
			// TRANSLATORS This is an example account manager
			$columns['how']->getId() => 'Hover the mouse over a row you want to edit. Click on the pen on the right side. Maybe you want to add a "done" status to this row.',
			$columns['ease']->getId() => 5,
			$columns['done']->getId() => 'false',
		]);
		$this->createRow($table, [
			// TRANSLATORS This is an example name
			$columns['what']->getId() => $this->l->t('Add a new column'),
			// TRANSLATORS This is an example account manager
			$columns['how']->getId() => 'You can add, remove and adjust columns as you need it. Click on the three-dot-menu on the upper right of this table and choose "create column". Fill in the data you want, at least a title and column type.',
			$columns['ease']->getId() => 4,
			$columns['done']->getId() => 'false',
		]);
		$this->createRow($table, [
			// TRANSLATORS This is an example name
			$columns['what']->getId() => $this->l->t('Read the docs'),
			// TRANSLATORS This is an example account manager
			$columns['how']->getId() => 'If you want to go through the documentation, this can be found here: https://github.com/nextcloud/tables/wiki',
			$columns['ease']->getId() => 3,
			$columns['done']->getId() => 'false',
		]);
	}

	/**
	 * @param int $tableId
	 * @param (mixed)[] $parameters
	 * @return Column
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws InternalError
	 * @throws MultipleObjectsReturnedException
	 * @throws PermissionError
	 */
	private function createColumn(int $tableId, array $parameters): ?Column {
		if ($this->userId === null) {
			return null;
		}

		return $this->columnService->create(

			// userId
			$this->userId,

			// tableId
			$tableId,

			// viewId
			null,

			// column type
			(isset($parameters['type'])) ? $parameters['type'] : 'text',

			// column subtype
			(isset($parameters['subtype'])) ? $parameters['subtype'] : '',

			// title
			(isset($parameters['title']) && $parameters['title'] != '') ? $parameters['title'] : $this->l->t('No title given'),

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

			// numberPrefix
			(isset($parameters['numberPrefix'])) ? $parameters['numberPrefix'] : '',

			// numberSuffix
			(isset($parameters['numberSuffix'])) ? $parameters['numberSuffix'] : '',

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

			// datetimeDefault
			(isset($parameters['datetimeDefault'])) ? $parameters['datetimeDefault'] : '',

			// usergroupDefault
			(isset($parameters['usergroupDefault'])) ? $parameters['usergroupDefault'] : '',

			// usergroupMultipleItems
			(isset($parameters['usergroupMultipleItems'])) ? $parameters['usergroupMultipleItems'] : null,

			// usergroupSelectUsers
			(isset($parameters['usergroupSelectUsers'])) ? $parameters['usergroupSelectUsers'] : null,

			// usergroupSelectGroups
			(isset($parameters['usergroupSelectGroups'])) ? $parameters['usergroupSelectGroups'] : null,

			// showUserStatus
			(isset($parameters['showUserStatus'])) ? $parameters['showUserStatus'] : null,

			// additional view ids
			[]
		);
	}

	/**
	 * @throws NotFoundError
	 * @throws Exception
	 */
	private function createRow(Table $table, array $values): void {
		$data = [];
		foreach ($values as $columnId => $value) {
			$data[] = [
				'columnId' => (int) $columnId,
				'value' => $value
			];
		}
		try {
			$this->rowService->create($table->getId(), null, $data);
		} catch (PermissionError $e) {
			$this->logger->warning('Cannot create row, permission denied: '.$e->getMessage());
		} catch (InternalError $e) {
			$this->logger->warning('Exception occurred while creating a row: '.$e->getMessage());
		} catch (NotFoundError $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new NotFoundError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
		}
	}

	/**
	 * @param Table $table
	 * @param array $data
	 * @return void
	 * @throws InternalError
	 */
	private function createView(Table $table, array $data): void {
		try {
			$view = $this->viewService->create($data['title'], $data['emoji'], $table);
			$this->viewService->update($view->getId(), $data);
		} catch (PermissionError $e) {
			$this->logger->warning('Cannot create view, permission denied: '.$e->getMessage());
		}
	}
}
