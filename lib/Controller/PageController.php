<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Controller;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Service\NodeService;
use OCA\Tables\Service\ShareService;
use OCA\Tables\Service\ValueObject\ShareToken;
use OCA\Text\Event\LoadEditor;
use OCA\Viewer\Event\LoadViewer;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\INavigationManager;
use OCP\IRequest;
use OCP\Util;

class PageController extends Controller {

	public function __construct(
		IRequest $request,
		protected IEventDispatcher $eventDispatcher,
		protected INavigationManager $navigationManager,
		protected IInitialState $initialState,
		protected ShareService $shareService,
		protected NodeService $nodeService,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Render default template
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]
	public function index(): TemplateResponse {
		Util::addScript(Application::APP_ID, 'tables-main');
		$this->loadStyles();

		if (class_exists(LoadViewer::class)) {
			$this->eventDispatcher->dispatchTyped(new LoadViewer());
		}

		if (class_exists(LoadEditor::class)) {
			$this->eventDispatcher->dispatchTyped(new LoadEditor());
		}

		return new TemplateResponse(Application::APP_ID, 'main');
	}

	/**
	 * Render default template
	 *
	 * @psalm-param int<0, max> $appId
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]
	public function context(int $contextId): TemplateResponse {
		$navId = Application::APP_ID . '_application_' . $contextId;
		$this->navigationManager->setActiveEntry($navId);

		$this->initialState->provideInitialState('contextId', $contextId);

		return $this->index();
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]
	#[FrontpageRoute(verb: 'GET', url: '/s/{token}')]
	#[AnonRateLimit(limit: 10, period: 10)]
	public function linkShare(string $token): TemplateResponse {
		Util::addScript(Application::APP_ID, 'tables-main');
		$this->loadStyles();

		$shareToken = new ShareToken($token);
		$share = $this->shareService->findByToken($shareToken);
		$nodeData = $this->nodeService->getPublicDataOfNode($share->getNodeType(), $share->getNodeId());

		$this->initialState->provideInitialState('shareToken', (string)$shareToken);
		$this->initialState->provideInitialState('nodeType', $share->getNodeType());
		$this->initialState->provideInitialState('nodeData', $nodeData);

		return new TemplateResponse(Application::APP_ID, 'main', [], TemplateResponse::RENDER_AS_PUBLIC);
	}

	protected function loadStyles(): void {
		Util::addStyle(Application::APP_ID, 'grid');
		Util::addStyle(Application::APP_ID, 'modal');
		Util::addStyle(Application::APP_ID, 'tiptap');
		Util::addStyle(Application::APP_ID, 'tables-style');
	}
}
