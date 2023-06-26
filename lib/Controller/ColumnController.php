<?php

namespace OCA\Tables\Controller;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Service\ColumnService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class ColumnController extends Controller {
	private ColumnService $service;

	private string $userId;

	use Errors;

	public function __construct(IRequest     $request,
		ColumnService $service,
		string $userId) {
		parent::__construct(Application::APP_ID, $request);
		$this->service = $service;
		$this->userId = $userId;
	}

	/**
	 * @NoAdminRequired
	 */
	public function index(int $tableId): DataResponse {
		return $this->handleError(function () use ($tableId) {
			return $this->service->findAllByTable($tableId);
		});
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
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
		int $tableId,
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

		?string $datetimeDefault
	): DataResponse {
		return $this->handleError(function () use (
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

			$datetimeDefault) {
			return $this->service->create(
				$this->userId,
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

				$datetimeDefault);
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
