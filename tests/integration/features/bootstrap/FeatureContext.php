<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

require __DIR__ . '/../../vendor/autoload.php';

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use Behat\Step\Given;
use Behat\Step\When;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\ExpectationFailedException;
use Psr\Http\Message\ResponseInterface;

/**
 * Defines application features from the specific context.
 */
class FeatureContext implements Context {
	public const TEST_PASSWORD = '123456';
	public const NON_EXISTING_CONTEXT_ALIAS = 'NON-EXISTENT';
	public const NON_EXISTING_CONTEXT_ID = 99404;

	/** @var string */
	protected $currentUser;

	/** @var ResponseInterface */
	private $response;

	/** @var CookieJar[] */
	private $cookieJars;

	/** @var string */
	protected $baseUrl;

	/** @var array */
	protected $createdUsers = [];

	/** @var array */
	protected $createdGroups = [];

	private ?int $shareId = null;
	private ?int $tableId = null;
	private ?int $columnId = null;
	private ?int $rowId = null;
	private ?array $importResult = null;
	private ?array $activeNode = null;

	// we need some ids to reuse it in some contexts,
	// but we can not return them and reuse it in the scenarios,
	// that's why we hold a kind of register here
	// structure: $name -> item-id
	// example for a table: 'test-table' -> 5
	private array $tableIds = [];
	private array $viewIds = [];
	private array $columnIds = [];

	// Store data from last request to perform assertions, id is used as a key
	private array $tableData = [];

	private $importColumnData = null;

	// use CommandLineTrait;
	private CollectionManager $collectionManager;

	/**
	 * FeatureContext constructor.
	 */
	public function __construct() {
		$this->cookieJars = [];
		$this->baseUrl = getenv('TEST_SERVER_URL');
		$this->collectionManager = new CollectionManager();
	}

	/**
	 * @BeforeScenario
	 */
	public function setUp() {
		$this->createdUsers = [];
		$this->createdGroups = [];
		$this->activeNode = null;
	}

	/**
	 * @AfterScenario
	 */
	public function cleanupUsers() {
		$this->importColumnData = null;
		$this->collectionManager->cleanUp();
		foreach ($this->createdUsers as $user) {
			$this->deleteUser($user);
		}
		foreach ($this->createdGroups as $group) {
			$this->deleteGroup($group);
		}
	}

	/**
	 * @Given table :table with emoji :emoji exists for user :user as :tableName via v2
	 *
	 * @param string $user
	 * @param string $title
	 * @param string $tableName
	 * @param string|null $emoji
	 * @throws Exception
	 */
	public function createTableV2(string $user, string $title, string $tableName, ?string $emoji = null): void {
		$this->setCurrentUser($user);
		$this->sendOcsRequest('post', '/apps/tables/api/2/tables',
			[
				'title' => $title,
				'emoji' => $emoji
			]
		);

		$newTable = $this->getDataFromResponse($this->response)['ocs']['data'];
		$this->tableIds[$tableName] = $newTable['id'];
		$this->collectionManager->register($newTable, 'table', $newTable['id'], $tableName, function () use ($user, $tableName) {
			$this->deleteTableV2($user, $tableName);
		});

		Assert::assertEquals(200, $this->response->getStatusCode());
		Assert::assertEquals($newTable['title'], $title);
		Assert::assertEquals($newTable['emoji'], $emoji);
		Assert::assertEquals($newTable['ownership'], $user);

		$tableToVerify = $this->userFetchesTableInfo($user, $tableName);
		Assert::assertEquals(200, $this->response->getStatusCode());
		Assert::assertEquals($tableToVerify['title'], $title);
		Assert::assertEquals($tableToVerify['emoji'], $emoji);
		Assert::assertEquals($tableToVerify['ownership'], $user);
	}

	/**
	 * @Given user :user fetches table info for table :tableName
	 */
	public function userFetchesTableInfo($user, $tableName) {
		$this->setCurrentUser($user);
		$tableId = $this->tableIds[$tableName];

		$this->sendOcsRequest(
			'GET',
			'/apps/tables/api/2/tables/' . $tableId,
		);

		$tableToVerify = $this->getDataFromResponse($this->response)['ocs']['data'];
		$this->tableData[$tableName] = $tableToVerify;
		$this->tableId = $tableToVerify['id'];

		return $tableToVerify;
	}

	/**
	 * @Then user :user has the following tables via v2
	 *
	 * @param string $user
	 * @param TableNode|null $body
	 * @throws Exception
	 */
	public function userTablesV2(string $user, ?TableNode $body = null): void {
		$this->setCurrentUser($user);
		$this->sendOcsRequest(
			'GET',
			'/apps/tables/api/2/tables'
		);

		$data = $this->getDataFromResponse($this->response)['ocs']['data'];
		Assert::assertEquals(200, $this->response->getStatusCode());

		// check if tables are empty
		if ($body === null) {
			Assert::assertCount(0, $data);
			return;
		}

		// check if given tables exists
		$titles = [];
		foreach ($data as $d) {
			$titles[] = $d['title'];
		}
		foreach ($body->getRows()[0] as $tableTitle) {
			Assert::assertTrue(in_array($tableTitle, $titles, true));
		}
	}

	/**
	 * @Then user :user has the following resources via v2
	 *
	 * first row contains tables, second views
	 * | first table 		| second table 			|
	 * | first shared view 	| second shared view 	|
	 *
	 * @param string $user
	 * @param TableNode|null $body
	 * @throws Exception
	 */
	public function initialResourcesV2(string $user, ?TableNode $body = null): void {
		$this->setCurrentUser($user);
		$this->sendOcsRequest(
			'GET',
			'/apps/tables/api/2/init'
		);

		$data = $this->getDataFromResponse($this->response)['ocs']['data'];
		Assert::assertEquals(200, $this->response->getStatusCode());

		// check if table is empty
		if ($body === null) {
			Assert::assertCount(0, $data);
			return;
		}

		// check if given tables exists
		$tableTitles = [];
		foreach ($data['tables'] as $d) {
			$tableTitles[] = $d['title'];
		}
		$viewTitles = [];
		foreach ($data['views'] as $d) {
			$tableTitles[] = $d['title'];
		}

		if (@$body->getRows()[0]) {
			foreach ($body->getRows()[0] as $tableTitle) {
				Assert::assertTrue(in_array($tableTitle, $tableTitles, true));
			}
		}
		if (@$body->getRows()[1]) {
			foreach ($body->getRows()[1] as $viewTitle) {
				Assert::assertTrue(in_array($viewTitle, $viewTitles, true));
			}
		}
	}

	/**
	 * @Then user :user updates table :tableName set title :title and emoji :emoji via v2
	 * @Then user :user updates table :tableName set archived :archived via v2
	 *
	 * @param string $user
	 * @param string $title
	 * @param string|null $emoji
	 * @param string $tableName
	 * @throws Exception
	 */
	public function updateTableV2(string $user, string $tableName, ?string $title = null, ?string $emoji = null, ?bool $archived = null): void {
		$this->setCurrentUser($user);

		$this->sendOcsRequest(
			'GET',
			'/apps/tables/api/2/tables/' . $this->tableIds[$tableName],
		);

		$previousData = $this->getDataFromResponse($this->response)['ocs']['data'];

		$data = [];
		if ($title !== null) {
			$data['title'] = $title;
		}
		if ($emoji !== null) {
			$data['emoji'] = $emoji;
		}
		if ($archived !== null) {
			$data['archived'] = $archived;
		}

		$this->sendOcsRequest(
			'PUT',
			'/apps/tables/api/2/tables/' . $this->tableIds[$tableName],
			$data
		);

		$updatedTable = $this->getDataFromResponse($this->response)['ocs']['data'];

		Assert::assertEquals(200, $this->response->getStatusCode());
		Assert::assertEquals($updatedTable['title'], $title ?? $previousData['title']);
		Assert::assertEquals($updatedTable['emoji'], $emoji ?? $previousData['emoji']);
		Assert::assertEquals($updatedTable['ownership'], $user ?? $previousData['ownership']);
		Assert::assertEquals($updatedTable['archived'], $archived ?? $previousData['archived']);

		$this->sendOcsRequest(
			'GET',
			'/apps/tables/api/2/tables/' . $updatedTable['id'],
		);

		$tableToVerify = $this->getDataFromResponse($this->response)['ocs']['data'];
		Assert::assertEquals(200, $this->response->getStatusCode());
		Assert::assertEquals($tableToVerify['title'], $title ?? $previousData['title']);
		Assert::assertEquals($tableToVerify['emoji'], $emoji ?? $previousData['emoji']);
		Assert::assertEquals($tableToVerify['ownership'], $user ?? $previousData['ownership']);
		Assert::assertEquals($tableToVerify['archived'], $archived ?? $previousData['archived']);

		$this->tableData[$tableName] = $tableToVerify;
	}

	/**
	 * @Then change owner for table :tableName from user :user to user :newUser
	 *
	 * @param string $user
	 * @param string $newUser
	 * @param string $tableName
	 */
	public function transferTableV2(string $user, string $newUser, string $tableName): void {
		$this->setCurrentUser($user);

		$data = ['newOwnerUserId' => $newUser];

		$this->sendOcsRequest(
			'PUT',
			'/apps/tables/api/2/tables/' . $this->tableIds[$tableName] . '/transfer',
			$data
		);

		$updatedTable = $this->getDataFromResponse($this->response)['ocs']['data'];

		Assert::assertEquals(200, $this->response->getStatusCode());
		Assert::assertEquals($updatedTable['ownership'], $newUser);
		$this->collectionManager->update($updatedTable, 'table', $updatedTable['id'], function () use ($newUser, $tableName): void {
			$this->deleteTableV2($newUser, $tableName);
		});
	}

	/**
	 * @Then table :tableName is owned by :user
	 *
	 * @param string $user
	 * @param string $tableName
	 * @throws Exception
	 */
	public function checkTableOwnershipV2(string $user, string $tableName): void {
		$this->setCurrentUser($user);

		$this->sendOcsRequest(
			'GET',
			'/apps/tables/api/2/tables/' . $this->tableIds[$tableName]
		);

		$table = $this->getDataFromResponse($this->response)['ocs']['data'];

		Assert::assertEquals(200, $this->response->getStatusCode());
		Assert::assertEquals($table['ownership'], $user);
	}

	/**
	 * @Then user :user deletes table :tableName via v2
	 *
	 * @param string $user
	 * @param string $tableName
	 * @throws Exception
	 */
	public function deleteTableV2(string $user, string $tableName): void {
		$this->setCurrentUser($user);

		$this->sendOcsRequest(
			'DELETE',
			'/apps/tables/api/2/tables/' . $this->tableIds[$tableName]
		);

		$deletedTable = $this->getDataFromResponse($this->response)['ocs']['data'];
		Assert::assertEquals(200, $this->response->getStatusCode());
		Assert::assertEquals($deletedTable['id'], $this->tableIds[$tableName]);

		$this->sendOcsRequest(
			'GET',
			'/apps/tables/api/2/tables/' . $deletedTable['id'],
		);
		Assert::assertEquals(404, $this->response->getStatusCode());

		unset($this->tableIds[$tableName]);
		if ($table = $this->collectionManager->getByAlias('table', $tableName)) {
			$this->collectionManager->forget('table', $table['id']);
		}
	}

