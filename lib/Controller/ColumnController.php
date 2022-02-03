<?php

namespace OCA\Tables\Controller;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Service\ColumnService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\DB\Exception;
use OCP\IRequest;

class ColumnController extends Controller {
	/** @var ColumnService */
	private $service;

	/** @var string */
	private $userId;

	use Errors;

	public function __construct(IRequest     $request,
                                ColumnService $service,
                                             $userId) {
		parent::__construct(Application::APP_ID, $request);
		$this->service = $service;
		$this->userId = $userId;
	}

    /**
     * @NoAdminRequired
     * @throws \Exception
     */
	public function index(int $tableId): DataResponse {
		return new DataResponse($this->service->findAllByTable($this->userId, $tableId));
	}

	/**
	 * @NoAdminRequired
	 */
	public function show(int $id): DataResponse {
		return $this->handleNotFound(function () use ($id) {
			return $this->service->find($id, $this->userId);
		});
	}

    /**
     * @NoAdminRequired
     * @throws Exception
     */
	public function create(
        int $tableId,
        string $title,
        string $type,
        string $prefix = '',
        string $suffix = '',
        bool $mandatory = false,
        string $description = '',
        string $textDefault = '',
        string $textAllowedPattern = '',
        int $textMaxLength = -1,
        bool $textMultiline = false,
        float $numberDefault = null,
        float $numberMin = null,
        float $numberMax = null,
        int $numberDecimals = null
    ): DataResponse {
		return new DataResponse($this->service->create(
            $tableId,
            $title,
            $this->userId,
            $type,
            $prefix,
            $suffix,
            $mandatory,
            $description,
            $textDefault,
            $textAllowedPattern,
            $textMaxLength,
            $textMultiline,
            $numberDefault,
            $numberMin,
            $numberMax,
            $numberDecimals));
	}

	/**
	 * @NoAdminRequired
	 */
	public function update(
        int $id,
        int $tableId,
        string $userId,
        string $title,
        string $type,
        string $prefix,
        string $suffix,
        bool $mandatory,
        string $description,
        string $textDefault,
        string $textAllowedPattern,
        int $textMaxLength,
        bool $textMultiline,
        float $numberDefault,
        float $numberMin,
        float $numberMax,
        int $numberDecimals
    ): DataResponse {
		return $this->handleNotFound(function () use (
            $id,
            $tableId,
            $userId,
            $title,
            $type,
            $prefix,
            $suffix,
            $mandatory,
            $description,
            $textDefault,
            $textAllowedPattern,
            $textMaxLength,
            $textMultiline,
            $numberDefault,
            $numberMin,
            $numberMax,
            $numberDecimals
        ) {
			return $this->service->update(
                $id,
                $tableId,
                $userId,
                $title,
                $type,
                $prefix,
                $suffix,
                $mandatory,
                $description,
                $textDefault,
                $textAllowedPattern,
                $textMaxLength,
                $textMultiline,
                $numberDefault,
                $numberMin,
                $numberMax,
                $numberDecimals);
		});
	}

	/**
	 * @NoAdminRequired
	 */
	public function destroy(int $id): DataResponse {
		return $this->handleNotFound(function () use ($id) {
			return $this->service->delete($id, $this->userId);
		});
	}
}
