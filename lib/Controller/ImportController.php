<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Controller;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Middleware\Attribute\RequirePermission;
use OCA\Tables\Service\ImportService;
use OCA\Tables\UploadException;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\DataResponse;
use OCP\Files\NotPermittedException;
use OCP\IL10N;
use OCP\IRequest;
use OCP\Util;
use Psr\Log\LoggerInterface;

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

	private ImportService $service;
	private string $userId;

	protected LoggerInterface $logger;

	private IL10N $l10n;

	use Errors;

	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		ImportService $service,
		string $userId,
		IL10N $l10n) {
		parent::__construct(Application::APP_ID, $request);
		$this->logger = $logger;
		$this->service = $service;
		$this->userId = $userId;
		$this->l10n = $l10n;
	}

	#[NoAdminRequired]
	#[UserRateLimit(limit: 20, period: 60)]
	#[RequirePermission(permission: Application::PERMISSION_READ, type: Application::NODE_TYPE_TABLE, idParam: 'tableId')]
	public function previewImportTable(int $tableId, String $path): DataResponse {
		return $this->handleError(function () use ($tableId, $path) {
			return $this->service->previewImport($tableId, null, $path);
		});
	}

	/**
	 * @deprecated Use {@link scheduleImportInTable} instead
	 */
	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_CREATE, type: Application::NODE_TYPE_TABLE, idParam: 'tableId')]
	public function importInTable(int $tableId, String $path, bool $createMissingColumns = true, array $columnsConfig = []): DataResponse {
		return $this->handleError(function () use ($tableId, $path, $createMissingColumns, $columnsConfig) {
			// minimal permission is checked, creating columns requires MANAGE permissions - currently tested on service layer
			return $this->service->import($tableId, null, $path, $createMissingColumns, $columnsConfig);
		});
	}

	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_CREATE, type: Application::NODE_TYPE_TABLE, idParam: 'tableId')]
	public function scheduleImportInTable(int $tableId, String $path, bool $createMissingColumns = true, array $columnsConfig = []): DataResponse {
		return $this->handleError(function () use ($tableId, $path, $createMissingColumns, $columnsConfig) {
			// minimal permission is checked, creating columns requires MANAGE permissions - currently tested on service layer
			$file = $this->service->scheduleImport($tableId, null, $path, $createMissingColumns, $columnsConfig);

			return new DataResponse(['file' => $file]);
		});
	}

	#[NoAdminRequired]
	#[UserRateLimit(limit: 20, period: 60)]
	#[RequirePermission(permission: Application::PERMISSION_CREATE, type: Application::NODE_TYPE_VIEW, idParam: 'viewId')]
	public function previewImportView(int $viewId, String $path): DataResponse {
		return $this->handleError(function () use ($viewId, $path) {
			return $this->service->previewImport(null, $viewId, $path);
		});
	}

	/**
	 * @deprecated Use {@link scheduleImportInView} instead
	 */
	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_CREATE, type: Application::NODE_TYPE_VIEW, idParam: 'viewId')]
	public function importInView(int $viewId, String $path, bool $createMissingColumns = true, array $columnsConfig = []): DataResponse {
		return $this->handleError(function () use ($viewId, $path, $createMissingColumns, $columnsConfig) {
			// minimal permission is checked, creating columns requires MANAGE permissions - currently tested on service layer
			return $this->service->import(null, $viewId, $path, $createMissingColumns, $columnsConfig);
		});
	}

	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_CREATE, type: Application::NODE_TYPE_VIEW, idParam: 'viewId')]
	public function scheduleImportInView(int $viewId, String $path, bool $createMissingColumns = true, array $columnsConfig = []): DataResponse {
		return $this->handleError(function () use ($viewId, $path, $createMissingColumns, $columnsConfig) {
			// minimal permission is checked, creating columns requires MANAGE permissions - currently tested on service layer
			$file = $this->service->scheduleImport(null, $viewId, $path, $createMissingColumns, $columnsConfig);

			return new DataResponse(['file' => $file]);
		});
	}

	#[NoAdminRequired]
	#[UserRateLimit(limit: 20, period: 60)]
	#[RequirePermission(permission: Application::PERMISSION_CREATE, type: Application::NODE_TYPE_TABLE, idParam: 'tableId')]
	public function previewUploadImportTable(int $tableId): DataResponse {
		try {
			$file = $this->getUploadedFile('uploadfile');
			return $this->handleError(function () use ($tableId, $file) {
				return $this->service->previewImport($tableId, null, $file['tmp_name']);
			});
		} catch (UploadException|NotPermittedException $e) {
			$this->logger->error('Upload error', ['exception' => $e]);
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * @deprecated Use {@link scheduleImportUploadInTable} instead
	 */
	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_CREATE, type: Application::NODE_TYPE_TABLE, idParam: 'tableId')]
	public function importUploadInTable(int $tableId, bool $createMissingColumns = true, string $columnsConfig = ''): DataResponse {
		try {
			$columnsConfigArray = json_decode($columnsConfig, true);
			$file = $this->getUploadedFile('uploadfile');
			return $this->handleError(function () use ($tableId, $file, $createMissingColumns, $columnsConfigArray) {
				// minimal permission is checked, creating columns requires MANAGE permissions - currently tested on service layer
				return $this->service->import($tableId, null, $file['tmp_name'], $createMissingColumns, $columnsConfigArray);
			});
		} catch (UploadException|NotPermittedException $e) {
			$this->logger->error('Upload error', ['exception' => $e]);
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	#[UserRateLimit(limit: 20, period: 60)]
	#[RequirePermission(permission: Application::PERMISSION_CREATE, type: Application::NODE_TYPE_VIEW, idParam: 'viewId')]
	public function previewUploadImportView(int $viewId): DataResponse {
		try {
			$file = $this->getUploadedFile('uploadfile');
			return $this->handleError(function () use ($viewId, $file) {
				return $this->service->previewImport(null, $viewId, $file['tmp_name']);
			});
		} catch (UploadException|NotPermittedException $e) {
			$this->logger->error('Upload error', ['exception' => $e]);
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_CREATE, type: Application::NODE_TYPE_TABLE, idParam: 'tableId')]
	public function scheduleImportUploadInTable(int $tableId, bool $createMissingColumns = true, string $columnsConfig = ''): DataResponse {
		try {
			$columnsConfigArray = json_decode($columnsConfig, true);
			$file = $this->getUploadedFile('uploadfile');
			return $this->handleError(function () use ($tableId, $file, $createMissingColumns, $columnsConfigArray) {
				// minimal permission is checked, creating columns requires MANAGE permissions - currently tested on service layer
				$file = $this->service->scheduleImport($tableId, null, $file['tmp_name'], $createMissingColumns, $columnsConfigArray);

				return new DataResponse(['file' => $file]);
			});
		} catch (UploadException|NotPermittedException $e) {
			$this->logger->error('Upload error', ['exception' => $e]);
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * @deprecated Use {@link scheduleImportUploadInView} instead
	 */
	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_CREATE, type: Application::NODE_TYPE_VIEW, idParam: 'viewId')]
	public function importUploadInView(int $viewId, bool $createMissingColumns = true, string $columnsConfig = ''): DataResponse {
		try {
			$columnsConfigArray = json_decode($columnsConfig, true);
			$file = $this->getUploadedFile('uploadfile');
			return $this->handleError(function () use ($viewId, $file, $createMissingColumns, $columnsConfigArray) {
				// minimal permission is checked, creating columns requires MANAGE permissions - currently tested on service layer
				return $this->service->import(null, $viewId, $file['tmp_name'], $createMissingColumns, $columnsConfigArray);
			});
		} catch (UploadException|NotPermittedException $e) {
			$this->logger->error('Upload error', ['exception' => $e]);
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_CREATE, type: Application::NODE_TYPE_VIEW, idParam: 'viewId')]
	public function scheduleImportUploadInView(int $viewId, bool $createMissingColumns = true, string $columnsConfig = ''): DataResponse {
		try {
			$columnsConfigArray = json_decode($columnsConfig, true);
			$file = $this->getUploadedFile('uploadfile');
			return $this->handleError(function () use ($viewId, $file, $createMissingColumns, $columnsConfigArray) {
				// minimal permission is checked, creating columns requires MANAGE permissions - currently tested on service layer
				$file = $this->service->scheduleImport(null, $viewId, $file['tmp_name'], $createMissingColumns, $columnsConfigArray);

				return new DataResponse(['file' => $file]);
			});
		} catch (UploadException|NotPermittedException $e) {
			$this->logger->error('Upload error', ['exception' => $e]);
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
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
