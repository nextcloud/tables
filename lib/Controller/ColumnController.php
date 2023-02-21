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
		string $title,
		string $type,
		string $subtype = '',
		string $numberPrefix = '',
		string $numberSuffix = '',
		bool $mandatory = false,
		string $description = '',
		string $textDefault = '',
		string $textAllowedPattern = '',
		int $textMaxLength = -1,
		float $numberDefault = null,
		float $numberMin = null,
		float $numberMax = null,
		int $numberDecimals = null,
		string $selectionOptions = '',
		string $selectionDefault = '',
		int $orderWeight = 0,
		string $datetimeDefault = ''
	): DataResponse {
		return $this->handleError(function () use (
			$tableId,
			$title,
			$type,
			$subtype,
			$numberPrefix,
			$numberSuffix,
			$mandatory,
			$description,
			$textDefault,
			$textAllowedPattern,
			$textMaxLength,
			$numberDefault,
			$numberMin,
			$numberMax,
			$numberDecimals,
			$selectionOptions,
			$selectionDefault,
			$orderWeight,
			$datetimeDefault) {
			return $this->service->create(
				$tableId,
				$title,
				$this->userId,
				$type,
				$subtype,
				$numberPrefix,
				$numberSuffix,
				$mandatory,
				$description,
				$textDefault,
				$textAllowedPattern,
				$textMaxLength,
				$numberDefault,
				$numberMin,
				$numberMax,
				$numberDecimals,
				$selectionOptions,
				$selectionDefault,
				$orderWeight,
				$datetimeDefault);
		});
	}

	/**
	 * @NoAdminRequired
	 */
	public function update(
		int $id,
		int $tableId,
		string $title,
		string $type,
		string $subtype = '',
		string $numberPrefix = '',
		string $numberSuffix = '',
		bool $mandatory = false,
		string $description = '',
		string $textDefault = '',
		string $textAllowedPattern = '',
		int $textMaxLength = null,
		float $numberDefault = null,
		float $numberMin = null,
		float $numberMax = null,
		int $numberDecimals = null,
		string $selectionOptions = '',
		string $selectionDefault = '',
		int $orderWeight = 0,
		string $datetimeDefault = ''
	): DataResponse {
		return $this->handleError(function () use (
			$id,
			$tableId,
			$title,
			$type,
			$subtype,
			$numberPrefix,
			$numberSuffix,
			$mandatory,
			$description,
			$textDefault,
			$textAllowedPattern,
			$textMaxLength,
			$numberDefault,
			$numberMin,
			$numberMax,
			$numberDecimals,
			$selectionOptions,
			$selectionDefault,
			$orderWeight,
			$datetimeDefault
		) {
			return $this->service->update(
				$id,
				$tableId,
				$this->userId,
				$title,
				$type,
				$subtype,
				$numberPrefix,
				$numberSuffix,
				$mandatory,
				$description,
				$textDefault,
				$textAllowedPattern,
				$textMaxLength,
				$numberDefault,
				$numberMin,
				$numberMax,
				$numberDecimals,
				$selectionOptions,
				$selectionDefault,
				$orderWeight,
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
