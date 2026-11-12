<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Controller;

use Closure;
use OCA\Tables\AppInfo\Application;
use OCA\Tables\Middleware\Attribute\RequirePermission;
use OCA\Tables\Model\ImportProblem;
use OCA\Tables\ResponseDefinitions;
use OCA\Tables\Service\ImportService;
use OCA\Tables\UploadException;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\DataResponse;
use OCP\Files\NotPermittedException;
use OCP\IL10N;
use OCP\IRequest;
use OCP\Util;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type TablesImportV2Response from ResponseDefinitions
 */
class ImportController extends Controller {
	public const MIME_TYPES = [
		'text/csv',
		'text/plain',
		'application/vnd.ms-excel',
		'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		'application/xml',
		'text/html',
		'application/vnd.oasis.opendocument.spreadsheet',
	];

	public function __construct(
		IRequest $request,
		protected LoggerInterface $logger,
		private readonly ImportService $service,
		private readonly string $userId,
		private readonly IL10N $l10n,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * @template T of array<array-key, mixed>|null|object|scalar
	 * @param Closure(): T $callback
	 * @return DataResponse<Http::STATUS_OK, T, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 */
	protected function handleError(Closure $callback): DataResponse {
		try {
			return new DataResponse($callback());
		} catch (\Throwable $e) {
			$this->logger->error('Import request failed', ['exception' => $e]);
			$code = ImportProblem::codeForException($e);
			$status = match ($code) {
				ImportProblem::CODE_RESOURCE_NOT_FOUND => Http::STATUS_NOT_FOUND,
				ImportProblem::CODE_PERMISSION_DENIED => Http::STATUS_FORBIDDEN,
				ImportProblem::CODE_FILE_READ_FAILED => Http::STATUS_BAD_REQUEST,
				default => Http::STATUS_INTERNAL_SERVER_ERROR,
			};
			$message = ImportProblem::describeCode($this->l10n, $code);
			return new DataResponse(['message' => $message], $status);
		}
	}

	#[NoAdminRequired]
	#[UserRateLimit(limit: 20, period: 60)]
	#[RequirePermission(permission: Application::PERMISSION_READ, type: Application::NODE_TYPE_TABLE, idParam: 'tableId')]
	public function previewImportTable(int $tableId, String $path): DataResponse {
		return $this->handleError(fn () => $this->service->previewImport($tableId, null, $path));
	}

	/**
	 * @deprecated Use {@link importV2InTable} instead
	 */
	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_CREATE, type: Application::NODE_TYPE_TABLE, idParam: 'tableId')]
	public function importInTable(int $tableId, String $path, bool $createMissingColumns = true, array $columnsConfig = []): DataResponse {
		return $this->handleError(
			// minimal permission is checked, creating columns requires MANAGE permissions - currently tested on service layer
			fn () => $this->service->import($tableId, null, $path, $createMissingColumns, $columnsConfig));
	}

	/**
	 * Import data into a table from a file in the user's storage
	 *
	 * Small files are imported synchronously and the result is returned
	 * in `result`. Larger files are queued as a background job and
	 * `async` is set to true; the import stats are then published via
	 * an activity entry once the job finished.
	 *
	 * @param int $tableId Table ID
	 * @param string $path Path to a file in the user's storage
	 * @param bool $createMissingColumns Create missing columns
	 * @param list<array<string, mixed>> $columnsConfig Column configuration for the import
	 * @return DataResponse<Http::STATUS_OK, TablesImportV2Response, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Import finished or queued
	 * 400: Invalid request
	 * 403: No permissions
	 * 404: Not found
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 20, period: 60)]
	#[RequirePermission(permission: Application::PERMISSION_CREATE, type: Application::NODE_TYPE_TABLE, idParam: 'tableId')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function importV2InTable(int $tableId, String $path, bool $createMissingColumns = true, array $columnsConfig = []): DataResponse {
		return $this->handleError(function () use ($tableId, $path, $createMissingColumns, $columnsConfig) {
			// minimal permission is checked, creating columns requires MANAGE permissions - currently tested on service layer
			if ($this->service->shouldImportAsync($path)) {
				$this->service->scheduleImport($tableId, null, $path, $createMissingColumns, $columnsConfig);
				return ['async' => true];
			}
			$result = $this->service->importV2($this->userId, ImportService::IMPORT_TYPE_USER_FILE, $path, $tableId, null, $createMissingColumns, $columnsConfig);
			return ['async' => false, 'result' => $result->jsonSerialize()];
		});
	}

	#[NoAdminRequired]
	#[UserRateLimit(limit: 20, period: 60)]
	#[RequirePermission(permission: Application::PERMISSION_CREATE, type: Application::NODE_TYPE_VIEW, idParam: 'viewId')]
	public function previewImportView(int $viewId, String $path): DataResponse {
		return $this->handleError(fn () => $this->service->previewImport(null, $viewId, $path));
	}

	/**
	 * @deprecated Use {@link importV2InView} instead
	 */
	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_CREATE, type: Application::NODE_TYPE_VIEW, idParam: 'viewId')]
	public function importInView(int $viewId, String $path, bool $createMissingColumns = true, array $columnsConfig = []): DataResponse {
		return $this->handleError(
			// minimal permission is checked, creating columns requires MANAGE permissions - currently tested on service layer
			fn () => $this->service->import(null, $viewId, $path, $createMissingColumns, $columnsConfig));
	}

	/**
	 * Import data into a view from a file in the user's storage
	 *
	 * Small files are imported synchronously and the result is returned
	 * in `result`. Larger files are queued as a background job and
	 * `async` is set to true; the import stats are then published via
	 * an activity entry once the job finished.
	 *
	 * @param int $viewId View ID
	 * @param string $path Path to a file in the user's storage
	 * @param bool $createMissingColumns Create missing columns
	 * @param list<array<string, mixed>> $columnsConfig Column configuration for the import
	 * @return DataResponse<Http::STATUS_OK, TablesImportV2Response, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Import finished or queued
	 * 400: Invalid request
	 * 403: No permissions
	 * 404: Not found
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 20, period: 60)]
	#[RequirePermission(permission: Application::PERMISSION_CREATE, type: Application::NODE_TYPE_VIEW, idParam: 'viewId')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function importV2InView(int $viewId, String $path, bool $createMissingColumns = true, array $columnsConfig = []): DataResponse {
		return $this->handleError(function () use ($viewId, $path, $createMissingColumns, $columnsConfig) {
			// minimal permission is checked, creating columns requires MANAGE permissions - currently tested on service layer
			if ($this->service->shouldImportAsync($path)) {
				$this->service->scheduleImport(null, $viewId, $path, $createMissingColumns, $columnsConfig);
				return ['async' => true];
			}
			$result = $this->service->importV2($this->userId, ImportService::IMPORT_TYPE_USER_FILE, $path, null, $viewId, $createMissingColumns, $columnsConfig);
			return ['async' => false, 'result' => $result->jsonSerialize()];
		});
	}

	#[NoAdminRequired]
	#[UserRateLimit(limit: 20, period: 60)]
	#[RequirePermission(permission: Application::PERMISSION_CREATE, type: Application::NODE_TYPE_TABLE, idParam: 'tableId')]
	public function previewUploadImportTable(int $tableId): DataResponse {
		try {
			$file = $this->getUploadedFile('uploadfile');
			return $this->handleError(fn () => $this->service->previewImport($tableId, null, $file['tmp_name']));
		} catch (UploadException|NotPermittedException $e) {
			return $this->handleUploadError($e);
		}
	}

	/**
	 * @deprecated Use {@link importV2UploadInTable} instead
	 */
	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_CREATE, type: Application::NODE_TYPE_TABLE, idParam: 'tableId')]
	public function importUploadInTable(int $tableId, bool $createMissingColumns = true, string $columnsConfig = ''): DataResponse {
		try {
			$columnsConfigArray = json_decode($columnsConfig, true);
			$file = $this->getUploadedFile('uploadfile');
			return $this->handleError(
				// minimal permission is checked, creating columns requires MANAGE permissions - currently tested on service layer
				fn () => $this->service->import($tableId, null, $file['tmp_name'], $createMissingColumns, $columnsConfigArray));
		} catch (UploadException|NotPermittedException $e) {
			return $this->handleUploadError($e);
		}
	}

	#[NoAdminRequired]
	#[UserRateLimit(limit: 20, period: 60)]
	#[RequirePermission(permission: Application::PERMISSION_CREATE, type: Application::NODE_TYPE_VIEW, idParam: 'viewId')]
	public function previewUploadImportView(int $viewId): DataResponse {
		try {
			$file = $this->getUploadedFile('uploadfile');
			return $this->handleError(fn () => $this->service->previewImport(null, $viewId, $file['tmp_name']));
		} catch (UploadException|NotPermittedException $e) {
			return $this->handleUploadError($e);
		}
	}

	#[NoAdminRequired]
	#[UserRateLimit(limit: 20, period: 60)]
	#[RequirePermission(permission: Application::PERMISSION_CREATE, type: Application::NODE_TYPE_TABLE, idParam: 'tableId')]
	public function importV2UploadInTable(int $tableId, bool $createMissingColumns = true, string $columnsConfig = ''): DataResponse {
		try {
			$columnsConfigArray = json_decode($columnsConfig, true);
			$file = $this->getUploadedFile('uploadfile');
			return $this->handleError(function () use ($tableId, $file, $createMissingColumns, $columnsConfigArray) {
				// minimal permission is checked, creating columns requires MANAGE permissions - currently tested on service layer
				if ($this->service->shouldImportAsync($file['tmp_name'])) {
					$this->service->scheduleImport($tableId, null, $file['tmp_name'], $createMissingColumns, $columnsConfigArray, $file['name'] ?? null);
					return ['async' => true];
				}
				$result = $this->service->importV2($this->userId, ImportService::IMPORT_TYPE_UPLOADED_FILE, $file['tmp_name'], $tableId, null, $createMissingColumns, $columnsConfigArray, $file['name'] ?? null);
				return ['async' => false, 'result' => $result->jsonSerialize()];
			});
		} catch (UploadException|NotPermittedException $e) {
			return $this->handleUploadError($e);
		}
	}

	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_CREATE, type: Application::NODE_TYPE_VIEW, idParam: 'viewId')]
	public function importUploadInView(int $viewId, bool $createMissingColumns = true, string $columnsConfig = ''): DataResponse {
		try {
			$columnsConfigArray = json_decode($columnsConfig, true);
			$file = $this->getUploadedFile('uploadfile');
			return $this->handleError(
				// minimal permission is checked, creating columns requires MANAGE permissions - currently tested on service layer
				fn () => $this->service->import(null, $viewId, $file['tmp_name'], $createMissingColumns, $columnsConfigArray));
		} catch (UploadException|NotPermittedException $e) {
			return $this->handleUploadError($e);
		}
	}

	#[NoAdminRequired]
	#[UserRateLimit(limit: 20, period: 60)]
	#[RequirePermission(permission: Application::PERMISSION_CREATE, type: Application::NODE_TYPE_VIEW, idParam: 'viewId')]
	public function importV2UploadInView(int $viewId, bool $createMissingColumns = true, string $columnsConfig = ''): DataResponse {
		try {
			$columnsConfigArray = json_decode($columnsConfig, true);
			$file = $this->getUploadedFile('uploadfile');
			return $this->handleError(function () use ($viewId, $file, $createMissingColumns, $columnsConfigArray) {
				// minimal permission is checked, creating columns requires MANAGE permissions - currently tested on service layer
				if ($this->service->shouldImportAsync($file['tmp_name'])) {
					$this->service->scheduleImport(null, $viewId, $file['tmp_name'], $createMissingColumns, $columnsConfigArray, $file['name'] ?? null);
					return ['async' => true];
				}
				$result = $this->service->importV2($this->userId, ImportService::IMPORT_TYPE_UPLOADED_FILE, $file['tmp_name'], null, $viewId, $createMissingColumns, $columnsConfigArray, $file['name'] ?? null);
				return ['async' => false, 'result' => $result->jsonSerialize()];
			});
		} catch (UploadException|NotPermittedException $e) {
			return $this->handleUploadError($e);
		}
	}

	/**
	 * @return DataResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>
	 */
	private function handleUploadError(UploadException|NotPermittedException $exception): DataResponse {
		$this->logger->error('Upload error', ['exception' => $exception]);
		$message = $exception instanceof UploadException
			? $exception->getMessage()
			: ImportProblem::describeCode($this->l10n, ImportProblem::CODE_PERMISSION_DENIED);
		return new DataResponse(['message' => $message], Http::STATUS_BAD_REQUEST);
	}

	/**
	 * @param string $key
	 * @return array
	 * @throws UploadException
	 */
	private function getUploadedFile(string $key): array {
		$file = $this->request->getUploadedFile($key);
		$phpFileUploadErrors = [
			UPLOAD_ERR_OK => $this->l10n->t('The file was uploaded'),
			UPLOAD_ERR_INI_SIZE => $this->l10n->t('The uploaded file exceeds the upload_max_filesize directive in php.ini'),
			UPLOAD_ERR_FORM_SIZE => $this->l10n->t('The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form'),
			UPLOAD_ERR_PARTIAL => $this->l10n->t('The file was only partially uploaded'),
			UPLOAD_ERR_NO_FILE => $this->l10n->t('No file was uploaded'),
			UPLOAD_ERR_NO_TMP_DIR => $this->l10n->t('Missing a temporary folder'),
			UPLOAD_ERR_CANT_WRITE => $this->l10n->t('Could not write file to disk'),
			UPLOAD_ERR_EXTENSION => $this->l10n->t('A PHP extension stopped the file upload'),
		];

		if (empty($file)) {
			throw new UploadException($this->l10n->t('No file uploaded or file size exceeds maximum of %s', [Util::humanFileSize(Util::uploadLimit())]));
		}

		if (array_key_exists('error', $file) && $file['error'] !== UPLOAD_ERR_OK) {
			throw new UploadException($phpFileUploadErrors[$file['error']]);
		}

		if (isset($file['tmp_name'], $file['name'], $file['type'])) {
			$fileType = $file['type'];

			if (function_exists('mime_content_type')) {
				$fileType = @mime_content_type($file['tmp_name']);
			}

			if (!$fileType) {
				$fileType = $file['type'];
			}

			if (!in_array($fileType, self::MIME_TYPES, true)) {
				throw new UploadException('File type not supported: ' . $fileType);
			}

			$newFileResource = fopen($file['tmp_name'], 'rb');

			if ($newFileResource === false) {
				throw new UploadException('Could not read file');
			}
		}

		return $file;
	}
}
