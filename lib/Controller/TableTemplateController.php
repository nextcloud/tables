<?php

namespace OCA\Tables\Controller;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Service\TableTemplateService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class TableTemplateController extends Controller {
	/** @var TableTemplateService */
	private $service;

	protected LoggerInterface $logger;

	use Errors;

	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		TableTemplateService $service) {
		parent::__construct(Application::APP_ID, $request);
		$this->logger = $logger;
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
