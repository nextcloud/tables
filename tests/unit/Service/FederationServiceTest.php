<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service;

use OCA\Tables\Db\Share;
use OCA\Tables\Db\ShareMapper;
use OCA\Tables\Db\Table;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Errors\FederationDisabledError;
use OCA\Tables\Errors\FederationRequestError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Federation\FederationProxy;
use OCA\Tables\Helper\UserHelper;
use OCA\Tables\Service\ConfigService;
use OCA\Tables\Service\FederationService;
use OCP\Federation\ICloudFederationFactory;
use OCP\Federation\ICloudFederationProviderManager;
use OCP\Federation\ICloudId;
use OCP\Federation\ICloudIdManager;
use OCP\Http\Client\IResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class FederationServiceTest extends TestCase {
	private FederationProxy $proxy;
	private ICloudIdManager $cloudIdManager;
	private LoggerInterface $logger;
	private ShareMapper $shareMapper;
	private TableMapper $tableMapper;
	private ViewMapper $viewMapper;
	private UserHelper $userHelper;
	private ICloudFederationProviderManager $federationProviderManager;
	private ICloudFederationFactory $federationFactory;
	private ConfigService $configService;
	private FederationService $federationService;

	protected function setUp(): void {
		parent::setUp();
		$this->proxy = $this->createMock(FederationProxy::class);
		$this->cloudIdManager = $this->createMock(ICloudIdManager::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->shareMapper = $this->createMock(ShareMapper::class);
		$this->tableMapper = $this->createMock(TableMapper::class);
		$this->viewMapper = $this->createMock(ViewMapper::class);
		$this->userHelper = $this->createMock(UserHelper::class);
		$this->federationProviderManager = $this->createMock(ICloudFederationProviderManager::class);
		$this->federationFactory = $this->createMock(ICloudFederationFactory::class);
		$this->configService = $this->createMock(ConfigService::class);

		$this->federationService = new FederationService(
			$this->proxy,
			$this->cloudIdManager,
			$this->logger,
			$this->shareMapper,
			$this->tableMapper,
			$this->viewMapper,
			$this->userHelper,
			$this->federationProviderManager,
			$this->federationFactory,
			$this->configService,
		);
	}

	public function testIsNodeFederatedReturnsFalseWhenFederationDisabled(): void {
		$this->configService->method('isFederationEnabled')->willReturn(false);
		$this->assertFalse($this->federationService->isNodeFederated(1, 'table'));
	}

	public function testIsNodeFederatedReturnsTrueForFederatedTable(): void {
		$this->configService->method('isFederationEnabled')->willReturn(true);
		$this->tableMapper->method('isFederated')->with(1)->willReturn(true);
		$this->assertTrue($this->federationService->isNodeFederated(1, 'table'));
	}

	public function testSendShareThrowsWhenOutgoingFederationDisabled(): void {
		$this->configService->method('isOutgoingFederationEnabled')->willReturn(false);

		$share = new Share();
		$share->setNodeId(1);
		$share->setNodeType('table');
		$share->setReceiver('admin@nextcloud2.local');
		$share->setSender('admin');

		$this->expectException(FederationDisabledError::class);
		$this->federationService->sendShare($share);
	}

	public static function remoteCalls(): array {
		return [
			'columns' => [static fn (FederationService $service, Table $table) => $service->getColumns($table)],
			'rows' => [static fn (FederationService $service, Table $table) => $service->getRows($table)],
			'create row' => [static fn (FederationService $service, Table $table) => $service->createRow($table, [])],
			'update row' => [static fn (FederationService $service, Table $table) => $service->updateRow($table, 3, [])],
			'delete row' => [static fn (FederationService $service, Table $table) => $service->deleteRow($table, 3)],
		];
	}

	/**
	 * @dataProvider remoteCalls
	 */
	#[DataProvider('remoteCalls')]
	public function testANotFoundFromTheRemoteIsNotLoggedAsAnError(callable $call): void {
		$this->assertRemoteFailureIsLogged($call, new NotFoundError('Remote answered with status 404'), 'debug');
	}

	/**
	 * @dataProvider remoteCalls
	 */
	#[DataProvider('remoteCalls')]
	public function testAForbiddenFromTheRemoteIsNotLoggedAsAnError(callable $call): void {
		$this->assertRemoteFailureIsLogged($call, new PermissionError('Remote answered with status 403'), 'debug');
	}

	/**
	 * @dataProvider remoteCalls
	 */
	#[DataProvider('remoteCalls')]
	public function testAFailedRemoteRequestIsLoggedAsAnError(callable $call): void {
		$this->assertRemoteFailureIsLogged($call, new FederationRequestError('Error parsing JSON response'), 'error');
	}

	private function assertRemoteFailureIsLogged(callable $call, \Exception $failure, string $level): void {
		$cloudId = $this->createMock(ICloudId::class);
		$cloudId->method('getRemote')->willReturn('https://remote.example');
		$this->cloudIdManager->method('resolveCloudId')->willReturn($cloudId);
		$response = $this->createMock(IResponse::class);
		foreach (['get', 'post', 'put', 'delete'] as $verb) {
			$this->proxy->method($verb)->willReturn($response);
		}
		$this->proxy->method('getOCSData')->with($response)->willThrowException($failure);
		$this->logger->expects($level === 'error' ? $this->once() : $this->never())->method('error');
		$this->logger->expects($level === 'debug' ? $this->once() : $this->never())->method('debug')
			->with($this->anything(), ['exception' => $failure]);

		$table = new Table();
		$table->setOwnership('alice@remote.example');
		$table->setShareToken('token');

		$this->expectExceptionObject($failure);
		$call($this->federationService, $table);
	}
}
