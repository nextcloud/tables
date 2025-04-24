<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Tables\Controller;

use Exception;
use OCA\Tables\AppInfo\Application;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

abstract class AOCSController extends OCSController {

	protected LoggerInterface $logger;
	protected string $userId;
	protected IL10N $n;

	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		IL10N $n,
		string $userId,
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->logger = $logger;
		$this->userId = $userId;
		$this->n = $n;
	}

	/**
	 * @param Exception|InternalError $e
	 * @return DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 */
	protected function handleError($e): DataResponse {
		$this->logger->error('An internal error or exception occurred: ' . $e->getMessage(), ['exception' => $e]);
		return new DataResponse(['message' => $this->n->t('An unexpected error occurred. More details can be found in the logs. Please reach out to your administration.')], Http::STATUS_INTERNAL_SERVER_ERROR);
	}

	/**
	 * @param PermissionError $e
	 * @return DataResponse<Http::STATUS_FORBIDDEN, array{message: string}, array{}>
	 */
	protected function handlePermissionError(PermissionError $e): DataResponse {
		$this->logger->warning('A permission error occurred: ' . $e->getMessage(), ['exception' => $e]);
		return new DataResponse(['message' => $this->n->t('A permission error occurred. More details can be found in the logs. Please reach out to your administration.')], Http::STATUS_FORBIDDEN);
	}

	/**
	 * @param NotFoundError $e
	 * @return DataResponse<Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 */
	protected function handleNotFoundError(NotFoundError $e): DataResponse {
		$this->logger->info('A not found error occurred: ' . $e->getMessage(), ['exception' => $e]);
		return new DataResponse(['message' => $this->n->t('A not found error occurred. More details can be found in the logs. Please reach out to your administration.')], Http::STATUS_NOT_FOUND);
	}

	/**
	 * @param BadRequestError $e
	 * @return DataResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>
	 */
	protected function handleBadRequestError(BadRequestError $e): DataResponse {
		$this->logger->warning('An bad request was encountered: ' . $e->getMessage(), ['exception' => $e]);
		return new DataResponse(['message' => $e->translatedMessage ?: $e->getMessage()], Http::STATUS_BAD_REQUEST);
	}
}
