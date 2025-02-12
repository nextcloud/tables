<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/** @noinspection DuplicatedCode */

namespace OCA\Tables\Controller;

use Exception;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\ResponseDefinitions;
use OCA\Tables\Service\TableService;
use OCA\Tables\Service\ViewService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type TablesIndex from ResponseDefinitions
 */
class ApiGeneralController extends AOCSController {
	private TableService $tableService;
	private ViewService $viewService;

	public function __construct(
		IRequest $request,
		TableService $tableService,
		ViewService $viewService,
		LoggerInterface $logger,
		IL10N $n,
		?string $userId,
	) {
		parent::__construct($request, $logger, $n, $userId);
		$this->tableService = $tableService;
		$this->viewService = $viewService;
	}


	/**
	 * [api v2] Returns all main resources
	 *
	 * Tables and views incl. shares
	 *
	 * @return DataResponse<Http::STATUS_OK, TablesIndex, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Index returned
	 */
	#[NoAdminRequired]
	public function index(): DataResponse {
		try {
			$tables = $this->tableService->formatTables($this->tableService->findAll($this->userId));
			$views = $this->viewService->formatViews($this->viewService->findSharedViewsWithMe($this->userId));
			return new DataResponse([ 'tables' => $tables, 'views' => $views ]);
		} catch (InternalError|Exception $e) {
			$this->logger->error('An internal error or exception occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
