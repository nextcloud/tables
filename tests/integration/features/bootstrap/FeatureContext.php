<?php
/**
 * @author Joas Schilling <coding@schilljs.com>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 *
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */
require __DIR__ . '/../../vendor/autoload.php';

use Behat\Behat\Context\Context;
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Gherkin\Node\TableNode;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;

/**
 * Defines application features from the specific context.
 */
class FeatureContext implements Context, SnippetAcceptingContext {
	public const TEST_PASSWORD = '123456';

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

	use CommandLineTrait;

	/**
	 * FeatureContext constructor.
	 */
	public function __construct() {
		$this->cookieJars = [];
		$this->baseUrl = getenv('TEST_SERVER_URL');
	}

	/**
	 * @BeforeScenario
	 */
	public function setUp() {
		$this->createdUsers = [];
		$this->createdGroups = [];
	}

	/**
	 * @AfterScenario
	 */
	public function cleanupUsers() {
		foreach ($this->createdUsers as $user) {
			$this->deleteUser($user);
		}
		foreach ($this->createdGroups as $group) {
			$this->deleteGroup($group);
		}
	}



	// IMPORT --------------------------

	/**
	 * @Given file :file exists for user :user with following data
	 *
	 * @param string $user
	 * @param string $file
	 * @param TableNode|null $table
	 */
	public function createCsvFile(string $user, string $file, TableNode $table = null): void {
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
	public function userTables(string $user, TableNode $body = null): void {
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
			Assert::assertTrue(in_array($tableTitle, $titles, true));
		}
	}

	/**
	 * @Given table :table with emoji :emoji exists for user :user
	 *
	 * @param string $user
	 * @param string $title
	 * @param string|null $emoji
	 */
	public function createTable(string $user, string $title, string $emoji = null): void {
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
	public function checkSharePermissions($user, TableNode $permissions = null) {
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
			'/apps/tables/api/1/shares/'.$this->shareId,
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
	public function createColumn(string $title, TableNode $properties = null): void {
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
	public function tableColumns(TableNode $body = null): void {
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
	public function updateColumn(TableNode $properties = null): void {
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
	public function createRow(TableNode $properties = null): void {
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
	public function updateRow(TableNode $properties = null): void {
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
}