	/**
	 * @Then column from main type :columnType for node type :nodeType and node name :nodeName exists with name :columnName and following properties via v2
	 *
	 * @param string $nodeType
	 * @param string $nodeName
	 * @param string $columnType
	 * @param string $columnName
	 * @param TableNode|null $properties
	 */
	public function createColumnV2(string $nodeType, string $nodeName, string $columnType, string $columnName, ?TableNode $properties = null): void {
		$props = [
			'baseNodeType' => $nodeType,
		];
		if ($nodeType === 'table') {
			$props['baseNodeId'] = $this->tableIds[$nodeName];
		}
		if ($nodeType === 'view') {
			$props['baseNodeId'] = $this->viewIds[$nodeName];
		}
		$title = null;
		foreach ($properties->getRows() as $row) {
			if ($row[0] === 'title') {
				$title = $row[1];
			}
			$props[$row[0]] = $row[1];
		}

		$this->sendOcsRequest(
			'POST',
			'/apps/tables/api/2/columns/' . $columnType,
			$props
		);

		$newColumn = $this->getDataFromResponse($this->response)['ocs']['data'];
		$this->columnIds[$columnName] = $newColumn['id'];
		$this->columnId = $newColumn['id'];

		Assert::assertEquals(200, $this->response->getStatusCode());

		$this->sendOcsRequest(
			'GET',
			'/apps/tables/api/2/columns/' . $newColumn['id'],
		);

		$columnToVerify = $this->getDataFromResponse($this->response)['ocs']['data'];
		Assert::assertEquals(200, $this->response->getStatusCode());
		Assert::assertEquals($columnToVerify['title'], $title);
	}

	/**
	 * @Then node with node type :nodeType and node name :nodeName has the following columns via v2
	 *
	 * @param string $nodeType
	 * @param string $nodeName
	 * @param TableNode|null $body
	 */
	public function columnsForNodeV2(string $nodeType, string $nodeName, ?TableNode $body = null): void {
		$nodeId = null;
		if ($nodeType === 'table') {
			$nodeId = $this->tableIds[$nodeName];
		}
		if ($nodeType === 'view') {
			$nodeId = $this->viewIds[$nodeName];
		}

		$this->sendOcsRequest(
			'GET',
			'/apps/tables/api/2/columns/' . $nodeType . '/' . $nodeId
		);

		$data = $this->getDataFromResponse($this->response)['ocs']['data'];
		Assert::assertEquals(200, $this->response->getStatusCode());

		// check if tables are empty
		if ($body === null) {
			Assert::assertCount(0, $data);
			return;
		}

		// check if given tables exists
		$titles = [];
		foreach ($data as $d) {
			$titles[] = $d['title'];
		}
		foreach ($body->getRows()[0] as $tableTitle) {
			Assert::assertTrue(in_array($tableTitle, $titles, true));
		}
	}

	// (((((((((((((((((((((((((((( END API v2 )))))))))))))))))))))))))))))))))))


	/**
	 * @Given user :user uploads file :file
	 */
	public function uploadFile(string $user, string $file): void {
		$this->setCurrentUser($user);

		$localFilePath = __DIR__ . '/../../resources/' . $file;

		$url = sprintf('%sremote.php/dav/files/%s/%s', $this->baseUrl, $user, $file);
		$body = Utils::streamFor(fopen($localFilePath, 'rb'));

		$this->sendRequestFullUrl('PUT', $url, $body);

		Assert::assertEquals(201, $this->response->getStatusCode());
	}

	// IMPORT --------------------------

	/**
	 * @Given file :file exists for user :user with the following data
	 *
	 * @param string $user
	 * @param string $file
	 * @param TableNode $table
	 */
	public function createCsvFile(string $user, string $file, TableNode $table): void {
		$this->setCurrentUser($user);

		$url = sprintf('%sremote.php/dav/files/%s/%s', $this->baseUrl, $user, $file);
		$body = Utils::streamFor($this->tableNodeToCsv($table));

		$this->sendRequestFullUrl('PUT', $url, $body);

		Assert::assertEquals(201, $this->response->getStatusCode());
	}

	/**
	 * @param TableNode $node
	 * @return false|resource
	 */
	private function tableNodeToCsv(TableNode $node) {
		$resource = fopen('php://temp', 'rb+');
		foreach ($node->getRows() as $row) {
			$fields = array_map(function ($cell) {
				return str_replace('{rowId}', $this->rowId, $cell);
			}, $row);
			fputcsv($resource, $fields);
		}

		rewind($resource);

		return $resource;
	}

	/**
	 * @When user imports file :file into last created table
	 *
	 * @param string $file
	 */
	public function importTable(string $file): void {
		$this->sendRequest(
			'POST',
			'/apps/tables/api/1/import/table/' . $this->tableId,
			[
				'path' => $file,
				'createMissingColumns' => true,
			]
		);

		$this->importResult = $this->getDataFromResponse($this->response);
		Assert::assertEquals(200, $this->response->getStatusCode());
	}

	/**
	 * @Then import results have the following data
	 *
	 * @param TableNode $table
	 */
	public function checkImportResults(TableNode $table): void {
		foreach ($table->getRows() as $item) {
			Assert::assertEquals($item[1], $this->importResult[$item[0]]);
		}
	}

	/**
	 * @Then print register
	 *
	 */
	public function printRegister(): void {
		if (getenv('DEBUG') === '') {
			return;
		}

		echo "REGISTER ========================\n";
		echo "Tables --------------------\n";
		print_r($this->tableIds);
		echo "Views --------------------\n";
		print_r($this->viewIds);
		echo "Columns --------------------\n";
		print_r($this->columnIds);
	}

	/**
	 * @Then table contains at least following rows
	 *
	 * @param TableNode $table
	 */
	public function checkRowsExists(TableNode $table): void {
		$this->sendRequest(
			'GET',
			'/apps/tables/api/1/tables/' . $this->tableId . '/rows/simple',
		);

		$allRows = $this->getDataFromResponse($this->response);
		Assert::assertEquals(200, $this->response->getStatusCode());

		$tableRows = $table->getRows();
		foreach ($tableRows[0] as $key => $colTitle) {
			$indexForCol = array_search($colTitle, $allRows[0]);
			$allValuesForColumn = [];
			foreach ($allRows as $row) {
				$allValuesForColumn[] = $row[$indexForCol];
			}
			foreach ($table->getColumn($key) as $item) {
				Assert::assertTrue(in_array($item, $allValuesForColumn), sprintf('%s not in %s', $item, implode(', ', $allValuesForColumn)));
			}
		}
	}

	/**
	 * @Then user :user has the following tables
	 *
	 * @param string $user
	 * @param TableNode|null $body
	 */
	public function userTables(string $user, ?TableNode $body = null): void {
		$this->setCurrentUser($user);
		$this->sendRequest(
			'GET',
			'/apps/tables/api/1/tables'
		);

		$data = $this->getDataFromResponse($this->response);

		Assert::assertEquals(200, $this->response->getStatusCode());

		// check if tables are empty
		if ($body === null) {
			Assert::assertCount(0, $data);
			return;
		}

		// we check if the given tables are available, not if they are equal
		// Assert::assertCount(count($body->getRows()[0]), $data, 'Tables count does not match');

		// check if given tables exists
		$titles = [];
		foreach ($data as $d) {
			$titles[] = $d['title'];
		}
		foreach ($body->getRows()[0] as $tableTitle) {
			$message = sprintf('"%s" not in the list: %s', $tableTitle, implode(', ', $titles));
			Assert::assertTrue(in_array($tableTitle, $titles, true), $message);
		}
	}

	/**
	 * @Then table :tableName has the following views for user :user
	 *
	 * @param string $tableName
	 * @param string $user
	 * @param TableNode|null $body
	 */
	public function tableViews(string $tableName, string $user, ?TableNode $body = null): void {
		$this->setCurrentUser($user);

		$this->sendRequest(
			'GET',
			'/apps/tables/api/1/tables/' . $this->tableIds[$tableName] . '/views'
		);

		$data = $this->getDataFromResponse($this->response);

		Assert::assertEquals(200, $this->response->getStatusCode());

		// check if views are empty
		if ($body === null) {
			Assert::assertCount(0, $data);
			return;
		}

		// check if given view exists
		$titles = [];
		foreach ($data as $d) {
			$titles[] = $d['title'];
		}
		foreach ($body->getRows()[0] as $viewTitle) {
			Assert::assertTrue(in_array($viewTitle, $titles, true));
		}
	}

	/**
	 * @Given user :user create view :title with emoji :emoji for :tableName as :viewName
	 *
	 * @param string $user
	 * @param string $title
	 * @param string $tableName
	 * @param string $viewName
	 * @param string|null $emoji
	 */
	public function createView(string $user, string $title, string $tableName, string $viewName, ?string $emoji = null): void {
		$this->setCurrentUser($user);
		$this->sendRequest(
			'POST',
			'/apps/tables/api/1/tables/' . $this->tableIds[$tableName] . '/views',
			[
				'title' => $title,
				'emoji' => $emoji
			]
		);

		$newItem = $this->getDataFromResponse($this->response);
		$this->viewIds[$viewName] = $newItem['id'];

		$this->collectionManager->register($newItem, 'view', $newItem['id'], $viewName, function () use ($user, $viewName) {
			$this->deleteViewWithoutAssertion($user, $viewName);
			unset($this->viewIds[$viewName]);
		});

		Assert::assertEquals(200, $this->response->getStatusCode());
		Assert::assertEquals($newItem['title'], $title);
		Assert::assertEquals($newItem['emoji'], $emoji);

		$this->sendRequest(
			'GET',
			'/apps/tables/api/1/views/' . $newItem['id'],
		);

		$itemToVerify = $this->getDataFromResponse($this->response);

		Assert::assertEquals(200, $this->response->getStatusCode());
		Assert::assertEquals($itemToVerify['title'], $title);
		Assert::assertEquals($itemToVerify['emoji'], $emoji);
	}

	/**
	 * @Given table :table with emoji :emoji exists for user :user as :tableName
	 *
	 * @param string $user
	 * @param string $title
	 * @param string $tableName
	 * @param string|null $emoji
	 */
	public function createTable(string $user, string $title, string $tableName, ?string $emoji = null): void {
		$this->setCurrentUser($user);
		$this->sendRequest(
			'POST',
			'/apps/tables/api/1/tables',
			[
				'title' => $title,
				'emoji' => $emoji
			]
		);

		$newTable = $this->getDataFromResponse($this->response);
		$this->tableId = $newTable['id'];
		$this->tableIds[$tableName] = $newTable['id'];
		$this->collectionManager->register($newTable, 'table', $newTable['id'], $tableName, function () use ($user, $tableName) {
			$this->deleteViewWithoutAssertion($user, $tableName);
			unset($this->tableIds[$tableName]);
		});

		Assert::assertEquals(200, $this->response->getStatusCode());
		Assert::assertEquals($newTable['title'], $title);
		Assert::assertEquals($newTable['emoji'], $emoji);
		Assert::assertEquals($newTable['ownership'], $user);

		$this->sendRequest(
			'GET',
			'/apps/tables/api/1/tables/' . $newTable['id'],
		);

		$tableToVerify = $this->getDataFromResponse($this->response);
		Assert::assertEquals(200, $this->response->getStatusCode());
		Assert::assertEquals($tableToVerify['title'], $title);
		Assert::assertEquals($tableToVerify['emoji'], $emoji);
		Assert::assertEquals($tableToVerify['ownership'], $user);
	}

