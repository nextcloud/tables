<?php

namespace OCA\Tables\Controller;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Service\ImportService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class ImportController extends Controller {

	private ImportService $service;
	private string $userId;

	use Errors;


	public function __construct(IRequest $request,
		ImportService $service,
		string $userId) {
		parent::__construct(Application::APP_ID, $request);
		$this->service = $service;
		$this->userId = $userId;
	}


	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function import(int $tableId, int $viewId, String $path, bool $createMissingColumns = true): DataResponse {
		return $this->handleError(function () use ($tableId, $viewId, $path, $createMissingColumns) {
			return $this->service->import($tableId, $viewId, $path, $createMissingColumns);
		});
	}
}
