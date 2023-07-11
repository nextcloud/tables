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
	 * @throws InternalError
	 * @throws PermissionError
	 */
	public function makeTemplate(Table $table, string $template, int $baseViewId): Table {
		$createColumn = function ($params) use ($table, $baseViewId) {return $this->createColumn($table->getId(), $params, $baseViewId);};
		$createRow = function ($data) use ($table, $baseViewId) {$this->createRow($table, $baseViewId, $data);};
		if ($template === 'todo') {
			$this->makeTodo($table, $createColumn, $createRow);
		} elseif ($template === 'members') {
			$this->makeMembers($table, $createColumn, $createRow);
		} elseif ($template === 'weight') {
			$this->makeWeight($table, $createColumn, $createRow);
		} elseif ($template === 'vacation-requests') {
			$this->makeVacationRequests($table, $createColumn, $createRow);
		} elseif ($template === 'customers') {
			$this->makeCustomers($table, $createColumn, $createRow);
		} elseif ($template === 'tutorial') {
			$this->makeStartupTable($table, $createColumn, $createRow);
		}
		return $table;
	}

	/**
	 * @psalm-suppress PossiblyNullReference
	 * @param Table $table
	 * @throws InternalError
	 * @throws PermissionError
	 */
	private function makeWeight($createColumn, $createRow):void {
		$columns = [];

		$params = [
			'title' => $this->l->t('Date'),
			'type' => 'datetime',
			'subtype' => 'date',
			'mandatory' => true,
			'datetimeDefault' => 'today',
			'orderWeight' => 50,
		];
		$columns['date'] = $createColumn($params);

		$params = [
			'title' => $this->l->t('Weight'),
			'type' => 'number',
			'numberSuffix' => 'kg',
			'numberMin' => 0,
			'numberMax' => 200,
			'orderWeight' => 40,
		];
		$columns['weight'] = $createColumn($params);

		$params = [
			'title' => $this->l->t('Body fat'),
			'type' => 'number',
			'numberMin' => 0,
			'numberMax' => 100,
			'numberSuffix' => '%',
			'orderWeight' => 30,
		];
		$columns['bodyFat'] = $createColumn($params);

		$params = [
			'title' => $this->l->t('Feeling over all'),
			'type' => 'number',
			'subtype' => 'stars',
			'orderWeight' => 20,
		];
		$columns['feeling'] = $createColumn($params);

		$params = [
			'title' => $this->l->t('Comments'),
			'type' => 'text',
			'subtype' => 'long',
			'orderWeight' => 10,
		];
		$columns['comment'] = $createColumn($params);

		// let's add some example rows
		$createRow([
			$columns['date']->getId() => '2022-03-01',
			$columns['weight']->getId() => 92.5,
			$columns['bodyFat']->getId() => 30,
			$columns['feeling']->getId() => 4,
			$columns['comment']->getId() => '',
		]);
		$createRow([
			$columns['date']->getId() => '2022-03-02',
			$columns['weight']->getId() => 92.7,
			$columns['bodyFat']->getId() => 30.3,
			$columns['feeling']->getId() => 3,
			$columns['comment']->getId() => $this->l->t('feel sick'),
		]);
		$createRow([
			$columns['date']->getId() => '2022-03-10',
			$columns['weight']->getId() => 91,
			$columns['bodyFat']->getId() => 33.1,
			$columns['feeling']->getId() => 4,
			$columns['comment']->getId() => '',
		]);
		$createRow([
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
	 * @throws InternalError
	 * @throws PermissionError
	 */
	private function makeCustomers($createColumn, $createRow):void {
		$columns = [];

		$params = [
			'title' => $this->l->t('Name'),
			'type' => 'text',
			'subtype' => 'line',
			'orderWeight' => 100,
		];
		$columns['name'] = $createColumn($params);

		$params = [
			'title' => $this->l->t('Account manager'),
			'type' => 'text',
			'subtype' => 'line',
			'orderWeight' => 90,
		];
		$columns['accountManager'] = $createColumn($params);

		$params = [
			'title' => $this->l->t('Contract type'),
			'type' => 'text',
			'subtype' => 'line',
			'orderWeight' => 80,
		];
		$columns['contractType'] = $createColumn($params);

		$params = [
			'title' => $this->l->t('Contract start'),
			'type' => 'datetime',
			'subtype' => 'date',
			'datetimeDefault' => 'today',
			'orderWeight' => 70,
		];
		$columns['contractStart'] = $createColumn($params);

		$params = [
			'title' => $this->l->t('Contract end'),
			'type' => 'datetime',
			'subtype' => 'date',
			'orderWeight' => 60,
		];
		$columns['contractEnd'] = $createColumn($params);

		$params = [
			'title' => $this->l->t('Description'),
			'type' => 'text',
			'subtype' => 'long',
			'orderWeight' => 50,
		];
		$columns['description'] = $createColumn($params);

		$params = [
			'title' => $this->l->t('Contact information'),
			'type' => 'text',
			'subtype' => 'long',
			'orderWeight' => 40,
		];
		$columns['contactInformation'] = $createColumn($params);

		$params = [
			'title' => $this->l->t('Quality of relationship'),
			'type' => 'number',
			'subtype' => 'progress',
			'orderWeight' => 30,
			'numberDefault' => 30,
		];
		$columns['qualityRelationship'] = $createColumn($params);

		$params = [
			'title' => $this->l->t('Comment'),
			'type' => 'text',
			'subtype' => 'long',
			'orderWeight' => 20,
		];
		$columns['comment'] = $createColumn($params);

		// let's add some example rows
		$createRow([
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
		$createRow([
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
		$createRow([
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
	 * @throws InternalError
	 * @throws PermissionError
	 */
	private function makeVacationRequests($createColumn, $createRow):void {
		$columns = [];

		$params = [
			'title' => $this->l->t('Employee name'),
			'type' => 'text',
			'subtype' => 'line',
			'mandatory' => true,
			'orderWeight' => 50,
		];
		$columns['employee'] = $createColumn($params);

		$params = [
			'title' => $this->l->t('from'),
			'description' => $this->l->t('When is your vacation starting?'),
			'type' => 'datetime',
			'subtype' => 'date',
			'orderWeight' => 41,
		];
		$columns['from'] = $createColumn($params);

		$params = [
			'title' => $this->l->t('to'),
			'description' => $this->l->t('When is your vacation ending?'),
			'type' => 'datetime',
			'subtype' => 'date',
			'orderWeight' => 40,
		];
		$columns['to'] = $createColumn($params);

		$params = [
			'title' => $this->l->t('Number of working days'),
			'description' => $this->l->t('How many working days are included?'),
			'type' => 'number',
			'numberMin' => 0,
			'numberMax' => 100,
			'orderWeight' => 30,
		];
		$columns['workingDays'] = $createColumn($params);

		$params = [
			'title' => $this->l->t('Request date'),
			'type' => 'datetime',
			'subtype' => 'date',
			'orderWeight' => 20,
		];
		$columns['dateRequest'] = $createColumn($params);

		$params = [
			'title' => $this->l->t('Approved'),
			'type' => 'selection',
			'subtype' => 'check',
			'orderWeight' => 25,
		];
		$columns['approved'] = $createColumn($params);

		$params = [
			'title' => $this->l->t('Approve date'),
			'type' => 'datetime',
			'subtype' => 'date',
			'orderWeight' => 24,
		];
		$columns['dateApprove'] = $createColumn($params);

		$params = [
			'title' => $this->l->t('Approved by'),
			'type' => 'text',
			'subtype' => 'line',
			'orderWeight' => 23,
		];
		$columns['approveBy'] = $createColumn($params);


		$params = [
			'title' => $this->l->t('Comments'),
			'type' => 'text',
			'subtype' => 'long',
			'orderWeight' => 10,
		];
		$columns['comment'] = $createColumn($params);

		// let's add some example rows
		$createRow([
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
		$createRow([
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
		$createRow([
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
	}

	/**
	 * @psalm-suppress PossiblyNullReference
	 * @param Table $table
	 * @throws InternalError
	 * @throws PermissionError
	 */
	private function makeMembers($createColumn, $createRow):void {
		$columns = [];

		$params = [
			'title' => $this->l->t('Name'),
			'type' => 'text',
			'subtype' => 'line',
			'mandatory' => true,
			'orderWeight' => 50,
		];
		$columns['name'] = $createColumn($params);

		$params = [
			'title' => $this->l->t('Position'),
			'type' => 'text',
			'subtype' => 'line',
			'orderWeight' => 40,
		];
		$columns['position'] = $createColumn($params);

		$params = [
			'title' => $this->l->t('Skills'),
			'type' => 'text',
			'subtype' => 'long',
			'orderWeight' => 30,
		];
		$columns['skills'] = $createColumn($params);

		$params = [
			'title' => $this->l->t('Birthday'),
			'type' => 'datetime',
			'subtype' => 'date',
			'orderWeight' => 20,
		];
		$columns['birthday'] = $createColumn($params);

		$params = [
			'title' => $this->l->t('Comments'),
			'type' => 'text',
			'subtype' => 'long',
			'orderWeight' => 10,
		];
		$columns['comment'] = $createColumn($params);

		// let's add some example rows
		$createRow([
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
	 * @throws InternalError
	 * @throws PermissionError
	 */
	private function makeTodo($createColumn, $createRow): void {
		$columns = [];

		$params = [
			'title' => $this->l->t('Task'),
			'type' => 'text',
			'subtype' => 'line',
			'mandatory' => true,
			'orderWeight' => 50,
		];
		$columns['task'] = $createColumn($params);

		$params = [
			'title' => $this->l->t('Description'),
			'type' => 'text',
			'subtype' => 'long',
			'description' => $this->l->t('Title or short description'),
			'textMultiline' => true,
			'orderWeight' => 40,
		];
		$columns['description'] = $createColumn($params);

		$params = [
			'title' => $this->l->t('Target'),
			'type' => 'text',
			'subtype' => 'long',
			'description' => $this->l->t('Date, time or whatever'),
			'orderWeight' => 30,
		];
		$columns['target'] = $createColumn($params);

		$params = [
			'title' => $this->l->t('Progress'),
			'type' => 'number',
			'subtype' => 'progress',
			'orderWeight' => 20,
			'numberDefault' => 0,
		];
		$columns['progress'] = $createColumn($params);

		$params = [
			'title' => $this->l->t('Comments'),
			'type' => 'text',
			'subtype' => 'long',
			'orderWeight' => 10,
		];
		$columns['comments'] = $createColumn($params);

		$params = [
			'title' => $this->l->t('Proofed'),
			'type' => 'selection',
			'subtype' => 'check',
			'orderWeight' => 5,
		];
		$columns['proofed'] = $createColumn($params);

		// let's add some example rows
		$createRow([
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
		$createRow([
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
		$createRow([
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
		$createRow([
			// TRANSLATORS This is an example for a task
			$columns['task']->getId() => $this->l->t('Add more actions'),
			// TRANSLATORS This is an example description
			$columns['description']->getId() => $this->l->t('I guess we need more actions in here...'),
		]);
	}


	/**
	 * @psalm-suppress PossiblyNullReference
	 * @param Table $table
	 * @throws InternalError
	 * @throws PermissionError
	 */
	private function makeStartupTable($createColumn, $createRow):void {
		$columns = [];

		$params = [
			// TRANSLATORS This is the title of the first column for a list of actions
			'title' => $this->l->t('What'),
			'type' => 'text',
			'subtype' => 'line',
			'orderWeight' => 10,
		];
		$columns['what'] = $createColumn($params);

		$params = [
			'title' => $this->l->t('How to do'),
			'type' => 'text',
			'subtype' => 'long',
			'orderWeight' => 10,
		];
		$columns['how'] = $createColumn($params);

		$params = [
			'title' => $this->l->t('Ease of use'),
			'type' => 'number',
			'subtype' => 'stars',
			'orderWeight' => 10,
		];
		$columns['ease'] = $createColumn($params);

		$params = [
			// TRANSLATORS This is an example title for a column to show if an action was done
			'title' => $this->l->t('Done'),
			'type' => 'selection',
			'subtype' => 'check',
			'orderWeight' => 10,
		];
		$columns['done'] = $createColumn($params);


		// let's add some example rows
		$createRow([
			// TRANSLATORS This is an example name
			$columns['what']->getId() => $this->l->t('Open the tables app'),
			// TRANSLATORS This is an example account manager
			$columns['how']->getId() => 'Click on tables icon in the menu bar.',
			$columns['ease']->getId() => 5,
			$columns['done']->getId() => 'true',
		]);
		$createRow([
			// TRANSLATORS This is an example name
			$columns['what']->getId() => $this->l->t('Add your first row'),
			// TRANSLATORS This is an example account manager
			$columns['how']->getId() => 'Just click on "new row" and enter some data inside of the form. At the end click on the bottom "save".',
			$columns['ease']->getId() => 5,
			$columns['done']->getId() => 'false',
		]);
		$createRow([
			// TRANSLATORS This is an example name
			$columns['what']->getId() => $this->l->t('Edit a row'),
			// TRANSLATORS This is an example account manager
			$columns['how']->getId() => 'Hover the mouse over a row you want to edit. Click on the pen on the right side. Maybe you want to add a "done" status to this row.',
			$columns['ease']->getId() => 5,
			$columns['done']->getId() => 'false',
		]);
		$createRow([
			// TRANSLATORS This is an example name
			$columns['what']->getId() => $this->l->t('Add a new column'),
			// TRANSLATORS This is an example account manager
			$columns['how']->getId() => 'You can add, remove and adjust columns as you need it. Click on the three-dot-menu on the upper right of this table and choose "create column". Fill in the data you want, at least a title and column type.',
			$columns['ease']->getId() => 4,
			$columns['done']->getId() => 'false',
		]);
		$createRow([
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
	 * @throws InternalError
	 * @throws PermissionError
	 */
	private function createColumn(int $tableId, array $parameters, int $baseViewId): ?Column {
		if ($this->userId === null) {
			return null;
		}

		return $this->columnService->create(

			// userId
			$this->userId,

			// tableId
			$tableId,

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

			// orderWeight
			(isset($parameters['orderWeight'])) ? $parameters['orderWeight'] : 0,

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

			// baseViewId
			$baseViewId,

			// additional view ids
			[]
		);
	}

	private function createRow(Table $table, int $viewId, array $values): void {
		$data = [];
		foreach ($values as $columnId => $value) {
			$data[] = [
				'columnId' => $columnId,
				'value' => $value
			];
		}
		try {
			$this->rowService->createComplete($viewId, $table->getId(), $data);
		} catch (PermissionError|Exception $e) {
		}
	}
}