	private function getTableByKeyword(string $keyword) {
		$this->sendRequest(
			'GET',
			'/apps/tables/api/1/tables?keyword=' . $keyword
		);

		$tables = $this->getDataFromResponse($this->response);
		return $tables[0];
	}

	private function getTableById(int $tableId): array {
		$this->sendRequest(
			'GET',
			'/apps/tables/api/1/tables/' . $tableId
		);

		return $this->getDataFromResponse($this->response);
	}

	private function getViewById(int $viewId): array {
		$this->sendRequest(
			'GET',
			'/apps/tables/api/1/views/' . $viewId
		);

		return $this->getDataFromResponse($this->response);
	}

	/**
	 * @Then user :user updates table with keyword :keyword set title :title and optional emoji :emoji
	 *
	 * @param string $user
	 * @param string $title
	 * @param string|null $emoji
	 * @param string $keyword
	 */
	public function updateTable(string $user, string $title, ?string $emoji, string $keyword): void {
		$this->setCurrentUser($user);
		$table = $this->getTableByKeyword($keyword);

		$data = ['title' => $title];
		if ($emoji !== null) {
			$data['emoji'] = $emoji;
		}

		$this->sendRequest(
			'PUT',
			'/apps/tables/api/1/tables/' . $table['id'],
			$data
		);

		$updatedTable = $this->getDataFromResponse($this->response);

		Assert::assertEquals(200, $this->response->getStatusCode());
		Assert::assertEquals($updatedTable['title'], $title);
		Assert::assertEquals($updatedTable['emoji'], $emoji);
		Assert::assertEquals($updatedTable['ownership'], $user);

		$this->sendRequest(
			'GET',
			'/apps/tables/api/1/tables/' . $updatedTable['id'],
		);

		$tableToVerify = $this->getDataFromResponse($this->response);
		Assert::assertEquals(200, $this->response->getStatusCode());
		Assert::assertEquals($tableToVerify['title'], $title);
		Assert::assertEquals($tableToVerify['emoji'], $emoji);
		Assert::assertEquals($tableToVerify['ownership'], $user);
	}

	protected function sendUpdateViewRequest(string $viewName, array $data): void {
		$viewId = $this->collectionManager->getByAlias('view', $viewName)['id'];
		$this->sendRequest(
			'PUT',
			sprintf('/apps/tables/api/1/views/%d', $viewId),
			[ 'data' => $data ]
		);
	}

	#[When('following sort order is applied to view :viewName:')]
	public function applySortToView(string $viewName, TableNode $sortOrder): void {
		$sortData = [];
		foreach ($sortOrder->getRows() as $row) {

			$columnId = match ($row[0]) {
				'meta-id' => -1,
				'meta-created-by' => -2,
				'meta-updated-by' => -3,
				'meta-created-at' => -4,
				'meta-updated-at' => -5,
				default => $this->collectionManager->getByAlias('column', $row[0])['id'],
			};

			$sortData[] = [
				'columnId' => $columnId,
				'mode' => $row[1]
			];

		}
		$this->sendUpdateViewRequest($viewName, ['sort' => json_encode($sortData)]);
	}

	/**
	 * @When user :user update view :viewName with title :title and emoji :emoji
	 *
	 * @param string $user
	 * @param string $viewName
	 * @param string $title
	 * @param string|null $emoji
	 */
	public function updateView(string $user, string $viewName, string $title, ?string $emoji): void {
		$this->setCurrentUser($user);

		$data = ['title' => $title];
		if ($emoji !== null) {
			$data['emoji'] = $emoji;
		}

		$this->sendUpdateViewRequest($viewName, $data);
		$updatedItem = $this->getDataFromResponse($this->response);

		Assert::assertEquals(200, $this->response->getStatusCode());
		Assert::assertEquals($updatedItem['title'], $title);
		Assert::assertEquals($updatedItem['emoji'], $emoji);

		$this->sendRequest(
			'GET',
			'/apps/tables/api/1/views/' . $updatedItem['id'],
		);

		$itemToVerify = $this->getDataFromResponse($this->response);
		Assert::assertEquals(200, $this->response->getStatusCode());
		Assert::assertEquals($itemToVerify['title'], $title);
		Assert::assertEquals($itemToVerify['emoji'], $emoji);
	}

	/**
	 * @When user :user sets columnSettings :columnList to view :viewAlias
	 */
	public function applyColumnsToView(string $user, string $columnList, string $viewAlias) {
		$this->setCurrentUser($user);

		$columns = explode(',', $columnList);
		$columnSettings = array_map(function (string $columnAlias, int $index) {
			if (is_numeric($columnAlias)) {
				return [
					'columnId' => (int)$columnAlias,
					'order' => $index
				];
			}

			$col = $this->collectionManager->getByAlias('column', $columnAlias);

			return [
				'columnId' => $col['id'],
				'order' => $index
			];
		}, $columns, array_keys($columns));

		$this->sendUpdateViewRequest($viewAlias, ['columnSettings' => json_encode($columnSettings)]);

		$view = $this->collectionManager->getByAlias('view', $viewAlias);
		$view['columnSettings'] = $columnSettings;
		$this->collectionManager->update($view, 'view', $view['id']);
	}

	/**
	 * @When user :user deletes view :viewName
	 *
	 * @param string $user
	 * @param string $viewName
	 */
	public function deleteView(string $user, string $viewName): void {
		if (!$this->deleteViewWithoutAssertion($user, $viewName)) {
			// deletion was not triggered
			Assert::assertTrue(isset($this->viewIds[$viewName]));
		}
		$deletedItem = $this->getDataFromResponse($this->response);

		Assert::assertEquals(200, $this->response->getStatusCode());

		$this->sendRequest(
			'GET',
			'/apps/tables/api/1/views/' . $deletedItem['id'],
		);
		Assert::assertEquals(404, $this->response->getStatusCode());

		unset($this->viewIds[$viewName]);
	}

	public function deleteViewWithoutAssertion(string $user, string $viewName): bool {
		if (!isset($this->viewIds[$viewName])) {
			return false;
		}
		$this->setCurrentUser($user);

		$this->sendRequest(
			'DELETE',
			'/apps/tables/api/1/views/' . $this->viewIds[$viewName]
		);
		return true;
	}

	/**
	 * @Then user :user deletes table with keyword :keyword
	 *
	 * @param string $user
	 * @param string $keyword
	 */
	public function deleteTable(string $user, string $keyword): void {
		$this->setCurrentUser($user);
		$table = $this->getTableByKeyword($keyword);

		$this->sendRequest(
			'DELETE',
			'/apps/tables/api/1/tables/' . $table['id']
		);
		$deletedTable = $this->getDataFromResponse($this->response);

		Assert::assertEquals(200, $this->response->getStatusCode());
		Assert::assertEquals($deletedTable['title'], $table['title']);
		Assert::assertEquals($deletedTable['id'], $table['id']);

		if ($tableItem = $this->collectionManager->getById('table', $table['id'])) {
			$this->collectionManager->forget('table', $tableItem['id']);
		}

		$this->sendRequest(
			'GET',
			'/apps/tables/api/1/tables/' . $deletedTable['id'],
		);
		Assert::assertEquals(404, $this->response->getStatusCode());

		$this->tableId = null;
		$this->columnId = null;
	}

	/**
	 * @When user :user attempts to share the table with user :receiver
	 */
	public function userAttemptsToShareTheTableWithUser(string $user, string $receiver): void {
		$this->setCurrentUser($user);

		$permissions = [
			'permissionRead' => true,
			'permissionCreate' => true,
			'permissionUpdate' => true,
			'permissionDelete' => false,
			'permissionManage' => false
		];
		$this->sendRequest(
			'POST',
			sprintf('/apps/tables/api/1/tables/%d/shares', $this->tableId),
			array_merge($permissions, [
				'receiverType' => 'user',
				'receiver' => $receiver
			])
		);
	}

	/**
	 * @Then user :initiator shares :nodeType :nodeAlias with :shareType :recipient
	 */
	public function shareNodeWith(
		string $initiator,
		string $nodeType,
		string $nodeAlias,
		string $shareType,
		string $recipient,
	): void {
		$this->setCurrentUser($initiator);
		$node = $this->collectionManager->getByAlias($nodeType, $nodeAlias);

		$permissions = [
			'permissionRead' => true,
			'permissionCreate' => true,
			'permissionUpdate' => true,
			'permissionDelete' => false,
			'permissionManage' => false
		];
		$this->sendRequest(
			'POST',
			sprintf('/apps/tables/api/1/shares'),
			array_merge($permissions, [
				'receiverType' => $shareType,
				'receiver' => $recipient,
				'nodeId' => $node['id'],
				'nodeType' => $nodeType,
			])
		);

		if ($this->response->getStatusCode() === 200) {
			$share = $this->getDataFromResponse($this->response);
			$this->shareId = $share['id'];
		}
	}

	/**
	 * @Then user :user shares table with user :receiver
	 *
	 * @param string $user
	 * @param string $receiver
	 * @deprecated use shareTableWith
	 */
	public function shareTableWithUser(string $user, string $receiver): void {
		$this->setCurrentUser($user);
		$table = $this->getTableById($this->tableId);

		$permissions = [
			'permissionRead' => true,
			'permissionCreate' => true,
			'permissionUpdate' => true,
			'permissionDelete' => false,
			'permissionManage' => false
		];
		$this->sendRequest(
			'POST',
			'/apps/tables/api/1/tables/' . $table['id'] . '/shares',
			array_merge($permissions, [
				'receiverType' => 'user',
				'receiver' => $receiver
			])
		);
		$share = $this->getDataFromResponse($this->response);
		$this->shareId = $share['id'];

		Assert::assertEquals(200, $this->response->getStatusCode());
		Assert::assertEquals($share['nodeType'], 'table');
		Assert::assertEquals($share['nodeId'], $table['id']);
		Assert::assertEquals($share['receiverType'], 'user');
		Assert::assertEquals($share['receiver'], $receiver);
		Assert::assertEquals($share['permissionRead'], $permissions['permissionRead']);
		Assert::assertEquals($share['permissionCreate'], $permissions['permissionCreate']);
		Assert::assertEquals($share['permissionUpdate'], $permissions['permissionUpdate']);
		Assert::assertEquals($share['permissionDelete'], $permissions['permissionDelete']);
		Assert::assertEquals($share['permissionManage'], $permissions['permissionManage']);

		$this->sendRequest(
			'GET',
			'/apps/tables/api/1/shares/' . $share['id'],
		);

		$shareToVerify = $this->getDataFromResponse($this->response);
		Assert::assertEquals(200, $this->response->getStatusCode());
		Assert::assertEquals($shareToVerify['nodeType'], 'table');
		Assert::assertEquals($shareToVerify['nodeId'], $table['id']);
		Assert::assertEquals($shareToVerify['receiverType'], 'user');
		Assert::assertEquals($shareToVerify['receiver'], $receiver);
		Assert::assertEquals($shareToVerify['permissionRead'], $permissions['permissionRead']);
		Assert::assertEquals($shareToVerify['permissionCreate'], $permissions['permissionCreate']);
		Assert::assertEquals($shareToVerify['permissionUpdate'], $permissions['permissionUpdate']);
		Assert::assertEquals($shareToVerify['permissionDelete'], $permissions['permissionDelete']);
		Assert::assertEquals($shareToVerify['permissionManage'], $permissions['permissionManage']);
	}

