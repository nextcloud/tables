<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Federation;

use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Federation\FederationProxy;
use OCP\AppFramework\Http;
use OCP\Federation\ICloudFederationFactory;
use OCP\Federation\ICloudFederationProviderManager;
use OCP\Federation\ICloudIdManager;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IConfig;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use OCP\Security\Signature\ISignatoryManager;
use OCP\Security\Signature\ISignatureManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class FederationProxyTest extends TestCase {
	private FederationProxy $proxy;

	protected function setUp(): void {
		parent::setUp();
		$this->proxy = new FederationProxy(
			$this->createMock(IClientService::class),
			$this->createMock(IUserSession::class),
			$this->createMock(IConfig::class),
			$this->createMock(IFactory::class),
			$this->createMock(LoggerInterface::class),
			$this->createMock(ICloudFederationProviderManager::class),
			$this->createMock(ICloudFederationFactory::class),
			$this->createMock(ICloudIdManager::class),
			$this->createMock(ISignatureManager::class),
			$this->createMock(ISignatoryManager::class),
		);
	}

	private function response(int $status, array $data): IResponse {
		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')->willReturn($status);
		$response->method('getBody')->willReturn(json_encode(['ocs' => ['meta' => ['statuscode' => $status], 'data' => $data]]));
		return $response;
	}

	public function testReturnsTheOcsDataOfAnAllowedStatus(): void {
		$this->assertSame(['id' => 12], $this->proxy->getOCSData($this->response(Http::STATUS_OK, ['id' => 12])));
	}

	public function testANotFoundFromTheRemoteIsRaisedAsNotFoundError(): void {
		$this->expectException(NotFoundError::class);
		$this->proxy->getOCSData($this->response(Http::STATUS_NOT_FOUND, ['message' => 'Row not found.']));
	}

	public function testAForbiddenFromTheRemoteIsRaisedAsPermissionError(): void {
		$this->expectException(PermissionError::class);
		$this->proxy->getOCSData($this->response(Http::STATUS_FORBIDDEN, ['message' => 'No permissions']));
	}

	public function testANotFoundThatTheCallerAllowsIsReturned(): void {
		$this->assertSame(
			['message' => 'Row not found.'],
			$this->proxy->getOCSData($this->response(Http::STATUS_NOT_FOUND, ['message' => 'Row not found.']), [Http::STATUS_OK, Http::STATUS_NOT_FOUND]),
		);
	}
}
