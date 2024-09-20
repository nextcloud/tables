<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Controller;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Service\SearchService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class SearchController extends Controller {

	private SearchService $service;
	private string $userId;
	private LoggerInterface $logger;

	use Errors;

	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		SearchService $service,
		string $userId) {
		parent::__construct(Application::APP_ID, $request);
		$this->userId = $userId;
		$this->service = $service;
		$this->logger = $logger;
	}


	#[NoAdminRequired]
	public function all(string $term = ''): DataResponse {
		return $this->handleError(function () use ($term) {
			return $this->service->all($term);
		});
	}

}