	/**
	 * @Then user :user shares table with group :receiver
	 *
	 * @param string $user
	 * @param string $receiver
	 */
	public function shareTableWithGroup(string $user, string $receiver): void {
		$this->setCurrentUser($user);
		$table = $this->getTableById($this->tableId);

		$permissions = [
			'permissionRead' => true,
			'permissionCreate' => true,
			'permissionUpdate' => true,
			'permissionDelete' => true,
			'permissionManage' => false
		];
		$this->sendRequest(
			'POST',
			'/apps/tables/api/1/tables/' . $table['id'] . '/shares',
			array_merge($permissions, [
				'receiverType' => 'group',
				'receiver' => $receiver
			])
		);
		$share = $this->getDataFromResponse($this->response);
		$this->shareId = $share['id'];

		Assert::assertEquals(200, $this->response->getStatusCode());
		Assert::assertEquals($share['nodeType'], 'table');
		Assert::assertEquals($share['nodeId'], $table['id']);
		Assert::assertEquals($share['receiverType'], 'group');
		Assert::assertEquals($share['receiver'], $receiver);
		Assert::assertEquals($share['permissionRead'], $permissions['permissionRead']);
		Assert::assertEquals($share['permissionCreate'], $permissions['permissionCreate']);
		Assert::assertEquals($share['permissionUpdate'], $permissions['permissionUpdate']);
		Assert::assertEquals($share['permissionDelete'], $permissions['permissionDelete']);
		Assert::assertEquals($share['permissionManage'], $permissions['permissionManage']);

		$this->sendRequest(
			'GET',
			'/apps/tables/api/1/shares/' . $share['id'],
		);

		$shareToVerify = $this->getDataFromResponse($this->response);
		Assert::assertEquals(200, $this->response->getStatusCode());
		Assert::assertEquals($shareToVerify['nodeType'], 'table');
		Assert::assertEquals($shareToVerify['nodeId'], $table['id']);
		Assert::assertEquals($shareToVerify['receiverType'], 'group');
		Assert::assertEquals($shareToVerify['receiver'], $receiver);
		Assert::assertEquals($shareToVerify['permissionRead'], $permissions['permissionRead']);
		Assert::assertEquals($shareToVerify['permissionCreate'], $permissions['permissionCreate']);
		Assert::assertEquals($shareToVerify['permissionUpdate'], $permissions['permissionUpdate']);
		Assert::assertEquals($shareToVerify['permissionDelete'], $permissions['permissionDelete']);
		Assert::assertEquals($shareToVerify['permissionManage'], $permissions['permissionManage']);
	}


	private function getShareById(int $shareId): array {
		$this->sendRequest(
			'GET',
			'/apps/tables/api/1/shares/' . $shareId
		);

		return $this->getDataFromResponse($this->response);
	}

	/**
	 * @Then user :user has the following permissions against :nodeType :nodeAlias
	 */
	public function checkPermissionsOnNode(
		string $user,
		string $nodeType,
		string $nodeAlias,
		?TableNode $permissions = null): void {
		$this->setCurrentUser($user);
		$node = $this->collectionManager->getByAlias($nodeType, $nodeAlias);
		$data = match ($nodeType) {
			'view' => $this->getViewById($node['id']),
			'table' => $this->getTableById($node['id']),
		};

		foreach ($permissions?->getRows() as $row) {
			Assert::assertEquals($data['onSharePermissions'][$row[0]], (bool)$row[1], sprintf('Permission %s is not as expected', $row[0]));
		}
	}

	/**
	 * @When user :user attempts to check the share permissions
	 */
	public function attemptToCheckSharePermissions(string $user): void {
		$this->setCurrentUser($user);
		$this->getShareById($this->shareId);
	}

	/**
	 * @When user :user attempts to fetch all shares of :element :alias
	 */
	public function attemptToFetchAllShares(string $user, string $element, string $alias): void {
		$this->setCurrentUser($user);
		$tableId = $this->collectionManager->getByAlias($element, $alias)['id'];

		$this->sendRequest(
			'GET',
			sprintf('/apps/tables/api/1/%ss/%d/shares', $element, $tableId)
		);
	}

	/**
	 * @Then user :user has the following permissions
	 */
	public function checkSharePermissions($user, ?TableNode $permissions = null) {
		$this->setCurrentUser($user);

		$share = $this->getShareById($this->shareId);
		$table = $this->getTableById($share['nodeId']);

		foreach ($permissions?->getRows() as $row) {
			Assert::assertEquals($table['onSharePermissions'][$row[0]], (bool)$row[1]);
		}
	}

	/**
	 * @Then user :user sets permission :permissionType to :value
	 *
	 * @param string $user
	 * @param string $permissionType
	 * @param bool $value
	 */
	public function updateSharePermission(string $user, string $permissionType, bool $value): void {
		$this->setCurrentUser($user);

		$this->sendRequest(
			'PUT',
			'/apps/tables/api/1/shares/' . $this->shareId,
			[
				'permissionType' => $permissionType,
				'permissionValue' => $value
			]
		);
		$share = $this->getDataFromResponse($this->response);

		Assert::assertEquals(200, $this->response->getStatusCode());
		Assert::assertEquals($share['permission' . ucfirst($permissionType)], $value);
	}

	// COLUMNS --------------------------

	/**
	 * @Then column :title exists with following properties
	 *
	 * @param string $title
	 * @param TableNode|null $properties
	 */
	public function createColumn(string $title, ?TableNode $properties = null): void {
		$props = ['title' => $title];
		foreach ($properties->getRows() as [$key, $value]) {
			if ($key === 'customSettings') {
				$value = json_decode($value, true);
			}
			$props[$key] = $value;
		}

		$this->sendRequest(
			'POST',
			'/apps/tables/api/1/tables/' . $this->tableId . '/columns',
			$props
		);

		$newColumn = $this->getDataFromResponse($this->response);
		$this->columnId = $newColumn['id'];

		Assert::assertEquals(200, $this->response->getStatusCode());
		Assert::assertEquals($newColumn['title'], $title);

		$this->sendRequest(
			'GET',
			'/apps/tables/api/1/columns/' . $newColumn['id'],
		);

		$columnToVerify = $this->getDataFromResponse($this->response);
		Assert::assertEquals(200, $this->response->getStatusCode());
		Assert::assertEquals($columnToVerify['title'], $title);

		foreach ($properties->getRows() as [$key, $value]) {
			if ($key === 'selectionOptions') {
				continue;
			}

			$value = match (true) {
				$key === 'customSettings' => json_decode($value, true),
				$value === 'true' => true,
				$value === 'false' => false,
				$value === 'null' => null,
				default => $value,
			};
			Assert::assertEquals($columnToVerify[$key], $value);
		}

		$this->collectionManager->register($newColumn, 'column', $newColumn['id'], $title);
	}

	/**
	 * @Then table has at least following columns
	 *
	 * @param TableNode|null $body
	 */
	public function tableColumns(?TableNode $body = null): void {
		$this->sendRequest(
			'GET',
			'/apps/tables/api/1/tables/' . $this->tableId . '/columns'
		);

		$data = $this->getDataFromResponse($this->response);
		Assert::assertEquals(200, $this->response->getStatusCode());

		// check if no columns exists
		if ($body === null) {
			Assert::assertCount(0, $data);
			return;
		}

		$titles = [];
		foreach ($data as $d) {
			$titles[] = $d['title'];
		}
		foreach ($body->getRows()[0] as $columnTitle) {
			Assert::assertTrue(in_array($columnTitle, $titles, true));
		}
	}

	/**
	 * @Then table has at least following typed columns
	 *
	 * @param TableNode|null $body
	 */
	public function tableTypedColumns(?TableNode $body = null): void {
		$this->sendRequest(
			'GET',
			'/apps/tables/api/1/tables/' . $this->tableId . '/columns'
		);

		$data = $this->getDataFromResponse($this->response);
		Assert::assertEquals(200, $this->response->getStatusCode());

		// check if no columns exists
		if ($body === null) {
			Assert::assertCount(0, $data);
			return;
		}

		$colByTitle = [];
		foreach ($data as $d) {
			$colByTitle[$d['title']] = $d['type'];
		}
		foreach ($body->getRows() as $columnData) {
			Assert::assertArrayHasKey($columnData[0], $colByTitle);
			Assert::assertSame($columnData[1], $colByTitle[$columnData[0]], sprintf('Column "%s" has unexpected type "%s"', $columnData[0], $colByTitle[$columnData[0]]));
		}
	}

	/**
	 * @Then user deletes last created column
	 */
	public function deleteColumn(): void {
		$this->sendRequest(
			'DELETE',
			'/apps/tables/api/1/columns/' . $this->columnId
		);
		$column = $this->getDataFromResponse($this->response);

		Assert::assertEquals(200, $this->response->getStatusCode());
		Assert::assertEquals($column['id'], $this->columnId);

		$this->sendRequest(
			'GET',
			'/apps/tables/api/1/columns/' . $column['id'],
		);
		Assert::assertEquals(404, $this->response->getStatusCode());
	}

	/**
	 * @Then set following properties for last created column
	 *
	 * @param TableNode|null $properties
	 */
	public function updateColumn(?TableNode $properties = null): void {
		$props = [];
		foreach ($properties->getRows() as $row) {
			$props[$row[0]] = $row[1];
		}

		$this->sendRequest(
			'PUT',
			'/apps/tables/api/1/columns/' . $this->columnId,
			$props
		);

		$column = $this->getDataFromResponse($this->response);

		Assert::assertEquals(200, $this->response->getStatusCode());
		foreach ($props as $key => $value) {
			if (is_array($column[$key])) {
				// I am afraid this is usergroupcolumn specific, but not generic
				$retrieved = json_encode($column[$key], true);
			} else {
				$retrieved = $column[$key];
			}

			Assert::assertEquals($value, $retrieved, 'Failing key: ' . $key);
		}

		$this->sendRequest(
			'GET',
			'/apps/tables/api/1/columns/' . $column['id'],
		);

		$columnToVerify = $this->getDataFromResponse($this->response);
		Assert::assertEquals(200, $this->response->getStatusCode());
		foreach ($props as $key => $value) {
			// I am afraid this is usergroupcolumn specific, but not generic
			if (is_array($columnToVerify[$key])) {
				$retrieved = json_encode($columnToVerify[$key], true);
			} else {
				$retrieved = $columnToVerify[$key];
			}

			Assert::assertEquals($value, $retrieved, 'Failing key: ' . $key);
		}
	}

	// ROWS --------------------------

	/**
	 * @Then row exists with following values
	 *
	 * @param TableNode|null $properties
	 */
	public function createRow(?TableNode $properties = null): void {
		$props = [];
		foreach ($properties->getRows() as $row) {
			$columnId = $this->collectionManager->getByAlias('column', $row[0])['id'];
			$props[$columnId] = $row[1];
		}

		$this->sendRequest(
			'POST',
			'/apps/tables/api/1/tables/' . $this->tableId . '/rows',
			['data' => $props]
		);

		$newRow = $this->getDataFromResponse($this->response);
		$this->rowId = $newRow['id'];

		Assert::assertEquals(200, $this->response->getStatusCode());
		foreach ($newRow['data'] as $cell) {
			Assert::assertEquals($props[$cell['columnId']], $cell['value']);
		}

		$this->sendRequest(
			'GET',
			'/apps/tables/api/1/rows/' . $newRow['id'],
		);

		$rowToVerify = $this->getDataFromResponse($this->response);
		Assert::assertEquals(200, $this->response->getStatusCode());
		foreach ($rowToVerify['data'] as $cell) {
			// I am afraid this is usergroupcolumn specific, but not generic
			if (is_array($cell['value'])) {
				$retrieved = json_encode(array_reduce($cell['value'], function (array $carry, string $item): array {
					$carry[] = json_decode($item, true);
					return $carry;
				}, []));
			} else {
				$retrieved = $cell['value'];
			}

			Assert::assertEquals($props[$cell['columnId']], $retrieved);
		}
	}

