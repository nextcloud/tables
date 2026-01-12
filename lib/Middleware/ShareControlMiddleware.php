<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Middleware;

use InvalidArgumentException;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Middleware\Attribute\AssertShareToken;
use OCA\Tables\Service\ShareService;
use OCA\Tables\Service\ValueObject\ShareToken;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Middleware;
use OCP\IRequest;
use ReflectionMethod;

class ShareControlMiddleware extends Middleware {
	public function __construct(
		protected IRequest $request,
		protected ShareService $shareService,
	) {
	}

	public function beforeController(Controller $controller, string $methodName): void {
		$reflectionMethod = new ReflectionMethod($controller, $methodName);
		$shallAssertShareToken = $reflectionMethod->getAttributes(AssertShareToken::class);
		if ($shallAssertShareToken) {
			$this->assertShareTokenIsValidAndExisting($this->request->getParam('token', ''));
		}

	}

	/**
	 * @throws NotFoundError
	 * @throws InvalidArgumentException
	 */
	public function assertShareTokenIsValidAndExisting(string $tokenInput): void {
		$shareToken = new ShareToken($tokenInput);
		$this->shareService->findByToken($shareToken);
	}

	public function afterException($controller, $methodName, \Exception $exception): DataResponse {
		if ($exception instanceof InvalidArgumentException) {
			return new DataResponse(['message' => $exception->getMessage()], Http::STATUS_BAD_REQUEST);
		}
		if ($exception instanceof NotFoundError) {
			return new DataResponse(['message' => $exception->getMessage()], Http::STATUS_NOT_FOUND);
		}
		throw $exception;
	}
}
