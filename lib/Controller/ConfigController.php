<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Controller;

use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\ResponseDefinitions;
use OCA\Tables\Service\ConfigService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type TablesNotifyConfig from ResponseDefinitions
 */

class ConfigController extends AOCSController {
	private ConfigService $configService;

	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		IL10N $n,
		string $userId,
		ConfigService $configService,
	) {
		parent::__construct($request, $logger, $n, $userId);
		$this->configService = $configService;
	}

	/**
	 * Gets the config for a specific table
	 *
	 * @param int $id Table id
	 * @return DataResponse<Http::STATUS_OK, TablesNotifyConfig, array{}>
	 *
	 * 200: Table config returned
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getTableConfig(int $id): DataResponse {
		$config = $this->configService->getTableConfig($id);
		return new DataResponse($config);
	}

	/**
	 * Gets the config for a specific view
	 *
	 * @param int $id View id
	 * @return DataResponse<Http::STATUS_OK, TablesNotifyConfig, array{}>
	 *
	 * 200: View config returned
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getViewConfig(int $id): DataResponse {
		$config = $this->configService->getViewConfig($id);
		return new DataResponse($config);
	}

	/**
	 * Sets a config value for a specific key
	 *
	 * @param string $key Config key
	 * @param mixed $value Config value
	 * @return DataResponse<Http::STATUS_OK, bool, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN, array{message: string}, array{}>
	 *
	 * 200: Config updated
	 * 400: bad request
	 * 403: No permissions
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function setValue(string $key, mixed $value): DataResponse|NotFoundResponse {
		try {
			// Validate input
			if (empty($key)) {
				throw new BadRequestError('Config key cannot be empty');
			}
			if (!is_string($value) && !is_numeric($value) && !is_bool($value)) {
				throw new BadRequestError('Config value must be a string, number, or boolean');
			}
			$this->configService->set($key, $value);
			return new DataResponse(true);
		} catch (BadRequestError $e) {
			return $this->handleBadRequestError($e);
		} catch (PermissionError $e) {
			return $this->handlePermissionError($e);
		}
	}
}