	/**
	 * @When user :user tries to create a row using v2 on :nodeType :nodeAlias with following values
	 *
	 * @param TableNode $properties
	 */
	public function userTriesToCreateRowUsingV2OnNodeXWithFollowingValues(string $user, string $nodeType, string $nodeAlias, TableNode $properties): void {
		$this->setCurrentUser($user);
		$node = $this->collectionManager->getByAlias($nodeType, $nodeAlias);

		$props = [];
		foreach ($properties->getRows() as $row) {
			$columnId = $this->collectionManager->getByAlias('column', $row[0])['id'];
			$props[$columnId] = $row[1];
		}

		$this->sendOcsRequest(
			'POST',
			sprintf('/apps/tables/api/2/%ss/%d/rows', $nodeType, $node['id']),
			['data' => $props]
		);
	}

	/**
	 * @When user :user tries to create a row using v2 with following values
	 *
	 * @param TableNode $properties
	 */
	public function userTriesToCreateRowUsingV2WithFollowingValues(string $user, TableNode $properties): void {
		$this->setCurrentUser($user);

		$props = [];
		foreach ($properties->getRows() as $row) {
			$columnId = $this->collectionManager->getByAlias('column', $row[0])['id'];
			$props[$columnId] = $row[1];
		}

		$this->sendOcsRequest(
			'POST',
			'/apps/tables/api/2/tables/' . $this->tableId . '/rows',
			['data' => $props]
		);
	}

	/**
	 * @Then row exists using v2 with following values
	 *
	 * @param TableNode $properties
	 */
	public function createRowV2(TableNode $properties): void {
		$props = [];
		foreach ($properties->getRows() as $row) {
			$columnId = $this->collectionManager->getByAlias('column', $row[0])['id'];
			$props[$columnId] = $row[1];
		}

		$this->sendOcsRequest(
			'POST',
			'/apps/tables/api/2/tables/' . $this->tableId . '/rows',
			['data' => $props]
		);

		$newRow = $this->getDataFromResponse($this->response)['ocs']['data'];
		$this->rowId = $newRow['id'];

		Assert::assertEquals(200, $this->response->getStatusCode());
		foreach ($newRow['data'] as $cell) {
			Assert::assertEquals($props[$cell['columnId']], $cell['value']);
		}

		$this->sendRequest(
			'GET',
			'/apps/tables/api/1/rows/' . $newRow['id'],
		);

		$rowToVerify = $this->getDataFromResponse($this->response);
		Assert::assertEquals(200, $this->response->getStatusCode());
		foreach ($rowToVerify['data'] as $cell) {
			Assert::assertEquals($props[$cell['columnId']], $cell['value']);
		}
	}

	/**
	 * @Then row exists with following values via legacy interface
	 *
	 * @param TableNode|null $properties
	 */
	public function createRowLegacy(?TableNode $properties = null): void {
		$props = [];
		foreach ($properties->getRows() as $row) {
			$columnId = $this->collectionManager->getByAlias('column', $row[0])['id'];
			$props[$columnId] = $row[1];
		}

		$this->sendRequest(
			'POST',
			'/apps/tables/api/1/tables/' . $this->tableId . '/rows',
			['data' => json_encode($props)]
		);

		$newRow = $this->getDataFromResponse($this->response);
		$this->rowId = $newRow['id'];

		Assert::assertEquals(200, $this->response->getStatusCode());
		foreach ($newRow['data'] as $cell) {
			Assert::assertEquals($props[$cell['columnId']], $cell['value']);
		}

		$this->sendRequest(
			'GET',
			'/apps/tables/api/1/rows/' . $newRow['id'],
		);

		$rowToVerify = $this->getDataFromResponse($this->response);
		Assert::assertEquals(200, $this->response->getStatusCode());
		foreach ($rowToVerify['data'] as $cell) {
			Assert::assertEquals($props[$cell['columnId']], $cell['value']);
		}
	}

	/**
	 * @Then user deletes last created row
	 */
	public function deleteRow(): void {
		$this->sendRequest(
			'DELETE',
			'/apps/tables/api/1/rows/' . $this->rowId
		);
		$row = $this->getDataFromResponse($this->response);

		Assert::assertEquals(200, $this->response->getStatusCode());
		Assert::assertEquals($row['id'], $this->rowId);

		$this->sendRequest(
			'GET',
			'/apps/tables/api/1/rows/' . $row['id'],
		);
		Assert::assertEquals(404, $this->response->getStatusCode());
	}

	/**
	 * @Then set following values for last created row
	 *
	 * @param TableNode|null $properties
	 */
	public function updateRow(?TableNode $properties = null): void {
		$props = [];
		foreach ($properties->getRows() as $row) {
			$columnId = $this->collectionManager->getByAlias('column', $row[0])['id'];
			$props[$columnId] = $row[1];
		}

		$this->sendRequest(
			'PUT',
			'/apps/tables/api/1/rows/' . $this->rowId,
			['data' => $props]
		);

		$row = $this->getDataFromResponse($this->response);

		Assert::assertEquals(200, $this->response->getStatusCode());
		foreach ($row['data'] as $cell) {
			Assert::assertEquals($props[$cell['columnId']], $cell['value']);
		}

		$this->sendRequest(
			'GET',
			'/apps/tables/api/1/rows/' . $row['id'],
		);

		$rowToVerify = $this->getDataFromResponse($this->response);
		Assert::assertEquals(200, $this->response->getStatusCode());
		foreach ($rowToVerify['data'] as $cell) {
			// I am afraid this is usergroupcolumn specific, but not generic
			if (is_array($cell['value'])) {
				$retrieved = json_encode(array_reduce($cell['value'], function (array $carry, string $item): array {
					$carry[] = json_decode($item, true);
					return $carry;
				}, []));
			} else {
				$retrieved = $cell['value'];
			}

			Assert::assertEquals($props[$cell['columnId']], $retrieved);
		}
	}

	/**
	 * @Then set following values for last created row via legacy interface
	 *
	 * @param TableNode|null $properties
	 */
	public function updateRowLegacy(?TableNode $properties = null): void {
		$props = [];
		foreach ($properties->getRows() as $row) {
			$columnId = $this->collectionManager->getByAlias('column', $row[0])['id'];
			$props[$columnId] = $row[1];
		}

		$this->sendRequest(
			'PUT',
			'/apps/tables/api/1/rows/' . $this->rowId,
			['data' => json_encode($props)]
		);

		$row = $this->getDataFromResponse($this->response);

		Assert::assertEquals(200, $this->response->getStatusCode());
		foreach ($row['data'] as $cell) {
			Assert::assertEquals($props[$cell['columnId']], $cell['value']);
		}

		$this->sendRequest(
			'GET',
			'/apps/tables/api/1/rows/' . $row['id'],
		);

		$rowToVerify = $this->getDataFromResponse($this->response);
		Assert::assertEquals(200, $this->response->getStatusCode());
		foreach ($rowToVerify['data'] as $cell) {
			Assert::assertEquals($props[$cell['columnId']], $cell['value']);
		}
	}

	/*
	 * User management
	 */

	#[Given('as user :user')]
	public function setCurrentUser(?string $user): void {
		$this->currentUser = $user;
	}

	/**
	 * @Given /^user "([^"]*)" exists$/
	 * @param string $user
	 */
	public function assureUserExists($user) {
		$response = $this->userExists($user);
		if ($response->getStatusCode() !== 200) {
			$this->createUser($user);
			// Set a display name different than the user ID to be able to
			// ensure in the tests that the right value was returned.
			$this->setUserDisplayName($user);
			$response = $this->userExists($user);
			$this->assertStatusCode($response, 200);
		}
	}

	private function userExists($user) {
		$currentUser = $this->currentUser;
		$this->setCurrentUser('admin');
		$this->sendOcsRequest('GET', '/cloud/users/' . $user);
		$this->setCurrentUser($currentUser);
		return $this->response;
	}

	private function createUser($user) {
		$currentUser = $this->currentUser;
		$this->setCurrentUser('admin');
		$this->sendOcsRequest('POST', '/cloud/users', [
			'userid' => $user,
			'password' => self::TEST_PASSWORD,
		]);
		$this->assertStatusCode($this->response, 200, 'Failed to create user');

		//Quick hack to login once with the current user
		$this->setCurrentUser($user);
		$this->sendOcsRequest('GET', '/cloud/users' . '/' . $user);
		$this->assertStatusCode($this->response, 200, 'Failed to do first login');

		$this->createdUsers[] = $user;

		$this->setCurrentUser($currentUser);
	}

	/**
	 * @Given /^user "([^"]*)" is deleted$/
	 * @param string $user
	 */
	public function userIsDeleted($user) {
		$deleted = false;

		$this->deleteUser($user);

		$response = $this->userExists($user);
		$deleted = $response->getStatusCode() === 404;

		if (!$deleted) {
			Assert::fail("User $user exists");
		}
	}

	private function deleteUser($user) {
		$currentUser = $this->currentUser;
		$this->setCurrentUser('admin');
		$this->sendOcsRequest('DELETE', '/cloud/users/' . $user);
		$this->setCurrentUser($currentUser);

		unset($this->createdUsers[array_search($user, $this->createdUsers, true)]);

		return $this->response;
	}

	private function setUserDisplayName($user) {
		$currentUser = $this->currentUser;
		$this->setCurrentUser('admin');
		$this->sendOcsRequest('PUT', '/cloud/users/' . $user, [
			'key' => 'displayname',
			'value' => $user . '-displayname'
		]);
		$this->setCurrentUser($currentUser);
	}

	/**
	 * @Given /^group "([^"]*)" exists$/
	 * @param string $group
	 * @throws Exception
	 */
	public function assureGroupExists($group) {
		$currentUser = $this->currentUser;
		$this->setCurrentUser('admin');
		$this->sendOcsRequest('POST', '/cloud/groups', [
			'groupid' => $group,
		]);

		$jsonBody = json_decode($this->response->getBody()->getContents(), true);
		if (isset($jsonBody['ocs']['meta'])) {
			// 102 = group exists
			// 200 = created with success
			Assert::assertContains(
				$jsonBody['ocs']['meta']['statuscode'],
				[102, 200],
				$jsonBody['ocs']['meta']['message']
			);
		} else {
			throw new \Exception('Invalid response when create group');
		}

		$this->setCurrentUser($currentUser);

		$this->createdGroups[] = $group;
	}

	private function deleteGroup($group) {
		$currentUser = $this->currentUser;
		$this->setCurrentUser('admin');
		$this->sendOcsRequest('DELETE', '/cloud/groups/' . $group);
		$this->setCurrentUser($currentUser);

		unset($this->createdGroups[array_search($group, $this->createdGroups, true)]);
	}

	/**
	 * @When /^user "([^"]*)" is member of group "([^"]*)"$/
	 * @param string $user
	 * @param string $group
	 */
	public function addingUserToGroup($user, $group) {
		$currentUser = $this->currentUser;
		$this->setCurrentUser('admin');
		$this->sendOcsRequest('POST', "/cloud/users/$user/groups", [
			'groupid' => $group,
		]);
		$this->assertStatusCode($this->response, 200);
		$this->setCurrentUser($currentUser);
	}

