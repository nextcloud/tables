<?php

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Controller;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Service\TableTemplateService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class TableTemplateController extends Controller {
	use Errors;

	public function __construct(
		IRequest $request,
		protected LoggerInterface $logger,
		private TableTemplateService $service) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	public function list(): DataResponse {
		return $this->handleError(fn() => $this->service->getTemplateList());
	}
}
