<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Middleware;

use InvalidArgumentException;
use OCA\Tables\Constants\ShareReceiverType;
use OCA\Tables\Db\Share;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Middleware\Attribute\AssertShareAccessIsAccessible;
use OCA\Tables\Service\ConfigService;
use OCA\Tables\Service\ShareService;
use OCA\Tables\Service\ValueObject\ShareToken;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\PublicShareController;
use OCP\Federation\ICloudIdManager;
use OCP\IRequest;
use OCP\ISession;
use OCP\OCM\IOCMDiscoveryService;
use ReflectionMethod;

class ShareControlMiddleware extends Middleware {
	private Share $share;

	public function __construct(
		private readonly IRequest $request,
		private readonly ShareService $shareService,
		private readonly ISession $session,
		private readonly IOCMDiscoveryService $ocmDiscoveryService,
		private readonly ICloudIdManager $cloudIdManager,
		private readonly ConfigService $configService,
	) {
	}

	/**
	 * @throws \ReflectionException
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	public function beforeController(Controller $controller, string $methodName): void {
		$reflectionMethod = new ReflectionMethod($controller, $methodName);
		$shallAssertShareAccessible = $reflectionMethod->getAttributes(AssertShareAccessIsAccessible::class);
		if ($shallAssertShareAccessible) {
			$tokenInput = $this->request->getParam('token', '');
			$this->assertShareTokenIsValidAndExisting($tokenInput);
			$this->assertIsAccessible($tokenInput);
		}
	}

	/**
	 * @throws PermissionError
	 * @see PublicShareController
	 */
	private function assertIsAccessible(string $tokenInput): void {
		if ($this->share->getPassword() === null || $this->share->getPassword() === '') {
			return;
		}

		$allowedTokensJSON = $this->session->get(PublicShareController::DAV_AUTHENTICATED_FRONTEND) ?? '[]';
		$allowedTokens = json_decode($allowedTokensJSON, true);
		if (!is_array($allowedTokens)) {
			$allowedTokens = [];
		}

		if (($allowedTokens[$tokenInput] ?? '') !== $this->share->getPassword()) {
			throw new PermissionError('Share cannot be accessed');
		}
	}

	/**
	 * @throws NotFoundError
	 * @throws InvalidArgumentException
	 * @throws PermissionError
	 */
	public function assertShareTokenIsValidAndExisting(string $tokenInput): void {
		$shareToken = new ShareToken($tokenInput);
		$this->share = $this->shareService->findByToken($shareToken);

		if ($this->share->getReceiverType() === ShareReceiverType::REMOTE) {
			$this->assertFederationShare();
		}

		$this->shareService->assertPublicShareAccessible($this->share);
	}

	public function afterException($controller, $methodName, \Exception $exception): DataResponse {
		if ($exception instanceof InvalidArgumentException) {
			return new DataResponse(['message' => $exception->getMessage()], Http::STATUS_BAD_REQUEST);
		}
		if ($exception instanceof NotFoundError) {
			return new DataResponse(['message' => $exception->getMessage()], Http::STATUS_NOT_FOUND);
		}
		if ($exception instanceof PermissionError) {
			return new DataResponse(['message' => $exception->getMessage()], Http::STATUS_FORBIDDEN);
		}
		throw $exception;
	}

	private function assertFederationShare(): void {
		if (!$this->configService->isFederationEnabled()) {
			throw new PermissionError('Federation is disabled');
		}

		$signedRequest = $this->ocmDiscoveryService->getIncomingSignedRequest();
		if ($signedRequest === null) {
			throw new PermissionError('Federation requests must be signed');
		}

		$cloudId = $this->cloudIdManager->resolveCloudId($this->share->getReceiver());
		$expectedOrigin = parse_url($cloudId->getRemote(), PHP_URL_HOST);
		if ($signedRequest->getOrigin() !== $expectedOrigin) {
			throw new PermissionError('Unauthorized federation request origin');
		}
	}
}
