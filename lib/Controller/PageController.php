<?php

namespace OCA\Tables\Controller;

use OCA\Tables\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\Util;

class PageController extends Controller {
	public function __construct(IRequest $request) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * Render default template
	 */
	public function index(): TemplateResponse
    {
		Util::addScript(Application::APP_ID, 'tables-main');
        Util::addStyle(Application::APP_ID, 'grid');
        Util::addStyle(Application::APP_ID, 'style');

		return new TemplateResponse(Application::APP_ID, 'main');
	}
}
