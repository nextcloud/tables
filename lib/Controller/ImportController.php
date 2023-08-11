<?php

namespace OCA\Tables\Controller;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Service\ImportService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class ImportController extends Controller {

	private ImportService $service;
	private string $userId;

	protected LoggerInterface $logger;

	use Errors;


	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		ImportService $service,
		string $userId) {
		parent::__construct(Application::APP_ID, $request);
		$this->logger = $logger;
		$this->service = $service;
		$this->userId = $userId;
	}


	/**
	 * @NoAdminRequired
	 */
	public function importInTable(int $tableId, String $path, bool $createMissingColumns = true): DataResponse {
		return $this->handleError(function () use ($tableId, $path, $createMissingColumns) {
			return $this->service->import($tableId, null, $path, $createMissingColumns);
		});
	}

	/**
	 * @NoAdminRequired
	 */
	public function importInView(int $viewId, String $path, bool $createMissingColumns = true): DataResponse {
		return $this->handleError(function () use ($viewId, $path, $createMissingColumns) {
			return $this->service->import(null, $viewId, $path, $createMissingColumns);
		});
	}
}
