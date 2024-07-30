<?php
/**
 *
 * @copyright Copyright (c) 2023, Florian Steffens (flost-dev@mailbox.org)
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 */
require __DIR__ . '/../../vendor/autoload.php';

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
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
	private ?array $tableColumns = [];
	private ?array $importResult = null;

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
	private array $viewData = [];

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
		$this->tableId = null;
	}

	/**
	 * @AfterScenario
	 */
	public function cleanupUsers() {
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
			'/apps/tables/api/2/tables/'.$tableId,
		);

		$tableToVerify = $this->getDataFromResponse($this->response)['ocs']['data'];
		$this->tableData[$tableName] = $tableToVerify;
		$this->tableId = $tableToVerify['id'];

		return $tableToVerify;
	}

	/**
	 * @When user :user fetches the rows from :nodeType :nodeAlias
	 */
	public function userFetchesRowsFromNode(string $user, string $nodeType, string $nodeAlias) {
		$this->setCurrentUser($user);
		$node = $this->collectionManager->getByAlias($nodeType, $nodeAlias);

		$collection = $nodeType . 's';

		$this->sendRequest(
			'GET',
			sprintf('/apps/tables/api/2/%s/%d/rows', $collection, $node['id'])
		);

		$data = $this->getDataFromResponse($this->response);
		$this->collectionManager->register($data, $nodeType . '-rows', $node['id']);
		$this->collectionManager->register(['type' => $nodeType, 'item' => $node], 'currentNode', 0);
	}

	/**
	 * @Then there should be a row with following values:
	 */
	public function thereShouldBeARowWithFollowingValues(TableNode $rowValues) {
		$nodeMeta = $this->collectionManager->getById('currentNode', 0);
		$columns = $this->getColumnsForNode($nodeMeta['type'], $nodeMeta['item']['id']);

		$expectedColumns = array_combine($rowValues->getColumn(0), $rowValues->getColumn(1));
		$resultColumns = [];
		foreach ($columns as $column) {
			if (isset($expectedColumns[$column['title']])) {
				$resultColumns[] = ['columnId' => $column['id'], 'value' => $expectedColumns[$column['title']]];
				unset($expectedColumns[$column['title']]);
			}
		}
		Assert::assertCount(0, $expectedColumns, 'not all expected columns are valid');

		$actualRowData = $this->collectionManager->getById($nodeMeta['type'] . '-rows', $nodeMeta['item']['id']);
		foreach ($actualRowData as $row) {
			$found = true;
			foreach ($resultColumns as $column) {
				$found = $found && in_array($column, $row['data'], true);
			}
			if ($found) {
				Assert::assertTrue(true);
				return;
			}
		}

		Assert::assertTrue(false, 'Expected row was not found');
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
			'/apps/tables/api/2/tables/'.$this->tableIds[$tableName],
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
			'/apps/tables/api/2/tables/'.$this->tableIds[$tableName],
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
			'/apps/tables/api/2/tables/'.$updatedTable['id'],
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
			'/apps/tables/api/2/tables/'.$this->tableIds[$tableName].'/transfer',
			$data
		);

		$updatedTable = $this->getDataFromResponse($this->response)['ocs']['data'];

		Assert::assertEquals(200, $this->response->getStatusCode());
		Assert::assertEquals($updatedTable['ownership'], $newUser);
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
			'/apps/tables/api/2/tables/'.$this->tableIds[$tableName]
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
			'/apps/tables/api/2/tables/'.$this->tableIds[$tableName]
		);

		$deletedTable = $this->getDataFromResponse($this->response)['ocs']['data'];
		Assert::assertEquals(200, $this->response->getStatusCode());
		Assert::assertEquals($deletedTable['id'], $this->tableIds[$tableName]);

		$this->sendOcsRequest(
			'GET',
			'/apps/tables/api/2/tables/'.$deletedTable['id'],
		);
		Assert::assertEquals(404, $this->response->getStatusCode());

		unset($this->tableIds[$tableName]);
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
		if($nodeType === 'table') {
			$props['baseNodeId'] = $this->tableIds[$nodeName];
		}
		if($nodeType === 'view') {
			$props['baseNodeId'] = $this->viewIds[$nodeName];
		}
		$title = null;
		foreach ($properties->getRows() as $row) {
			if($row[0] === 'title') {
				$title = $row[1];
			}
			$props[$row[0]] = $row[1];
		}

		$this->sendOcsRequest(
			'POST',
			'/apps/tables/api/2/columns/'.$columnType,
			$props
		);

		$newColumn = $this->getDataFromResponse($this->response)['ocs']['data'];
		$this->columnIds[$columnName] = $newColumn['id'];

		Assert::assertEquals(200, $this->response->getStatusCode());

		$this->sendOcsRequest(
			'GET',
			'/apps/tables/api/2/columns/'.$newColumn['id'],
		);

		$columnToVerify = $this->getDataFromResponse($this->response)['ocs']['data'];
		Assert::assertEquals(200, $this->response->getStatusCode());
		Assert::assertEquals($columnToVerify['title'], $title);
	}

	protected function getColumnsForNode(string $nodeType, string $nodeAlias): array {
		$nodeId = null;
		if($nodeType === 'table') {
			$nodeId = $this->tableIds[$nodeAlias];
		} else if($nodeType === 'view') {
			$nodeId = $this->viewIds[$nodeAlias];
		}

		$this->sendOcsRequest(
			'GET',
			sprintf('/apps/tables/api/2/columns/%s/%s', $nodeType, $nodeId),
		);

		return $this->getDataFromResponse($this->response)['ocs']['data'];
	}

	/**
	 * @Then node with node type :nodeType and node name :nodeName has the following columns via v2
	 *
	 * @param string $nodeType
	 * @param string $nodeName
	 * @param TableNode|null $body
	 */
	public function columnsForNodeV2(string $nodeType, string $nodeName, ?TableNode $body = null): void {
		$data = $this->getColumnsForNode($nodeType, $nodeName);
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





	// IMPORT --------------------------

	/**
	 * @Given file :file exists for user :user with following data
	 *
	 * @param string $user
	 * @param string $file
	 * @param TableNode|null $table
	 */
	public function createCsvFile(string $user, string $file, ?TableNode $table = null): void {
		$this->setCurrentUser($user);
		$url = $this->baseUrl.'remote.php/dav/files/'.$user.$file;
		$body = $this->tableNodeToCsv($table);
		$headers = ['Content-Type' => 'text/csv'];

		$this->sendRequestFullUrl('PUT', $url, $body, $headers, []);

		Assert::assertEquals(201, $this->response->getStatusCode());
	}

	private function tableNodeToCsv(TableNode $node): string {
		$out = '';
		foreach ($node->getRows() as $row) {
			foreach ($row as $value) {
				if($out !== '' && substr($out, -1) !== "\n") {
					$out .= ",";
				}
				$out .= trim($value);
			}
			$out .= "\n";
		}
		return $out;
	}

	/**
	 * @When user imports file :file into last created table
	 *
	 * @param string $file
	 */
	public function importTable(string $file): void {
		$this->sendRequest(
			'POST',
			'/apps/tables/api/1/import/table/'.$this->tableId,
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
			'/apps/tables/api/1/tables/'.$this->tableId.'/rows/simple',
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
				Assert::assertTrue(in_array($item, $allValuesForColumn));
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
			'/apps/tables/api/1/tables/'.$this->tableIds[$tableName].'/views'
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
			'/apps/tables/api/1/tables/'.$this->tableIds[$tableName].'/views',
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
			'/apps/tables/api/1/views/'.$newItem['id'],
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

		Assert::assertEquals(200, $this->response->getStatusCode());
		Assert::assertEquals($newTable['title'], $title);
		Assert::assertEquals($newTable['emoji'], $emoji);
		Assert::assertEquals($newTable['ownership'], $user);

		$this->sendRequest(
			'GET',
			'/apps/tables/api/1/tables/'.$newTable['id'],
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
			'/apps/tables/api/1/tables?keyword='.$keyword
		);

		$tables = $this->getDataFromResponse($this->response);
		return $tables[0];
	}

	private function getTableById(int $tableId): array {
		$this->sendRequest(
			'GET',
			'/apps/tables/api/1/tables/'.$tableId
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
			'/apps/tables/api/1/tables/'.$table['id'],
			$data
		);

		$updatedTable = $this->getDataFromResponse($this->response);

		Assert::assertEquals(200, $this->response->getStatusCode());
		Assert::assertEquals($updatedTable['title'], $title);
		Assert::assertEquals($updatedTable['emoji'], $emoji);
		Assert::assertEquals($updatedTable['ownership'], $user);

		$this->sendRequest(
			'GET',
			'/apps/tables/api/1/tables/'.$updatedTable['id'],
		);

		$tableToVerify = $this->getDataFromResponse($this->response);
		Assert::assertEquals(200, $this->response->getStatusCode());
		Assert::assertEquals($tableToVerify['title'], $title);
		Assert::assertEquals($tableToVerify['emoji'], $emoji);
		Assert::assertEquals($tableToVerify['ownership'], $user);
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

		$this->sendRequest(
			'PUT',
			'/apps/tables/api/1/views/'.$this->viewIds[$viewName],
			[ 'data' => $data ]
		);

		$updatedItem = $this->getDataFromResponse($this->response);

		Assert::assertEquals(200, $this->response->getStatusCode());
		Assert::assertEquals($updatedItem['title'], $title);
		Assert::assertEquals($updatedItem['emoji'], $emoji);

		$this->sendRequest(
			'GET',
			'/apps/tables/api/1/views/'.$updatedItem['id'],
		);

		$itemToVerify = $this->getDataFromResponse($this->response);
		Assert::assertEquals(200, $this->response->getStatusCode());
		Assert::assertEquals($itemToVerify['title'], $title);
		Assert::assertEquals($itemToVerify['emoji'], $emoji);
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
			'/apps/tables/api/1/views/'.$deletedItem['id'],
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
			'/apps/tables/api/1/views/'.$this->viewIds[$viewName]
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
			'/apps/tables/api/1/tables/'.$table['id']
		);
		$deletedTable = $this->getDataFromResponse($this->response);

		Assert::assertEquals(200, $this->response->getStatusCode());
		Assert::assertEquals($deletedTable['title'], $table['title']);
		Assert::assertEquals($deletedTable['id'], $table['id']);

		$this->sendRequest(
			'GET',
			'/apps/tables/api/1/tables/'.$deletedTable['id'],
		);
		Assert::assertEquals(404, $this->response->getStatusCode());

		$this->tableId = null;
		$this->columnId = null;
		$this->tableColumns = [];
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
	 * @Then user :user shares table with user :receiver
	 *
	 * @param string $user
	 * @param string $receiver
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
			'/apps/tables/api/1/tables/'.$table['id'].'/shares',
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
			'/apps/tables/api/1/shares/'.$share['id'],
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
			'/apps/tables/api/1/tables/'.$table['id'].'/shares',
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
			'/apps/tables/api/1/shares/'.$share['id'],
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
			'/apps/tables/api/1/shares/'.$shareId
		);

		return $this->getDataFromResponse($this->response);
	}

	/**
	 * @Then user :user has the following permissions
	 */
	public function checkSharePermissions($user, ?TableNode $permissions = null) {
		$this->setCurrentUser($user);

		$share = $this->getShareById($this->shareId);
		$table = $this->getTableById($share['nodeId']);

		foreach ($permissions->getRows() as $row) {
			Assert::assertEquals($table['onSharePermissions'][$row[0]], boolval($row[1]));
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
		Assert::assertEquals($share['permission'.ucfirst($permissionType)], $value);
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
		foreach ($properties->getRows() as $row) {
			$props[$row[0]] = $row[1];
		}

		$this->sendRequest(
			'POST',
			'/apps/tables/api/1/tables/'.$this->tableId.'/columns',
			$props
		);

		$newColumn = $this->getDataFromResponse($this->response);
		$this->columnId = $newColumn['id'];
		$this->tableColumns[$newColumn['title']] = $newColumn['id'];

		Assert::assertEquals(200, $this->response->getStatusCode());
		Assert::assertEquals($newColumn['title'], $title);

		$this->sendRequest(
			'GET',
			'/apps/tables/api/1/columns/'.$newColumn['id'],
		);

		$columnToVerify = $this->getDataFromResponse($this->response);
		Assert::assertEquals(200, $this->response->getStatusCode());
		Assert::assertEquals($columnToVerify['title'], $title);
	}

	/**
	 * @Then table has at least following columns
	 *
	 * @param TableNode|null $body
	 */
	public function tableColumns(?TableNode $body = null): void {
		$this->sendRequest(
			'GET',
			'/apps/tables/api/1/tables/'.$this->tableId.'/columns'
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
	 * @Then user deletes last created column
	 */
	public function deleteColumn(): void {
		$this->sendRequest(
			'DELETE',
			'/apps/tables/api/1/columns/'.$this->columnId
		);
		$column = $this->getDataFromResponse($this->response);

		Assert::assertEquals(200, $this->response->getStatusCode());
		Assert::assertEquals($column['id'], $this->columnId);

		$this->sendRequest(
			'GET',
			'/apps/tables/api/1/columns/'.$column['id'],
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
			'/apps/tables/api/1/columns/'.$this->columnId,
			$props
		);

		$column = $this->getDataFromResponse($this->response);

		Assert::assertEquals(200, $this->response->getStatusCode());
		foreach ($props as $key => $value) {
			Assert::assertEquals($column[$key], $value);
		}

		$this->sendRequest(
			'GET',
			'/apps/tables/api/1/columns/'.$column['id'],
		);

		$columnToVerify = $this->getDataFromResponse($this->response);
		Assert::assertEquals(200, $this->response->getStatusCode());
		foreach ($props as $key => $value) {
			Assert::assertEquals($columnToVerify[$key], $value);
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
			$columnId = $this->tableColumns[$row[0]];
			$props[$columnId] = $row[1];
		}

		$this->sendRequest(
			'POST',
			'/apps/tables/api/1/tables/'.$this->tableId.'/rows',
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
			'/apps/tables/api/1/rows/'.$newRow['id'],
		);

		$rowToVerify = $this->getDataFromResponse($this->response);
		Assert::assertEquals(200, $this->response->getStatusCode());
		foreach ($rowToVerify['data'] as $cell) {
			Assert::assertEquals($props[$cell['columnId']], $cell['value']);
		}
	}

	/**
	 * @When user :user tries to create a row using v2 on :nodeType :nodeAlias with following values
	 *
	 * @param TableNode $properties
	 */
	public function userTriesToCreateRowUsingV2OnNodeXWithFollowingValues(string $user, string $nodeType, string $nodeAlias, TableNode $properties): void {
		$this->setCurrentUser($user);
		// FIXME: tables are not in collectionManager yet
		$node = $this->collectionManager->getByAlias($nodeType, $nodeAlias);

		$props = [];
		foreach ($properties->getRows() as $row) {
			$columnId = $this->tableColumns[$row[0]];
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
			$columnId = $this->tableColumns[$row[0]];
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
			$columnId = $this->tableColumns[$row[0]];
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
			$columnId = $this->tableColumns[$row[0]];
			$props[$columnId] = $row[1];
		}

		$this->sendRequest(
			'POST',
			'/apps/tables/api/1/tables/'.$this->tableId.'/rows',
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
			'/apps/tables/api/1/rows/'.$newRow['id'],
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
			'/apps/tables/api/1/rows/'.$this->rowId
		);
		$row = $this->getDataFromResponse($this->response);

		Assert::assertEquals(200, $this->response->getStatusCode());
		Assert::assertEquals($row['id'], $this->rowId);

		$this->sendRequest(
			'GET',
			'/apps/tables/api/1/rows/'.$row['id'],
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
			$columnId = $this->tableColumns[$row[0]];
			$props[$columnId] = $row[1];
		}

		$this->sendRequest(
			'PUT',
			'/apps/tables/api/1/rows/'.$this->rowId,
			['data' => $props]
		);

		$row = $this->getDataFromResponse($this->response);

		Assert::assertEquals(200, $this->response->getStatusCode());
		foreach ($row['data'] as $cell) {
			Assert::assertEquals($props[$cell['columnId']], $cell['value']);
		}

		$this->sendRequest(
			'GET',
			'/apps/tables/api/1/rows/'.$row['id'],
		);

		$rowToVerify = $this->getDataFromResponse($this->response);
		Assert::assertEquals(200, $this->response->getStatusCode());
		foreach ($rowToVerify['data'] as $cell) {
			Assert::assertEquals($props[$cell['columnId']], $cell['value']);
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
			$columnId = $this->tableColumns[$row[0]];
			$props[$columnId] = $row[1];
		}

		$this->sendRequest(
			'PUT',
			'/apps/tables/api/1/rows/'.$this->rowId,
			['data' => json_encode($props)]
		);

		$row = $this->getDataFromResponse($this->response);

		Assert::assertEquals(200, $this->response->getStatusCode());
		foreach ($row['data'] as $cell) {
			Assert::assertEquals($props[$cell['columnId']], $cell['value']);
		}

		$this->sendRequest(
			'GET',
			'/apps/tables/api/1/rows/'.$row['id'],
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

	/**
	 * @Given /^as user "([^"]*)"$/
	 * @param string $user
	 */
	public function setCurrentUser($user) {
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
			'/apps/tables/api/2/favorites/' . $nodeType. '/' . $tableId,
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
			'/apps/tables/api/2/favorites/' . $nodeType. '/' . $tableId,
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
				case "name":
					Assert::assertEquals($value, $actualData['name']);
					break;
				case "icon":
					Assert::assertEquals($value, $actualData['iconName']);
					break;
				case "node":
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
				case "page":
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
				case "name":
					Assert::assertNotEquals($value, $actualData['name']);
					break;
				case "icon":
					Assert::assertNotEquals($value, $actualData['iconName']);
					break;
				case "node":
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
				case "page":
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
}