	/**
	 * @When /^user "([^"]*)" is not member of group "([^"]*)"$/
	 * @param string $user
	 * @param string $group
	 */
	public function removeUserFromGroup($user, $group) {
		$currentUser = $this->currentUser;
		$this->setCurrentUser('admin');
		$this->sendOcsRequest('DELETE', "/cloud/users/$user/groups", [
			'groupid' => $group,
		]);
		$this->assertStatusCode($this->response, 200);
		$this->setCurrentUser($currentUser);
	}

	/*
	 * Requests
	 */

	/**
	 * @Given /^user "([^"]*)" logs in$/
	 * @param string $user
	 * @throws GuzzleException
	 */
	public function userLogsIn(string $user) {
		$loginUrl = $this->baseUrl . 'login';

		$cookieJar = $this->getUserCookieJar($user);

		// Request a new session and extract CSRF token
		$client = new Client();
		$this->response = $client->get(
			$loginUrl,
			[
				'cookies' => $cookieJar,
			]
		);

		$requestToken = $this->extractRequestTokenFromResponse($this->response);

		// Login and extract new token
		$password = ($user === 'admin') ? 'admin' : self::TEST_PASSWORD;
		$client = new Client();
		$this->response = $client->post(
			$loginUrl,
			[
				'form_params' => [
					'user' => $user,
					'password' => $password,
					'requesttoken' => $requestToken,
				],
				'cookies' => $cookieJar,
			]
		);

		$this->assertStatusCode($this->response, 200);
	}

	/**
	 * Parses the xml answer to get the array of users returned.
	 * @param ResponseInterface $response
	 * @return array
	 */
	protected function getDataFromResponse(ResponseInterface $response): array {
		return json_decode($response->getBody()->getContents(), true);
	}

