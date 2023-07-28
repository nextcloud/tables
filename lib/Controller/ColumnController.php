<?php

namespace OCA\Tables\Controller;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Service\ColumnService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class ColumnController extends Controller {
	private ColumnService $service;

	private string $userId;
	
	protected LoggerInterface $logger;

	use Errors;

	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		ColumnService $service,
		string $userId) {
		parent::__construct(Application::APP_ID, $request);
		$this->logger = $logger;
		$this->service = $service;
		$this->userId = $userId;
	}

	/**
	 * @NoAdminRequired
	 */
	public function index(int $tableId, int $viewId): DataResponse {
		return $this->handleError(function () use ($tableId, $viewId) {
			return $this->service->findAllByTable($tableId, $viewId);
		});
	}

	/**
	 * @NoAdminRequired
	 */
	public function indexView(int $viewId): DataResponse {
		return $this->handleError(function () use ($viewId) {
			return $this->service->findAllByView($viewId);
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
	public function create(
		int $viewId,
		string $type,
		?string $subtype,
		string $title,
		bool $mandatory,
		?string $description,
		?int $orderWeight,

		?string $textDefault,
		?string $textAllowedPattern,
		?int $textMaxLength,

		?string $numberPrefix,
		?string $numberSuffix,
		?float $numberDefault,
		?float $numberMin,
		?float $numberMax,
		?int $numberDecimals,

		?string $selectionOptions,
		?string $selectionDefault,

		?string $datetimeDefault,
		?array $selectedViewIds
	): DataResponse {
		return $this->handleError(function () use (
			$viewId,
			$type,
			$subtype,
			$title,
			$mandatory,
			$description,
			$orderWeight,

			$textDefault,
			$textAllowedPattern,
			$textMaxLength,

			$numberPrefix,
			$numberSuffix,
			$numberDefault,
			$numberMin,
			$numberMax,
			$numberDecimals,

			$selectionOptions,
			$selectionDefault,

			$datetimeDefault,
			$selectedViewIds) {
			return $this->service->create(
				$this->userId,
				$viewId,
				$type,
				$subtype,
				$title,
				$mandatory,
				$description,
				$orderWeight,

				$textDefault,
				$textAllowedPattern,
				$textMaxLength,

				$numberPrefix,
				$numberSuffix,
				$numberDefault,
				$numberMin,
				$numberMax,
				$numberDecimals,

				$selectionOptions,
				$selectionDefault,

				$datetimeDefault,
				$selectedViewIds);
		});
	}

	/**
	 * @NoAdminRequired
	 */
	public function update(
		int $id,
		?int $tableId,
		?string $type,
		?string $subtype,
		?string $title,
		?bool $mandatory,
		?string $description,
		?int $orderWeight,

		?string $textDefault,
		?string $textAllowedPattern,
		?int $textMaxLength,

		?string $numberPrefix,
		?string $numberSuffix,
		?float $numberDefault,
		?float $numberMin,
		?float $numberMax,
		?int $numberDecimals,

		?string $selectionOptions,
		?string $selectionDefault,

		?string $datetimeDefault
	): DataResponse {
		return $this->handleError(function () use (
			$id,
			$tableId,
			$type,
			$subtype,
			$title,
			$mandatory,
			$description,
			$orderWeight,

			$textDefault,
			$textAllowedPattern,
			$textMaxLength,

			$numberPrefix,
			$numberSuffix,
			$numberDefault,
			$numberMin,
			$numberMax,
			$numberDecimals,

			$selectionOptions,
			$selectionDefault,

			$datetimeDefault
		) {
			return $this->service->update(
				$id,
				$tableId,
				$this->userId,
				$type,
				$subtype,
				$title,
				$mandatory,
				$description,
				$orderWeight,

				$textDefault,
				$textAllowedPattern,
				$textMaxLength,

				$numberPrefix,
				$numberSuffix,
				$numberDefault,
				$numberMin,
				$numberMax,
				$numberDecimals,

				$selectionOptions,
				$selectionDefault,

				$datetimeDefault);
		});
	}

	/**
	 * @NoAdminRequired
	 */
	public function destroy(int $id): DataResponse {
		return $this->handleError(function () use ($id) {
			return $this->service->delete($id, false, $this->userId);
		});
	}
}
