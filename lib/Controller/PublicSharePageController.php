<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Controller;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Db\Share;
use OCA\Tables\Service\NodeService;
use OCA\Tables\Service\ShareService;
use OCA\Tables\Service\ValueObject\ShareToken;
use OCA\Tables\Share\SharePageShareDataDecorator;
use OCA\Text\Event\LoadEditor;
use OCP\AppFramework\AuthPublicShareController;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\Util;

#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]
class PublicSharePageController extends AuthPublicShareController {
	private ?Share $share = null;
	private ?ShareToken $shareToken = null;

	public function __construct(
		string $appName,
		IRequest $request,
		ISession $session,
		IURLGenerator $urlGenerator,
		private ShareService $shareService,
		private NodeService $nodeService,
		private IInitialState $initialState,
		private IEventDispatcher $eventDispatcher,
	) {
		parent::__construct($appName, $request, $session, $urlGenerator);
		$token = $request->getParam('token');
		if (is_string($token) && $token !== '') {
			$this->shareToken = new ShareToken($token);
			try {
				$this->share = $this->shareService->findByToken($this->shareToken);
			} catch (\Exception $e) {
				$this->share = null;
			}
		}
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'GET', url: '/s/{token}')]
	#[AnonRateLimit(limit: 10, period: 10)]
	public function showShare(): TemplateResponse {

		$this->loadStyles();
		Util::addScript(Application::APP_ID, 'tables-main');

		$nodeData = $this->nodeService->getPublicDataOfNode($this->share->getNodeType(), $this->share->getNodeId());

		$this->initialState->provideInitialState('shareToken', (string)$this->shareToken);
		$this->initialState->provideInitialState('nodeType', $this->share->getNodeType());
		$this->initialState->provideInitialState('nodeData', $nodeData);

		if (class_exists(LoadEditor::class)) {
			$this->eventDispatcher->dispatchTyped(new LoadEditor());
		}

		return new TemplateResponse(Application::APP_ID, 'main', [], TemplateResponse::RENDER_AS_PUBLIC);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'GET', url: '/s/{token}/authenticate')]
	#[AnonRateLimit(limit: 10, period: 10)]
	public function showAuthenticate(): TemplateResponse {
		$templateParameters = ['share' => new SharePageShareDataDecorator($this->share)];

		$response = new TemplateResponse('core', 'publicshareauth', $templateParameters, 'guest');
		return $response;
	}

	protected function showAuthFailed(): TemplateResponse {
		$templateParameters = ['share' => new SharePageShareDataDecorator($this->share), 'wrongpw' => true];
		return new TemplateResponse('core', 'publicshareauth', $templateParameters, 'guest');
	}

	protected function getPasswordHash(): ?string {
		return $this->share->getPassword();
	}

	public function isValidToken(): bool {
		return $this->share !== null;
	}

	protected function isPasswordProtected(): bool {
		if ($this->share === null) {
			return false;
		}
		$password = $this->share->getPassword();
		return $password !== null && $password !== '';
	}

	protected function verifyPassword(string $password): bool {
		return $password === $this->share->getPassword();
	}

	protected function loadStyles(): void {
		Util::addStyle(Application::APP_ID, 'grid');
		Util::addStyle(Application::APP_ID, 'modal');
		Util::addStyle(Application::APP_ID, 'tiptap');
		Util::addStyle(Application::APP_ID, 'error');
	}
}
