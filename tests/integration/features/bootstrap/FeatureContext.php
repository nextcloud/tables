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
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\ClientException;
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
	 * @Then user :user has the following tables
	 *
	 * @param string $user
	 * @param TableNode|null $body
	 */
	public function userTables(string $user, TableNode $body = null): void {
		$this->setCurrentUser($user);
		$this->sendRequest(
			'GET', '/apps/tables/api/1/tables'
		);

		$data = $this->getDataFromResponse($this->response);

		if ($body === null) {
			Assert::assertCount(0, $data);
			return;
		}

		Assert::assertCount(count($body->getHash()), $body, 'Tables count does not match');
		foreach ($body->getRows() as $row) {
			// Compare table row
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
	 */
	public function userLogsIn(string $user) {
		$loginUrl = $this->baseUrl . '/login';

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
	protected function getDataFromResponse(ResponseInterface $response) {
		$jsonBody = json_decode($response->getBody()->getContents(), true);
		return $jsonBody;
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
