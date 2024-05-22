<?php

namespace OCA\Tables\Controller;

use OCA\Tables\AppInfo\Application;
use OCA\Text\Event\LoadEditor;
use OCP\AppFramework\Controller;
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
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @IgnoreOpenAPI
	 *
	 * Render default template
	 */
	public function index(): TemplateResponse {
		Util::addScript(Application::APP_ID, 'tables-main');
		Util::addStyle(Application::APP_ID, 'grid');
		Util::addStyle(Application::APP_ID, 'modal');
		Util::addStyle(Application::APP_ID, 'tiptap');

		if (class_exists(LoadEditor::class)) {
			$this->eventDispatcher->dispatchTyped(new LoadEditor());
		}

		return new TemplateResponse(Application::APP_ID, 'main');
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @IgnoreOpenAPI
	 *
	 * Render default template
	 *
	 * @psalm-param int<0, max> $appId
	 */
	public function context(int $contextId): TemplateResponse {
		$navId = Application::APP_ID . '_application_' . $contextId;
		$this->navigationManager->setActiveEntry($navId);

		$this->initialState->provideInitialState('contextId', $contextId);

		return $this->index();
	}
}
