<?php

namespace OCA\Tables\Controller;

use OCA\Tables\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\Util;
use OCA\Text\Event\LoadEditor;
use OCP\EventDispatcher\IEventDispatcher;

class PageController extends Controller {
	private IEventDispatcher $eventDispatcher;

	public function __construct(IRequest $request, IEventDispatcher $eventDispatcher) {
		parent::__construct(Application::APP_ID, $request);
		$this->eventDispatcher = $eventDispatcher;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
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
}