	/**
	 * @Then /^status code is ([0-9]*)$/
	 *
	 * @param int $statusCode
	 */
	public function isStatusCode($statusCode) {
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @Then the response error matches with :error
	 */
	public function assertResponseErrorMatchesWith(string $error): void {
		$responseData = $this->getDataFromResponse($this->response);
		Assert::assertEquals(['error' => $error], $responseData);
	}

	/**
	 * @param ResponseInterface $response
	 * @return string
	 */
	private function extractRequestTokenFromResponse(ResponseInterface $response): string {
		return substr(preg_replace('/(.*)data-requesttoken="(.*)">(.*)/sm', '\2', $response->getBody()->getContents()), 0, 89);
	}

	/**
	 * @When /^sending "([^"]*)" to "([^"]*)" with$/
	 * @param string $verb
	 * @param string $url
	 * @param TableNode|array|null $body
	 * @param array $headers
	 * @param array $options
	 */
	public function sendRequest($verb, $url, $body = null, array $headers = [], array $options = []) {
		$fullUrl = $this->baseUrl . 'index.php' . $url;
		$this->sendRequestFullUrl($verb, $fullUrl, $body, $headers, $options);
	}

	/**
	 * @When /^sending ocs request "([^"]*)" to "([^"]*)" with$/
	 * @param string $verb
	 * @param string $url
	 * @param TableNode|array|null $body
	 * @param array $headers
	 * @param array $options
	 */
	public function sendOcsRequest($verb, $url, $body = null, array $headers = [], array $options = []) {
		$fullUrl = $this->baseUrl . 'ocs/v2.php' . $url;
		$this->sendRequestFullUrl($verb, $fullUrl, $body, $headers, $options);
	}

	/**
	 * @param string $verb
	 * @param string $fullUrl
	 * @param TableNode|array|null $body
	 * @param array $headers
	 * @param array $options
	 */
	public function sendRequestFullUrl($verb, $fullUrl, $body = null, array $headers = [], array $options = []) {
		$client = new Client();
		$options = array_merge($options, ['cookies' => $this->getUserCookieJar($this->currentUser)]);
		if ($this->currentUser === 'admin') {
			$options['auth'] = ['admin', 'admin'];
		} elseif (strpos($this->currentUser, 'guest') !== 0) {
			$options['auth'] = [$this->currentUser, self::TEST_PASSWORD];
		}
		if ($body instanceof TableNode) {
			$fd = $body->getRowsHash();
			$options['form_params'] = $fd;
		} elseif (is_array($body)) {
			$options['form_params'] = $body;
		} else {
			$options['body'] = $body;
		}

		$options['headers'] = array_merge($headers, [
			'OCS-ApiRequest' => 'true',
			'Accept' => 'application/json',
		]);

		try {
			$this->response = $client->{$verb}($fullUrl, $options);
		} catch (ClientException $ex) {
			$this->response = $ex->getResponse();
		} catch (\GuzzleHttp\Exception\ServerException $ex) {
			$this->response = $ex->getResponse();
		}
	}

	protected function getUserCookieJar($user) {
		if (!isset($this->cookieJars[$user])) {
			$this->cookieJars[$user] = new CookieJar();
		}
		return $this->cookieJars[$user];
	}

	/**
	 * @param ResponseInterface $response
	 * @param int $statusCode
	 * @param string $message
	 */
	protected function assertStatusCode(ResponseInterface $response, int $statusCode, string $message = '') {
		if ($statusCode !== $response->getStatusCode()) {
			$content = $this->response->getBody()->getContents();
			Assert::assertEquals(
				$statusCode,
				$response->getStatusCode(),
				$message . ($message ? ': ' : '') . $content
			);
		} else {
			Assert::assertEquals($statusCode, $response->getStatusCode(), $message);
		}
	}

	/**
	 * @Given user :user sees the following table attributes on table :tableName
	 */
	public function userSeesTheFollowingTableAttributesOnTable($user, $tableName, TableNode $table) {
		foreach ($table->getRows() as $row) {
			$attribute = $row[0];
			$value = $row[1];
			if (in_array($attribute, ['archived', 'favorite'])) {
				$value = (bool)$value;
			}
			Assert::assertEquals($value, $this->tableData[$tableName][$attribute]);
		}
	}

	/**
	 * @Given user :user adds the table :tableName to favorites
	 */
	public function userAddsTheTableToFavorites($user, $tableName) {
		$this->setCurrentUser($user);
		$nodeType = 0;
		$tableId = $this->tableIds[$tableName];

		$this->sendOcsRequest(
			'POST',
			'/apps/tables/api/2/favorites/' . $nodeType . '/' . $tableId,
		);
		if ($this->response->getStatusCode() === 200) {
			$this->userFetchesTableInfo($user, $tableName);
		}
	}

	/**
	 * @Given user :user removes the table :tableName from favorites
	 */
	public function userRemovesTheTableFromFavorites($user, $tableName) {
		$this->setCurrentUser($user);
		$nodeType = 0;
		$tableId = $this->tableIds[$tableName];

		$this->sendOcsRequest(
			'DELETE',
			'/apps/tables/api/2/favorites/' . $nodeType . '/' . $tableId,
		);
		if ($this->response->getStatusCode() === 200) {
			$this->userFetchesTableInfo($user, $tableName);
		}
	}

	/**
	 * @Then /^the last response should have a "([^"]*)" status code$/
	 */
	public function theLastResponseShouldHaveAStatusCode(int $statusCode) {
		Assert::assertEquals($statusCode, $this->response->getStatusCode());
	}

	protected function humanReadablePermissionToInt(string $humanReadablePermissionString): int {
		$humanReadablePermissions = explode(',', $humanReadablePermissionString);

		$permissions = 0;
		foreach ($humanReadablePermissions as $humanReadablePermission) {
			switch (trim($humanReadablePermission)) {
				case 'read':
					$permissions += 1;
					break;
				case 'create':
					$permissions += 2;
					break;
				case 'update':
					$permissions += 4;
					break;
				case 'delete':
					$permissions += 8;
					break;
				case 'manage':
					$permissions += 16;
					break;
				case 'all':
					$permissions = 31;
					break 2;
			}
		}
		return $permissions;
	}

	/**
	 * @When user :user attempts to create the Context :alias with name :name with icon :icon and description :description and nodes:
	 */
	public function attemptCreateContext(string $user, string $alias, string $name, string $icon, string $description, TableNode $table) {
		$exceptionCaught = false;
		try {
			$this->createContext($user, $alias, $name, $icon, $description, $table);
		} catch (ExpectationFailedException $e) {
			$exceptionCaught = true;

		}

		Assert::assertTrue($exceptionCaught);
	}

	/**
	 * @When user :user creates the Context :alias with name :name with icon :icon and description :description and nodes:
	 */
	public function createContext(string $user, string $alias, string $name, string $icon, string $description, TableNode $table) {
		$this->setCurrentUser($user);

		$nodes = [];
		foreach ($table as $row) {
			$permissions = $this->humanReadablePermissionToInt($row['permissions']);

			$nodes[] = [
				'id' => $row['type'] === 'table' ? $this->tableIds[$row['alias']] : $this->viewIds[$row['alias']],
				'type' => $row['type'] === 'table' ? 0 : 1,
				'permissions' => $permissions,
			];
		}

		$this->sendOcsRequest(
			'POST',
			'/apps/tables/api/2/contexts',
			[
				'name' => $name,
				'iconName' => $icon,
				'description' => $description,
				'nodes' => $nodes,
			]
		);

		Assert::assertEquals(200, $this->response->getStatusCode());

		$newContext = $this->getDataFromResponse($this->response)['ocs']['data'];

		$this->collectionManager->register($newContext, 'context', $newContext['id'], $alias, function () use ($newContext) {
			$this->deleteContextWithFetchCheck($newContext['id'], $newContext['owner']);
		});

		Assert::assertEquals($newContext['name'], $name);
		Assert::assertEquals($newContext['iconName'], $icon);
		Assert::assertEquals($newContext['owner'], $user);
		Assert::assertCount(count($nodes), $newContext['nodes'], 'Node count does not match');
		Assert::assertCount(1, $newContext['pages'], 'Page count does not match');
		Assert::assertCount(count($nodes), $newContext['pages'][array_key_first($newContext['pages'])]['content'], 'Page content count does not match');

		foreach ($newContext['nodes'] as $i => $newNode) {
			Assert::assertSame($newNode['nodeId'], $nodes[$i]['id']);
			Assert::assertSame($newNode['nodeType'], $nodes[$i]['type']);
			Assert::assertSame($newNode['permissions'], $nodes[$i]['permissions']);
		}
	}

	/**
	 * @Then user :user has access to Context :contextAlias
	 */
	public function userHasAccessToContext(string $user, string $contextAlias) {
		$this->setCurrentUser($user);

		$context = $this->collectionManager->getByAlias('context', $contextAlias);
		$contextId = $context['id'] ?? -1;
		Assert::assertNotEquals(-1, $contextId);

		$this->sendOcsRequest(
			'GET',
			'/apps/tables/api/2/contexts/' . $contextId
		);

		$context = $this->getDataFromResponse($this->response)['ocs']['data'];
		Assert::assertEquals(200, $this->response->getStatusCode());
		Assert::assertEquals($context['id'], $contextId);
		$this->collectionManager->update($context, 'context', $context['id']);
	}

	/**
	 * @Given the fetched Context :contextAlias has following data:
	 */
	public function theFetchedContextHasFollowingData(string $contextAlias, TableNode $expectedData) {
		$actualData = $this->collectionManager->getByAlias('context', $contextAlias);
		Assert::assertNotEmpty($actualData);

		foreach ($expectedData as $field => $value) {
			switch ($field) {
				case 'name':
					Assert::assertEquals($value, $actualData['name']);
					break;
				case 'icon':
					Assert::assertEquals($value, $actualData['iconName']);
					break;
				case 'node':
					[$strType, $alias, $strPermission] = explode(':', $value);
					$nodeType = $strType === 'table' ? 0 : 1;
					$nodeId = $nodeType === 0 ? $this->tableIds[$alias] : $this->viewIds[$alias];
					$permissions = $this->humanReadablePermissionToInt($strPermission);
					$found = false;
					foreach ($actualData['nodes'] as $actualNodeData) {
						$found = $found || ($actualNodeData['node_id'] === $nodeId
							&& $actualNodeData['node_type'] === $nodeType
							&& $actualNodeData['permissions'] === $permissions);
					}
					Assert::assertTrue($found);
					break;
				case 'page':
					[$pageType, $contentNodesCount] = explode(':', $value);
					$found = false;
					foreach ($actualData['pages'] as $actualPageData) {
						$found = $found || ($actualPageData['type'] === $pageType
							&& count($actualPageData['content']) === (int)$contentNodesCount);
					}
					Assert::assertTrue($found);
					break;
			}
		}
	}

	/**
	 * @Given the fetched Context :contextAlias does not contain following data:
	 */
	public function theFetchedContextDoesNotContainFollowingData(string $contextAlias, TableNode $expectedData) {
		$actualData = $this->collectionManager->getByAlias('context', $contextAlias);

		foreach ($expectedData as $field => $value) {
			switch ($field) {
				case 'name':
					Assert::assertNotEquals($value, $actualData['name']);
					break;
				case 'icon':
					Assert::assertNotEquals($value, $actualData['iconName']);
					break;
				case 'node':
					[$strType, $alias, $strPermission] = explode(':', $value);
					$nodeType = $strType === 'table' ? 0 : 1;
					$nodeId = $nodeType === 0 ? $this->tableIds[$alias] : $this->viewIds[$alias];
					$permissions = $this->humanReadablePermissionToInt($strPermission);
					$found = false;
					foreach ($actualData['nodes'] as $actualNodeData) {
						$found = $found || ($actualNodeData['node_id'] === $nodeId
								&& $actualNodeData['node_type'] === $nodeType
								&& $actualNodeData['permissions'] === $permissions);
					}
					Assert::assertFalse($found);
					break;
				case 'page':
					[$pageType, $contentNodesCount] = explode(':', $value);
					$found = false;
					foreach ($actualData['pages'] as $actualPageData) {
						$found = $found || ($actualPageData['type'] === $pageType
								&& count($actualPageData['content']) === (int)$contentNodesCount);
					}
					Assert::assertFalse($found);
					break;
			}
		}
	}

	/**
	 * @When user :user attempts to fetch Context :contextAlias
	 */
	public function userAttemptsToFetchContext(string $user, string $contextAlias): void {
		$caughtException = false;
		try {
			$this->userFetchesContext($user, $contextAlias);
		} catch (ExpectationFailedException $e) {
			$caughtException = true;
		}

		Assert::assertTrue($caughtException);
	}

	/**
	 * @When user :user fetches Context :contextAlias
	 */
	public function userFetchesContext(string $user, string $contextAlias): void {
		$this->setCurrentUser($user);

		if ($contextAlias === self::NON_EXISTING_CONTEXT_ALIAS) {
			$context = ['id' => self::NON_EXISTING_CONTEXT_ID];
		} else {
			$context = $this->collectionManager->getByAlias('context', $contextAlias);
		}

		$this->sendOcsRequest(
			'GET',
			sprintf('/apps/tables/api/2/contexts/%d', $context['id'])
		);
		Assert::assertEquals(200, $this->response->getStatusCode());

		$updatedContext = $this->getDataFromResponse($this->response)['ocs']['data'];
		Assert::assertSame($context['id'], $updatedContext['id']);
		$this->collectionManager->update($updatedContext, 'context', $updatedContext['id']);
	}

	/**
	 * @When user :user fetches all Contexts
	 */
	public function userFetchAllsContexts(string $user): void {
		$this->setCurrentUser($user);

		$this->sendOcsRequest(
			'GET',
			'/apps/tables/api/2/contexts'
		);
		Assert::assertEquals(200, $this->response->getStatusCode());
	}

	/**
	 * @Then they will find Contexts :contextAliasList and no other
	 */
	public function theyWillFindContextsAndNoOther(string $contextAliasList): void {
		$receivedContexts = $this->getDataFromResponse($this->response)['ocs']['data'];

		$aliases = $contextAliasList === '' ? [] : explode(',', $contextAliasList);
		$expectedContextIds = array_map(function (string $alias) {
			return $this->collectionManager->getByAlias('context', trim($alias))['id'];
		}, $aliases);
		sort($expectedContextIds);

		$actualContextIds = [];
		foreach ($receivedContexts as $receivedContext) {
			$actualContextIds[] = $receivedContext['id'];
		}
		sort($actualContextIds);

		Assert::assertSame($expectedContextIds, $actualContextIds, json_encode($receivedContexts));
	}

	public function deleteContext(int $contextId, string $owner): void {
		$this->setCurrentUser($owner);

		$this->sendOcsRequest(
			'DELETE',
			sprintf('/apps/tables/api/2/contexts/%d', $contextId),
		);

		Assert::assertEquals(200, $this->response->getStatusCode());
	}

	public function deleteContextWithFetchCheck(int $contextId, string $owner): void {
		$this->deleteContext($contextId, $owner);

		$this->setCurrentUser($owner);
		$this->sendOcsRequest(
			'GET',
			sprintf('/apps/tables/api/2/contexts/%d', $contextId),
		);
		Assert::assertEquals(404, $this->response->getStatusCode());
	}

	/**
	 * @Then known Context :contextAlias has :attributeName set to :attributeValue
	 */
	public function knownContextHasAttributeSetTo(string $contextAlias, string $attributeName, string $attributeValue): void {
		$context = $this->collectionManager->getByAlias('context', $contextAlias);
		$officialAttribute = match($attributeName) {
			'name' => 'name',
			'icon' => 'iconName',
			'description' => 'description',
		};
		Assert::assertSame($attributeValue, $context[$officialAttribute]);
	}

	/**
	 * @Then known Context :contextAlias contains :nodeType :nodeAlias with permissions :permissionList
	 */
	public function knownContextContainsNodeWithPermissions(string $contextAlias, string $nodeType, string $nodeAlias, string $permissionList): void {
		$context = $this->collectionManager->getByAlias('context', $contextAlias);

		$numericNodeType = $nodeType === 'table' ? 0 : 1;

		if ($numericNodeType === 0) {
			$nodeId = $this->tableIds[$nodeAlias];
		} else {
			$nodeId = $this->viewIds[$nodeAlias];
		}

		$found = false;
		$actualPermissions = -1;
		foreach ($context['nodes'] as $actualNode) {
			$found = $actualNode['node_type'] === $numericNodeType
				&& $actualNode['node_id'] === $nodeId;
			if ($found) {
				$actualPermissions = $actualNode['permissions'];
				break;
			}
		}

		Assert::assertTrue($found);

		$expectedPermissions = $this->humanReadablePermissionToInt($permissionList);
		Assert::assertSame($expectedPermissions, $actualPermissions);
	}

	/**
	 * @Then the reported status is :statusCode
	 */
	public function theReportedStatusIs(int $statusCode): void {
		Assert::assertEquals($statusCode, $this->response->getStatusCode());
	}

	/**
	 * @When user :user deletes Context :contextAlias
	 */
	public function userDeletesContext(string $user, string $contextAlias): void {
		$context = $this->collectionManager->getByAlias('context', $contextAlias);
		$this->deleteContext($context['id'], $user);
		// keep the alias and id mapping, but reset the cleanup method
		$this->collectionManager->update($context, 'context', $context['id'], fn () => null);
	}

	/**
	 * @When user :user attempts to delete Context :contextAlias
	 */
	public function userAttemptsToDeleteContext(string $user, string $contextAlias): void {
		if ($contextAlias === self::NON_EXISTING_CONTEXT_ALIAS) {
			$context = ['id' => self::NON_EXISTING_CONTEXT_ID];
		} else {
			$context = $this->collectionManager->getByAlias('context', $contextAlias);
		}

		$exceptionCaught = false;
		try {
			$this->deleteContext($context['id'], $user);
		} catch (ExpectationFailedException $e) {
			$exceptionCaught = true;
		}

		Assert::assertTrue($exceptionCaught);
	}

	/**
	 * @When user :user updates Context :contextAlias by setting
	 */
	public function userUpdatesContextBySetting(string $user, string $contextAlias, TableNode $updatedProperties) {
		$this->setCurrentUser($user);
		$context = $this->collectionManager->getByAlias('context', $contextAlias);

		$this->sendOcsRequest(
			'PUT',
			sprintf('/apps/tables/api/2/contexts/%d', $context['id']),
			$updatedProperties
		);
	}

	/**
	 * @When user :user updates the nodes of the Context :contextAlias to
	 */
	public function userUpdatesNodesOfContext(string $user, string $contextAlias, TableNode $updatedNodes) {
		$this->setCurrentUser($user);
		$context = $this->collectionManager->getByAlias('context', $contextAlias);
		$nodes = [];

		foreach ($updatedNodes as $row) {
			$permissions = $this->humanReadablePermissionToInt($row['permissions']);
			$nodes[] = [
				'id' => $row['type'] === 'table' ? $this->tableIds[$row['alias']] : $this->viewIds[$row['alias']],
				'type' => $row['type'] === 'table' ? 0 : 1,
				'permissions' => $permissions,
			];
		}

		$this->sendOcsRequest(
			'PUT',
			sprintf('/apps/tables/api/2/contexts/%d', $context['id']),
			['nodes' => $nodes]
		);
	}

	/**
	 * @When user :user transfers the Context :contextAlias to :recipientUser
	 */
	public function userTransfersTheTheContextTo(string $user, string $contextAlias, string $recipientUser): void {
		$this->setCurrentUser($user);
		$context = $this->collectionManager->getByAlias('context', $contextAlias);
		$this->sendOcsRequest(
			'PUT',
			sprintf('/apps/tables/api/2/contexts/%d/transfer', $context['id']),
			[
				'newOwnerId' => $recipientUser,
				'newOwnerType' => 0,
			]
		);
		if ($this->response->getStatusCode() === 200) {
			$context['owner'] = $recipientUser;
			$this->collectionManager->update($context, 'context', $context['id'], function () use ($context, $recipientUser) {
				$this->deleteContextWithFetchCheck($context['id'], $recipientUser);
			});
		}
	}

	/**
	 * @When user :sharer shares the Context :contextAlias to :shareeType :sharee
	 */
	public function userSharesTheContextTo(string $sharer, string $contextAlias, string $shareeType, string $sharee): void {
		$this->setCurrentUser($sharer);
		$context = $this->collectionManager->getByAlias('context', $contextAlias);

		$this->sendRequest(
			'POST',
			'/apps/tables/api/1/shares',
			[
				'nodeId' => $context['id'],
				'nodeType' => 'context',
				'receiver' => $sharee,
				'receiverType' => $shareeType,
				'displayMode' => 2,
			]
		);

		if ($this->response->getStatusCode() === 200) {
			$share = $this->getDataFromResponse($this->response);
			$this->shareId = $share['id'];

			Assert::assertEquals($share['nodeType'], 'context');
			Assert::assertEquals($share['nodeId'], $context['id']);
			Assert::assertEquals($share['receiverType'], $shareeType);
			Assert::assertEquals($share['receiver'], $sharee);
		}
	}

	/**
	 * @Then user :initiator shares view :viewAlias with :recipient
	 */
	public function shareViewWithUser(string $initiator, string $viewAlias, string $recipient): void {
		$this->setCurrentUser($initiator);
		$view = $this->collectionManager->getByAlias('view', $viewAlias);

		$permissions = [
			'permissionRead' => true,
			'permissionCreate' => true,
			'permissionUpdate' => true,
			'permissionDelete' => false,
			'permissionManage' => false
		];
		$this->sendRequest(
			'POST',
			sprintf('/apps/tables/api/1/shares'),
			array_merge($permissions, [
				'receiverType' => 'user',
				'receiver' => $recipient,
				'nodeId' => $view['id'],
				'nodeType' => 'view',
			])
		);

		if ($this->response->getStatusCode() === 200) {
			$share = $this->getDataFromResponse($this->response);
			$this->shareId = $share['id'];
		}
	}

	/**
	 * @Given the inserted row has the following values
	 */
	public function theInsertedRowHasTheFollowingValues(TableNode $columnValues) {
		$jsonBody = json_decode($this->response->getBody()->getContents(), true);
		$insertedRow = $jsonBody['ocs']['data'];

		$expected = [];
		foreach ($columnValues->getRows() as $row) {
			$columnId = $this->collectionManager->getByAlias('column', $row[0])['id'];
			$expected[$columnId] = $row[1];
		}

		foreach ($insertedRow['data'] as $entry) {
			if (!isset($expected[$entry['columnId']])) {
				throw new \Exception(sprintf('Unexpected column with ID %d was returned', $entry['columnId']));
			}
			// intentional weak comparison
			if ($expected[$entry['columnId']] != $entry['value']) {
				throw new \Exception(sprintf('Unexpected value %s for column with ID %d was returned', $entry['value'], $entry['columnId']));
			}
			unset($expected[$entry['columnId']]);
		}

		if (!empty($expected)) {
			throw new \Exception(sprintf('Some expected columns were not returned: %s ', print_r($expected, true)));
		}
	}

	/**
	 * @When using :nodeType :alias
	 */
	public function using(string $nodeType, string $alias): void {
		$node = $this->collectionManager->getByAlias($nodeType, $alias);
		$this->activeNode = [
			'type' => $nodeType,
			'alias' => $alias,
			'id' => $node['id'],
		];
		if ($nodeType === 'table') {
			//FIXME: remove $this->tableId everywhere
			$this->tableId = $node['id'];
		}
	}

	/**
	 * @Given user :user creates row :rowAlias with following values:
	 */
	public function userCreatesRowWithFollowingValues(string $user, string $rowAlias, TableNode $properties): void {
		$this->setCurrentUser($user);

		$props = [];
		foreach ($properties->getRows() as $row) {
			$columnId = $this->collectionManager->getByAlias('column', $row[0])['id'];
			$props[$columnId] = $row[1];
		}

		if (!$this->activeNode) {
			throw new \LogicException('Set an active node via "@And using table|view nodeAlias"');
		}

		$this->sendOcsRequest(
			'POST',
			sprintf('/apps/tables/api/2/%ss/%s/rows', $this->activeNode['type'], $this->activeNode['id']),
			['data' => $props]
		);

		if ($this->response->getStatusCode() === 200) {
			$newRow = $this->getDataFromResponse($this->response)['ocs']['data'];
			$this->collectionManager->register($newRow, 'row', $newRow['id'], $rowAlias);
		}
	}

	/**
	 * @When user :user updates row :rowAlias with following values:
	 */
	public function userUpdatesRowWithFollowingValues(string $user, string $rowAlias, TableNode $properties): void {
		$this->setCurrentUser($user);

		$props = [];
		foreach ($properties->getRows() as $row) {
			$columnId = $this->collectionManager->getByAlias('column', $row[0])['id'];
			$props[$columnId] = $row[1];
		}

		$row = $this->collectionManager->getByAlias('row', $rowAlias);
		$payload = ['data' => $props];
		if ($this->activeNode['type'] === 'view') {
			$payload['viewId'] = $this->activeNode['id'];
		}
		$this->sendRequest(
			'PUT',
			'/apps/tables/api/1/rows/' . $row['id'],
			$payload,
		);

		if ($this->response->getStatusCode() === 200) {
			$row = $this->getDataFromResponse($this->response);
			$this->collectionManager->update($row, 'row', $row['id']);
		}
	}

	/**
	 * @When user :user deletes row :rowAlias
	 */
	public function userDeletesRow(string $user, string $rowAlias): void {
		$this->setCurrentUser($user);

		$row = $this->collectionManager->getByAlias('row', $rowAlias);

		if ($this->activeNode['type'] === 'view') {
			$endpoint = sprintf('/apps/tables/api/1/views/%s/rows/%s', $this->activeNode['id'], $row['id']);
		} else {
			$endpoint = sprintf('/apps/tables/api/1/rows/%s', $row['id']);
		}

		$this->sendRequest('DELETE', $endpoint);

		$row = $this->getDataFromResponse($this->response);
		if ($this->response->getStatusCode() === 200) {
			$this->collectionManager->forget('row', $row['id']);
		}
	}

	/**
	 * @When the user :user fetches :nodeType :nodeAlias, it has exactly these rows :rowAliasList
	 */
	public function nodeHasExactlyThoseRows(string $user, string $nodeType, string $nodeAlias, string $rowAliasList): void {
		$this->setCurrentUser($user);
		$nodeItem = $this->collectionManager->getByAlias($nodeType, $nodeAlias);

		$this->sendRequest(
			'GET',
			sprintf('/apps/tables/api/1/%ss/%s/rows', $nodeType, $nodeItem['id']),
		);

		$allRows = $this->getDataFromResponse($this->response);
		Assert::assertEquals(200, $this->response->getStatusCode());

		$rowAliases = explode(',', $rowAliasList);
		$expectedIds = array_map(function ($rowAlias): int {
			$row = $this->collectionManager->getByAlias('row', $rowAlias);
			return $row['id'];
		}, $rowAliases);
		sort($expectedIds);

		$actualRowIds = array_map(function (array $actualRow): int {
			if ($this->collectionManager->getById('row', (int)$actualRow['id'])) {
				$this->collectionManager->update($actualRow, 'row', (int)$actualRow['id']);
			} else {
				$this->collectionManager->register($actualRow, 'row', (int)$actualRow['id']);
			}
			return (int)$actualRow['id'];
		}, $allRows);
		sort($actualRowIds);

		Assert::assertSame($expectedIds, $actualRowIds);
	}

	/**
	 * @Then :nodeType :nodeAlias has exactly these rows :rowAliasList in exactly this order
	 */
	public function nodeHasExactlyThoseSortedRows(string $nodeType, string $nodeAlias, string $rowAliasList): void {
		$nodeItem = $this->collectionManager->getByAlias($nodeType, $nodeAlias);

		$this->sendRequest(
			'GET',
			sprintf('/apps/tables/api/1/%ss/%s/rows', $nodeType, $nodeItem['id']),
		);

		$allRows = $this->getDataFromResponse($this->response);
		Assert::assertEquals(200, $this->response->getStatusCode());

		$rowAliases = explode(',', $rowAliasList);
		$expectedIds = array_map(function ($rowAlias): int {
			$row = $this->collectionManager->getByAlias('row', $rowAlias);
			return $row['id'];
		}, $rowAliases);

		$actualRowIds = array_map(function (array $actualRow): int {
			if ($this->collectionManager->getById('row', (int)$actualRow['id'])) {
				$this->collectionManager->update($actualRow, 'row', (int)$actualRow['id']);
			} else {
				$this->collectionManager->register($actualRow, 'row', (int)$actualRow['id']);
			}
			return (int)$actualRow['id'];
		}, $allRows);

		Assert::assertSame($expectedIds, $actualRowIds);
	}

	/**
	 * @When the column :columnName of row :rowAlias has the value :value
	 */
	public function columnOfRowIs(string $columnName, string $rowAlias, string $value): void {
		$row = $this->collectionManager->getByAlias('row', $rowAlias);
		$column = $this->collectionManager->getByAlias('column', $columnName);

		$expected = [
			'columnId' => $column['id'],
			'value' => $value,
		];

		Assert::assertContains($expected, $row['data']);
	}

	/**
	 * @When user :user sets filter to view :viewName
	 */
	public function setFilterOnView(string $user, string $viewName, TableNode $filters): void {
		$this->setCurrentUser($user);
		$filterArray = [];

		foreach ($filters->getRows() as $row) {
			if ($row[0] === 'column') {
				continue;
			}

			// Get the column ID for the given column name
			$columnId = $this->collectionManager->getByAlias('column', $row[0])['id'];

			// Add filter condition to array
			$filterArray[] = [
				[
					'columnId' => $columnId,
					'operator' => $row[1],
					'value' => $row[2]
				]
			];
		}

		$this->sendRequest(
			'PUT',
			'/apps/tables/api/1/views/' . $this->viewIds[$viewName],
			[ 'data' => ['filter' => json_encode($filterArray)] ]
		);

		$updatedView = $this->getDataFromResponse($this->response);
		Assert::assertEquals(200, $this->response->getStatusCode());
	}

	/**
	 * @Then view :viewName has exactly the following rows
	 *
	 * @param string $viewName
	 * @param TableNode $expectedRows
	 */
	public function viewHasExactRows(string $viewName, TableNode $expectedRows): void {
		$this->sendRequest(
			'GET',
			'/apps/tables/api/1/views/' . $this->viewIds[$viewName] . '/rows'
		);

		$actualRows = $this->getDataFromResponse($this->response);
		Assert::assertEquals(200, $this->response->getStatusCode());

		// Get column headers from first row of expected data
		$columnHeaders = $expectedRows->getRow(0);
		$expectedData = $expectedRows->getRows();
		array_shift($expectedData); // Remove header row

		$columnSettings = $this->collectionManager->getByAlias('view', $viewName)['columnSettings'];
		$columnIdToHeader = array_combine(array_column($columnSettings, 'columnId'), $columnHeaders);

		// Convert actual row data to match expected format
		$actualFormattedRows = [];
		foreach ($actualRows as $row) {
			$formattedRow = [];
			foreach ($row['data'] as $cell) {
				$columnTitle = $columnIdToHeader[$cell['columnId']];
				if ($columnTitle !== false) {
					$formattedRow[$columnTitle] = $cell['value'];
				}
			}
			$actualFormattedRows[] = $formattedRow;
		}

		// Convert expected data to associative array format
		$expectedFormattedRows = [];
		foreach ($expectedData as $row) {
			$formattedRow = [];
			foreach ($columnHeaders as $i => $header) {
				$formattedRow[$header] = $row[$i];
			}
			$expectedFormattedRows[] = $formattedRow;
		}

		Assert::assertEquals(
			count($expectedFormattedRows),
			count($actualFormattedRows),
			'Number of rows does not match expected'
		);

		foreach ($expectedFormattedRows as $i => $expectedRow) {
			Assert::assertEquals(
				$expectedRow,
				$actualFormattedRows[$i],
				'Row ' . $i . ' does not match expected values'
			);
		}
	}
}
