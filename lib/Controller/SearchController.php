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

	use Errors;

	public function __construct(
		IRequest $request,
		private LoggerInterface $logger,
		private SearchService $service,
		private string $userId) {
		parent::__construct(Application::APP_ID, $request);
	}


	#[NoAdminRequired]
	public function all(string $term = ''): DataResponse {
		return $this->handleError(fn() => $this->service->all($term));
	}

}
