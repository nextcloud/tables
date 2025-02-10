<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Controller;

use OCA\Tables\Service\ContextService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\DataResponse;
use OCP\INavigationManager;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;

/**
 * This is a workaround until https://github.com/nextcloud/server/pull/49904 is
 * settled in all covered NC versions; expected >= 31.
 */
class NavigationController extends \OC\Core\Controller\NavigationController {
	public function __construct(
		protected ContextService $contextService,
		protected IUserSession $userSession,
		string $appName,
		IRequest $request,
		INavigationManager $navigationManager,
		IURLGenerator $urlGenerator,
	) {
		parent::__construct($appName, $request, $navigationManager, $urlGenerator);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]
	public function getAppsNavigation(bool $absolute = false): DataResponse {
		$this->contextService->addToNavigation($this->userSession->getUser()?->getUID());
		return parent::getAppsNavigation($absolute);
	}
}
