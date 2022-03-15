<?php

namespace OCA\Tables\Controller;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Db\Table;
use OCA\Tables\Service\TableTemplateService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class TableTemplateController extends Controller {
	/** @var TableTemplateService */
	private $service;

    use Errors;

    public function __construct(IRequest     $request,
                                TableTemplateService $service) {
		parent::__construct(Application::APP_ID, $request);
		$this->service = $service;
	}

    /**
     * @NoAdminRequired
     */
	public function list(): DataResponse {
        return $this->handleError(function () {
            return $this->service->getTemplateList();
        });
    }

}
