<?php

namespace OCA\Tables\Middleware;

use InvalidArgumentException;
use OCA\Tables\Controller\AEnvironmentAwareOCSController;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Middleware\Attribute\RequireTable;
use OCA\Tables\Service\TableService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\OCS\OCSException;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;
use ReflectionAttribute;
use ReflectionMethod;

class InjectionMiddleware extends Middleware {
	public function __construct(
		protected IRequest $request,
		protected IURLGenerator $urlGenerator,
		protected IUserSession $userSession,
		protected LoggerInterface $logger,
		protected IL10N $l,
		protected TableService $tableService,
	) {
	}

	/**
	 * @throws PermissionError
	 * @throws \ReflectionException
	 * @throws NotFoundError
	 * @throws InternalError
	 */
	public function beforeController($controller, $methodName): void {
		if (!$controller instanceof AEnvironmentAwareOCSController) {
			return;
		}

		$reflectionMethod = new ReflectionMethod($controller, $methodName);
		$attributes = $reflectionMethod->getAttributes(RequireTable::class);
		if (!empty($attributes)) {
			/** @var ReflectionAttribute $attribute */
			$attribute = current($attributes);
			/** @var RequireTable $requireTableAttribute */
			$requireTableAttribute = $attribute->newInstance();
			$this->getTable($controller, $requireTableAttribute->enhance());
		}
	}

	public function afterException($controller, $methodName, $exception): Response {
		if ($exception instanceof InvalidArgumentException) {
			throw new OCSException($exception->getMessage(), Http::STATUS_BAD_REQUEST, $exception);
		}

		$loggerOptions = [
			'errorCode' => $exception->getCode(),
			'errorMessage' => $exception->getMessage(),
			'exception' => $exception,
		];

		if ($exception instanceof NotFoundError) {
			$this->logger->warning('A not found error occurred: [{errorCode}] {errorMessage}', $loggerOptions);
			return new DataResponse(['message' => $this->l->t('A not found error occurred. More details can be found in the logs. Please reach out to your administration.')], Http::STATUS_NOT_FOUND);
		}
		if ($exception instanceof InternalError) {
			$this->logger->warning('An internal error or exception occurred: [{errorCode}] {errorMessage}', $loggerOptions);
			return new DataResponse(['message' => $this->l->t('An unexpected error occurred. More details can be found in the logs. Please reach out to your administration.')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		if ($exception instanceof PermissionError) {
			$this->logger->warning('A permission error occurred: [{errorCode}] {errorMessage}', $loggerOptions);
			return new DataResponse(['message' => $this->l->t('A permission error occurred. More details can be found in the logs. Please reach out to your administration.')], Http::STATUS_FORBIDDEN);
		}

		$this->logger->warning('A unknown error occurred: [{errorCode}] {errorMessage}', $loggerOptions);
		return new RedirectResponse($this->urlGenerator->linkToDefaultPageUrl());
	}

	/**
	 * @throws NotFoundError
	 * @throws PermissionError
	 * @throws InternalError
	 */
	protected function getTable(AEnvironmentAwareOCSController $controller, bool $enhance): void {
		$tableId = $this->request->getParam('id');
		if ($tableId === null) {
			throw new InvalidArgumentException('Missing table ID.');
		}

		$userId = $this->userSession->getUser()?->getUID();

		$controller->setTable($this->tableService->find($tableId, !$enhance, $userId));
	}
}
