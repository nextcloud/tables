<?php

namespace OCA\Tables\Controller;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Service\TableService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class TableController extends Controller {
	private TableService $service;

	private string $userId;

	protected LoggerInterface $logger;

	use Errors;


	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		TableService $service,
		string $userId) {
		parent::__construct(Application::APP_ID, $request);
		$this->logger = $logger;
		$this->service = $service;
		$this->userId = $userId;
	}


	/**
	 * @NoAdminRequired
	 */
	public function index(): DataResponse {
		return $this->handleError(function () {
			return $this->service->findAll($this->userId);
		});
	}

	/**
	 * @NoAdminRequired
	 */
	public function show(int $id): DataResponse {
		return $this->handleError(function () use ($id) {
			return $this->service->find($id);
		});
	}

	/**
	 * @NoAdminRequired
	 */
	public function create(string $title, string $template, string $emoji): DataResponse {
		return $this->handleError(function () use ($title, $template, $emoji) {
			return $this->service->create($title, $template, $emoji);
		});
	}

	/**
	 * @NoAdminRequired
	 */
	public function destroy(int $id): DataResponse {
		return $this->handleError(function () use ($id) {
			return $this->service->delete($id);
		});
	}

	/**
	 * @NoAdminRequired
	 */
	public function update(int $id, string $title = null, string $emoji = null, ?bool $archived = null): DataResponse {
		return $this->handleError(function () use ($id, $title, $emoji, $archived) {
			return $this->service->update($id, $title, $emoji, null, $archived, $this->userId);
		});
	}
}
