<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service;

use OCA\Tables\Db\Share;
use OCA\Tables\Db\ShareMapper;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Errors\FederationDisabledError;
use OCA\Tables\Federation\FederationProxy;
use OCA\Tables\Helper\UserHelper;
use OCA\Tables\Service\ConfigService;
use OCA\Tables\Service\FederationService;
use OCP\Federation\ICloudFederationFactory;
use OCP\Federation\ICloudFederationProviderManager;
use OCP\Federation\ICloudIdManager;
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

	public function testIsNodeFederatedThrowsWhenFederationDisabled(): void {
		$this->configService->method('isFederationEnabled')->willReturn(false);
		$this->expectException(FederationDisabledError::class);
		$this->federationService->isNodeFederated(1, 'table');
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
}
